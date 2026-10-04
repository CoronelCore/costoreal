<?php

namespace App\Http\Controllers;

use App\Models\CostReconciliation;
use App\Models\Trip;
use App\Modules\Access\AccessContext;
use App\Modules\Costing\Reconciliation\CompareStoredCosts;
use App\Modules\Costing\Reconciliation\ReviewStoredCosts;
use App\Modules\Importations\Actions\MutateTrip;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CostReconciliationController extends Controller
{
    public function __construct(private CompareStoredCosts $compare, private ReviewStoredCosts $review, private MutateTrip $mutation) {}

    public function preview(Request $request, Trip $trip)
    {
        $this->authorize('view', $trip);
        $request->validate(['page' => 'nullable|integer|min:1']);
        $data = DB::transaction(function () use ($trip) {
            $current = $trip->fresh();
            $this->authorize('view', $current);

            return $this->compare->execute($current)['comparison'];
        });

        return response()->json($this->page($data, $request));
    }

    public function index(Request $request, Trip $trip)
    {
        $this->authorize('view', $trip);
        $request->validate(['page' => 'nullable|integer|min:1']);

        return response()->json(CostReconciliation::where('trip_id', $trip->id)->select('id', 'base_revision', 'applied_revision', 'applied_at', 'created_at')->latest('id')->paginate(20));
    }

    public function store(Request $request, Trip $trip)
    {
        $this->authorize('view', $trip);
        $input = $request->validate(['expected_revision' => 'required|integer|min:1', 'fingerprint' => 'required|string|size:64']);
        $saved = $this->review->execute($trip, (int) $input['expected_revision'], $input['fingerprint'], AccessContext::forRequest($request));

        return response()->json(['id' => $saved->id, ...$this->page($saved->comparison, $request), 'applied_revision' => $saved->applied_revision]);
    }

    public function show(Request $request, Trip $trip, string $comparisonId)
    {
        $this->authorize('view', $trip);
        $request->validate(['page' => 'nullable|integer|min:1']);
        $saved = CostReconciliation::where('trip_id', $trip->id)->findOrFail($comparisonId);

        return response()->json(['id' => $saved->id, ...$this->page($saved->comparison, $request), 'applied_revision' => $saved->applied_revision]);
    }

    public function apply(Request $request, Trip $trip, string $comparisonId)
    {
        $this->authorize('update', $trip);
        $input = $request->validate(['expected_revision' => 'required|integer|min:1', 'acknowledged_keys' => 'required|array|min:1|max:4500', 'acknowledged_keys.*' => 'required|string|max:100|distinct']);
        $saved = CostReconciliation::where('trip_id', $trip->id)->findOrFail($comparisonId);
        if ($saved->applied_at) {
            return to_route('trips.show', $trip)->with('success', 'La comparación ya se aplicó; no se repitieron cambios.');
        }
        if ((int) $input['expected_revision'] !== $saved->base_revision) {
            throw ValidationException::withMessages(['conflict' => 'Esta comparación pertenece a otra revisión. Vuelve a comparar.']);
        }
        $this->mutation->execute($trip, function ($current) use ($saved, $input) {
            $record = CostReconciliation::where('trip_id', $current->id)->whereKey($saved->id)->lockForUpdate()->firstOrFail();
            $comparison = $record->comparison;
            $fresh = $this->compare->execute($current)['comparison'];
            if (! hash_equals($record->fingerprint, $fresh['fingerprint']) || $comparison !== $fresh) {
                throw ValidationException::withMessages(['conflict' => 'Cambió el origen o la política de cálculo. Guarda una nueva comparación.']);
            }
            if ($comparison['errors'] || ! $comparison['differences']) {
                throw ValidationException::withMessages(['comparison' => 'Corrige los errores de origen antes de conciliar. Una comparación sin diferencias no requiere aplicación.']);
            }
            $keys = array_column($comparison['differences'], 'key');
            sort($keys);
            $checked = $input['acknowledged_keys'];
            sort($checked);
            if ($keys !== $checked) {
                throw ValidationException::withMessages(['acknowledged_keys' => 'Revisa y marca cada diferencia antes de aplicar la conciliación completa.']);
            }
            $record->update(['applied_revision' => $current->revision + 1, 'applied_at' => now()]);
        }, 'cost_reconcile', expectedRevision: $saved->base_revision);

        return to_route('trips.show', $trip)->with('success', 'Costos conciliados como nueva revisión; los importes originales permanecen en el historial.');
    }

    private function page(array $comparison, Request $request): array
    {
        $page = max(1, $request->integer('page', 1));
        $rows = $comparison['differences'];
        $warnings = $comparison['warnings'];
        unset($comparison['differences'], $comparison['warnings']);

        return [...$comparison, 'warnings' => array_slice($warnings, 0, 20), 'warnings_count' => count($warnings), 'differences' => ['data' => array_slice($rows, ($page - 1) * 20, 20), 'total' => count($rows), 'current_page' => $page, 'last_page' => max(1, (int) ceil(count($rows) / 20))]];
    }
}
