<?php

namespace App\Modules\Costing\Reconciliation;

use App\Models\CostReconciliation;
use App\Models\Trip;
use App\Modules\Access\AccessContext;
use App\Modules\Shared\TransactionRunner;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

final class ReviewStoredCosts
{
    public function __construct(private CompareStoredCosts $compare, private PreserveLegacyCosts $preserve, private TransactionRunner $transaction) {}

    public function execute(Trip $trip, int $expectedRevision, ?string $expectedFingerprint = null, ?AccessContext $context = null): CostReconciliation
    {
        $context ??= AccessContext::current();
        $context->authorizeTrip($trip);

        return $this->record($trip, $expectedRevision, $expectedFingerprint, $context);
    }

    /** Privileged, local maintenance only: never use this entry point for user jobs or HTTP. */
    public function executeForMaintenance(Trip $trip, int $expectedRevision): CostReconciliation
    {
        if (! app()->runningInConsole()) {
            throw new AuthorizationException('Esta operación requiere mantenimiento local.');
        }

        return $this->record($trip, $expectedRevision, null, null);
    }

    private function record(Trip $trip, int $expectedRevision, ?string $expectedFingerprint, ?AccessContext $context): CostReconciliation
    {
        return $this->transaction->execute(function () use ($trip, $expectedRevision, $expectedFingerprint, $context) {
            $current = Trip::whereKey($trip->id)->lockForUpdate()->firstOrFail();
            $context?->authorizeTrip($current);
            if ($current->revision !== $expectedRevision) {
                throw ValidationException::withMessages(['conflict' => 'La revisión cambió. Vuelve a comparar antes de guardar.']);
            }
            $review = $this->compare->execute($current);
            $data = $review['comparison'];
            if ($expectedFingerprint !== null && ! hash_equals($data['fingerprint'], $expectedFingerprint)) {
                throw ValidationException::withMessages(['conflict' => 'Los datos de origen cambiaron. Vuelve a comparar; no se guardó otra revisión.']);
            }
            $this->preserve->execute($current, $context);

            return CostReconciliation::firstOrCreate(['trip_id' => $current->id, 'fingerprint' => $data['fingerprint']],
                ['actor_id' => $context?->actorId(), 'base_revision' => $current->revision, 'comparison' => $data, 'snapshot' => $review['snapshot'], 'created_at' => now()]);
        });
    }
}
