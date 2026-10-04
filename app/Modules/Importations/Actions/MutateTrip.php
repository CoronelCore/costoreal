<?php

namespace App\Modules\Importations\Actions;

use App\Models\AuditEvent;
use App\Models\Trip;
use App\Modules\Costing\Actions\RecordCostRun;
use App\Modules\Shared\TransactionRunner;
use App\Services\ProrrateoService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class MutateTrip
{
    public function __construct(private ProrrateoService $costing, private RecordCostRun $recorder, private TransactionRunner $transaction) {}

    public function execute(Trip $trip, callable $mutation, string $action = 'update', ?int $expectedRevision = null, string $ability = 'update', ?callable $verify = null): mixed
    {
        $operation = function () use ($trip, $mutation, $action, $expectedRevision, $ability, $verify) {
            $current = Trip::whereKey($trip->id)->lockForUpdate()->firstOrFail();
            Gate::authorize($ability, $current);
            if (request()->has('expected_revision')) {
                request()->validate(['expected_revision' => 'required|integer|min:1']);
            }
            $expectedRevision ??= request()->has('expected_revision') ? (int) request('expected_revision') : null;
            if ($expectedRevision !== null && $expectedRevision !== $current->revision) {
                throw ValidationException::withMessages(['conflict' => 'Esta importación cambió en otra pestaña. Recarga para revisar la nueva versión; tus datos no se han guardado.']);
            }
            $nextRevision = $current->revision + 1;
            // Compare-and-swap claims the revision in the same transaction before child writes.
            $claimed = Trip::whereKey($current->id)->where('revision', $current->revision)->update(['revision' => $nextRevision]);
            if ($claimed !== 1) {
                throw ValidationException::withMessages(['conflict' => 'La revisión cambió. Recarga antes de volver a guardar.']);
            }
            $before = $current->load(['products', 'expenses', 'documents'])->toArray();
            $result = $mutation($current);
            $cost = $this->costing->calcularProrrateo($current);
            if ($verify) {
                $verify($current, $cost);
            }
            $current->revision = $nextRevision;
            $current->syncOriginalAttribute('revision');
            $current->updated_at = Trip::whereKey($current->id)->value('updated_at');
            $current->syncOriginalAttribute('updated_at');
            $this->recorder->execute($current, $cost, $action);
            AuditEvent::create(['trip_id' => $current->id, 'actor_id' => auth()->id(), 'action' => $action, 'revision' => $current->revision,
                'before' => $before, 'after' => $current->toArray(), 'created_at' => now()]);

            return $result;
        };

        return $this->transaction->execute($operation);
    }
}
