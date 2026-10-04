<?php

namespace App\Http\Controllers;

use App\Http\Requests\LaunchImportDraftRequest;
use App\Models\ExpenseCategory;
use App\Models\ImportDraft;
use App\Modules\Access\AccessContext;
use App\Modules\Importations\Actions\CloseImportation;
use App\Modules\Importations\Actions\CreateImportation;
use App\Modules\Importations\Creation\DraftPayload;
use App\Modules\Importations\Data\ImportationData;
use App\Modules\Reporting\FinancialSummaryData;
use App\Modules\Shared\TransactionRunner;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ImportDraftController extends Controller
{
    public function __construct(private DraftPayload $payload, private TransactionRunner $transaction) {}

    public function create(Request $request)
    {
        $request->validate(['draft' => 'nullable|uuid']);
        $draft = $request->filled('draft') ? ImportDraft::where('user_id', $request->user()->id)->findOrFail($request->query('draft')) : null;
        if ($draft?->completed_at) {
            return to_route('trips.show', $draft->trip_id);
        }
        $trip = $draft?->trip;
        if ($trip) {
            $this->authorize('view', $trip);
            $trip->load(['products', 'expenses.category', 'documents', 'company']);
        }
        $summary = $trip ? FinancialSummaryData::fromTrip($trip) : null;
        $context = AccessContext::forRequest($request);
        $drafts = ImportDraft::where('user_id', $request->user()->id)->whereNull('completed_at')->latest('updated_at')->paginate(10, ['id', 'trip_id', 'step', 'payload', 'updated_at'], 'draft_page')
            ->through(fn ($item) => ['id' => $item->id, 'trip_id' => $item->trip_id, 'step' => $item->step, 'name' => $item->payload['context']['nombre'] ?? 'Sin nombre todavía', 'updated_at' => $item->updated_at->toISOString()]);

        return Inertia::render('Trips/Create', ['draft' => $draft ? $this->payload->data($draft) : null, 'drafts' => $drafts,
            'companies' => $context->companies()->map->only(['id', 'nombre']), 'selectedCompanyId' => $context->activeCompany()?->id,
            'trip' => $trip ? ImportationData::detail($trip, $summary) : null, 'estadisticas' => $summary?->statistics, 'projection' => $summary?->projection,
            'latestCostRun' => $trip?->costRuns()->select('id', 'revision', 'reason', 'algorithm', 'created_at')->latest('id')->first(),
            'categories' => ExpenseCategory::visibleTo($request->user()->id)->orderBy('nombre')->get(['id', 'nombre'])]);
    }

    public function store(Request $request)
    {
        $input = $request->validate(['draft_id' => 'required|uuid', 'write_key' => 'required|uuid', 'payload' => 'required|array']);
        $data = $this->payload->validate($input, $request->user());
        $hash = $this->hash(['step' => 'contexto', 'payload' => $data]);
        $draft = $this->transaction->execute(function () use ($request, $input, $data, $hash) {
            $existing = ImportDraft::whereKey($input['draft_id'])->lockForUpdate()->first();
            if ($existing) {
                abort_unless($existing->user_id === $request->user()->id, 403);
                if (! hash_equals($existing->last_write_hash, $hash) || $existing->last_write_key !== $input['write_key']) {
                    throw ValidationException::withMessages(['conflict' => 'Este borrador ya existe con otros cambios. Abre su versión guardada antes de continuar.']);
                }

                return $existing;
            }

            return ImportDraft::create(['id' => $input['draft_id'], 'user_id' => $request->user()->id, 'step' => 'contexto', 'revision' => 1, 'payload' => $data, 'last_write_key' => $input['write_key'], 'last_write_hash' => $hash]);
        });

        return response()->json(['draft' => $this->payload->data($draft)]);
    }

    public function update(Request $request, ImportDraft $draft)
    {
        abort_unless($draft->user_id === $request->user()->id, 403);
        $input = $request->validate(['expected_revision' => 'required|integer|min:1', 'write_key' => 'required|uuid', 'step' => 'required|in:contexto,productos,gastos,revision', 'payload' => 'required|array']);
        $data = $this->payload->validate($input, $request->user(), $draft);
        $hash = $this->hash(['step' => $input['step'], 'payload' => $data]);
        $current = $this->transaction->execute(function () use ($draft, $input, $data, $hash) {
            $current = ImportDraft::whereKey($draft->id)->lockForUpdate()->firstOrFail();
            if ($current->last_write_key === $input['write_key']) {
                if (! hash_equals($hash, $current->last_write_hash)) {
                    throw ValidationException::withMessages(['write_key' => 'La clave corresponde a otros cambios. Genera un nuevo guardado.']);
                }

                return $current;
            }
            $this->revision($current, $input['expected_revision']);
            if ($current->completed_at || (! $current->trip_id && $input['step'] !== 'contexto')) {
                throw ValidationException::withMessages(['step' => 'Completa el contexto antes de avanzar o abre la importación ya creada.']);
            }
            if ($current->trip_id) {
                $trip = $current->trip()->firstOrFail();
                $this->authorize('update', $trip);
            }
            $claimed = ImportDraft::whereKey($current->id)->where('revision', $current->revision)->update(['payload' => $data, 'step' => $input['step'], 'revision' => $current->revision + 1, 'last_write_key' => $input['write_key'], 'last_write_hash' => $hash, 'updated_at' => now()]);
            if ($claimed !== 1) {
                throw ValidationException::withMessages(['conflict' => 'El borrador cambió en otra pestaña. Abre su versión guardada; tus campos locales se conservan.']);
            }

            return $current->fresh();
        });

        return response()->json(['draft' => $this->payload->data($current)]);
    }

    public function launch(LaunchImportDraftRequest $request, ImportDraft $draft, CreateImportation $create)
    {
        abort_unless($draft->user_id === $request->user()->id, 403);
        $input = $request->validated();
        $context = array_intersect_key($input, array_flip(['nombre', 'descripcion', 'company_id', 'fecha_inicio', 'fecha_fin', 'moneda_trabajo', 'tipo_cambio']));
        $hash = $this->hash(['context' => $context, 'mode' => $input['mode']]);
        $current = $this->transaction->execute(function () use ($draft, $input, $context, $hash, $create, $request) {
            $current = ImportDraft::whereKey($draft->id)->lockForUpdate()->firstOrFail();
            if ($current->trip_id) {
                if (! hash_equals($current->launch_hash, $hash)) {
                    throw ValidationException::withMessages(['conflict' => 'El borrador ya tiene una importación con otro contexto. Retoma la versión guardada.']);
                }

                return $current;
            }
            $this->revision($current, $input['draft_revision']);
            $trip = $create->execute($request->user(), $context);
            $current->update(['trip_id' => $trip->id, 'step' => 'productos', 'payload' => [...$current->payload, 'context' => $context], 'revision' => $current->revision + 1, 'launch_hash' => $hash, 'completed_at' => $input['mode'] === 'quick' ? now() : null]);

            return $current;
        });

        return $input['mode'] === 'quick' ? redirect()->to(route('trips.show', $current->trip_id).'#productos') : to_route('trips.create', ['draft' => $current->id])->with('success', 'Contexto guardado. Continúa con los productos; puedes retomar este borrador después.');
    }

    public function finish(Request $request, ImportDraft $draft, CloseImportation $close)
    {
        abort_unless($draft->user_id === $request->user()->id, 403);
        $input = $request->validate(['draft_revision' => 'required|integer|min:1', 'expected_revision' => 'required|integer|min:1', 'mode' => 'required|in:active,close']);
        $trip = $this->transaction->execute(function () use ($draft, $input, $close) {
            $current = ImportDraft::whereKey($draft->id)->lockForUpdate()->firstOrFail();
            $trip = $current->trip()->lockForUpdate()->firstOrFail();
            $this->authorize('view', $trip);
            if ($current->completed_at) {
                return $trip;
            }
            $this->revision($current, $input['draft_revision']);
            $this->authorize('update', $trip);
            if ($trip->revision !== (int) $input['expected_revision']) {
                throw ValidationException::withMessages(['conflict' => 'La importación cambió. Recarga sus costos antes de terminar.']);
            }
            if (($current->payload['product'] ?? null) || ($current->payload['expense'] ?? null)) {
                throw ValidationException::withMessages(['draft' => 'Guarda o cancela los editores pendientes antes de terminar el alta.']);
            }
            if ($input['mode'] === 'close') {
                $close->execute($trip, $trip->revision);
            }
            $current->update(['step' => 'revision', 'revision' => $current->revision + 1, 'completed_at' => now(), 'payload' => ['context' => $current->payload['context'], 'product' => null, 'expense' => null]]);

            return $trip;
        });

        return to_route('trips.show', $trip)->with('success', $trip->fresh()->estado === 'finalizado' ? 'Importación creada y cerrada; revisión conservada.' : 'Alta completada. La importación queda en curso para seguir trabajando.');
    }

    private function revision(ImportDraft $draft, int $expected): void
    {
        if ($draft->revision !== $expected) {
            throw ValidationException::withMessages(['conflict' => 'El borrador cambió en otra pestaña. Abre la versión guardada; los campos locales se conservan.']);
        }
    }

    private function hash(array $data): string
    {
        return hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
    }
}
