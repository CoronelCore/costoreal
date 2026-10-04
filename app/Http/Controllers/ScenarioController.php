<?php

namespace App\Http\Controllers;

use App\Http\Requests\ScenarioRequest;
use App\Models\CostScenario;
use App\Models\Expense;
use App\Models\Product;
use App\Models\Trip;
use App\Modules\Importations\Actions\MutateTrip;
use App\Modules\Scenarios\SimulateCost;
use App\Modules\Shared\TransactionRunner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ScenarioController extends Controller
{
    public function __construct(private SimulateCost $simulate, private MutateTrip $mutation, private TransactionRunner $transaction) {}

    public function index(Request $request, Trip $trip)
    {
        $this->authorize('view', $trip);
        $request->validate(['page' => 'nullable|integer|min:1']);

        return response()->json($this->listing($trip));
    }

    public function listing(Trip $trip, string $pageName = 'page')
    {
        return $trip->scenarios()->select('id', 'trip_id', 'actor_id', 'name', 'base_revision', 'applied_revision', 'applied_at', 'created_at')
            ->with('actor:id,name')->latest('id')->paginate(20, ['*'], $pageName);
    }

    public function preview(ScenarioRequest $request, Trip $trip)
    {
        $input = $request->safe()->except(['expected_revision', 'idempotency_key', 'name']);
        $result = $this->transaction->execute(function () use ($trip, $request, $input) {
            $current = $trip->fresh();
            $this->checkRevision($current, (int) $request->expected_revision);

            return $this->simulate->execute($current, $input)['result'];
        });

        return response()->json(['revision' => (int) $request->expected_revision, 'result' => $result]);
    }

    public function store(ScenarioRequest $request, Trip $trip)
    {
        $input = $request->safe()->except(['expected_revision', 'idempotency_key', 'name']);
        $fingerprint = hash('sha256', json_encode(['name' => $request->name, 'revision' => (int) $request->expected_revision, 'input' => $input], JSON_THROW_ON_ERROR));
        $scenario = $this->transaction->execute(function () use ($request, $trip, $input, $fingerprint) {
            $existing = $trip->scenarios()->where('idempotency_key', $request->idempotency_key)->first();
            if ($existing) {
                if (! hash_equals($existing->input_hash, $fingerprint)) {
                    throw ValidationException::withMessages(['idempotency_key' => 'La clave pertenece a otra simulación. Genera una nueva vista previa.']);
                }

                return $existing;
            }
            $current = $trip->fresh();
            $this->checkRevision($current, (int) $request->expected_revision);
            $simulation = $this->simulate->execute($current, $input);

            return $current->scenarios()->create(['name' => $request->name, 'actor_id' => auth()->id(), 'idempotency_key' => $request->idempotency_key,
                'base_revision' => $current->revision, 'baseline_hash' => $simulation['baseline_hash'], 'input_hash' => $fingerprint,
                'input' => $input, 'result' => $simulation['result'], 'created_at' => now()]);
        });

        return response()->json(['scenario' => $scenario->load('actor:id,name')]);
    }

    public function show(Trip $trip, CostScenario $scenario)
    {
        $this->authorize('view', $trip);

        return response()->json(['scenario' => $scenario->load('actor:id,name')]);
    }

    public function apply(Request $request, Trip $trip, CostScenario $scenario)
    {
        $this->authorize('view', $trip);
        $request->validate(['expected_revision' => 'required|integer|min:1']);
        if ($scenario->applied_at) {
            return to_route('trips.show', $trip)->with('success', "El escenario ya se aplicó en la revisión {$scenario->applied_revision}; no se repitieron los cambios");
        }
        if ((int) $request->expected_revision !== $scenario->base_revision) {
            throw ValidationException::withMessages(['conflict' => 'El escenario corresponde a otra revisión. Genera una nueva simulación sobre la revisión actual.']);
        }
        $this->mutation->execute($trip, function ($current) use ($scenario) {
            // Every transaction retry needs fresh model state after a rollback.
            $saved = $current->scenarios()->whereKey($scenario->id)->lockForUpdate()->firstOrFail();
            $simulation = $this->simulate->execute($current, $saved->input);
            if (! hash_equals($saved->baseline_hash, $simulation['baseline_hash'])) {
                throw ValidationException::withMessages(['conflict' => 'Los datos de origen cambiaron fuera de esta revisión. Revisa y guarda un nuevo escenario antes de aplicar.']);
            }
            $candidate = $simulation['candidate'];
            $current->update(['tipo_cambio' => $candidate->tipo_cambio]);
            foreach (array_chunk($candidate->products->map->getAttributes()->all(), 30) as $chunk) {
                Product::upsert($chunk, ['id'], ['cantidad', 'costo_unitario', 'weight', 'volume', 'precio_venta']);
            }
            foreach (array_chunk($candidate->expenses->map->getAttributes()->all(), 30) as $chunk) {
                Expense::upsert($chunk, ['id'], ['monto', 'allocation_method', 'target_product_id']);
            }
            $pricing = $simulation['result']['pricing'];
            if ($pricing) {
                DB::table('pricing_batches')->insert(['trip_id' => $current->id, 'actor_id' => auth()->id(), 'revision' => $current->revision + 1,
                    'policy' => json_encode($pricing['policy'], JSON_THROW_ON_ERROR), 'result' => json_encode($pricing, JSON_THROW_ON_ERROR), 'created_at' => now()]);
            }
            $saved->update(['applied_revision' => $current->revision + 1, 'applied_at' => now()]);
        }, 'scenario_apply', expectedRevision: $scenario->base_revision);

        return to_route('trips.show', $trip)->with('success', 'Escenario aplicado como nueva revisión; la simulación original se conserva');
    }

    private function checkRevision(Trip $trip, int $expected): void
    {
        if ($trip->revision !== $expected) {
            throw ValidationException::withMessages(['conflict' => 'La importación cambió. Recarga y simula con la revisión vigente.']);
        }
    }
}
