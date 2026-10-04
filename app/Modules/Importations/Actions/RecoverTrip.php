<?php

namespace App\Modules\Importations\Actions;

use App\Models\AuditEvent;
use App\Models\Trip;
use App\Modules\Access\AccessContext;
use App\Modules\Shared\TransactionRunner;
use Illuminate\Validation\ValidationException;

final class RecoverTrip
{
    public function __construct(private TransactionRunner $transaction) {}

    public function execute(Trip $trip, int $expectedRevision, bool $restore, ?AccessContext $context = null): void
    {
        $context ??= AccessContext::current();
        $context->actorId();
        $this->transaction->execute(function () use ($trip, $expectedRevision, $restore, $context) {
            $current = Trip::withTrashed()->whereKey($trip->id)->lockForUpdate()->firstOrFail();
            $context->authorizeTrip($current, $restore ? 'restore' : 'delete');
            if ($current->revision !== $expectedRevision) {
                throw ValidationException::withMessages(['conflict' => 'La importación cambió. Recarga para revisar su estado antes de moverla.']);
            }
            $before = $current->load(['products', 'expenses', 'documents'])->toArray();
            $claimed = Trip::withTrashed()->whereKey($current->id)->where('revision', $expectedRevision)
                ->update(['revision' => $expectedRevision + 1]);
            if ($claimed !== 1) {
                throw ValidationException::withMessages(['conflict' => 'La revisión cambió. Recarga antes de volver a intentar.']);
            }
            $current->revision = $expectedRevision + 1;
            $current->syncOriginalAttribute('revision');
            $restore ? $current->restore() : $current->delete();
            // Recovery preserves the recorded amounts, exchange rates and closed state.
            // It must not recalculate a historical import under today's costing policy.
            AuditEvent::create(['trip_id' => $current->id, 'actor_id' => $context->actorId(),
                'action' => $restore ? 'trip_restore' : 'trip_trash', 'revision' => $current->revision,
                'before' => $before, 'after' => $current->toArray(), 'created_at' => now()]);
        });
    }
}
