<?php

namespace App\Modules\Costing\Reconciliation;

use App\Models\CostReconciliation;
use App\Models\Trip;
use App\Modules\Shared\TransactionRunner;
use Illuminate\Validation\ValidationException;

final class ReviewStoredCosts
{
    public function __construct(private CompareStoredCosts $compare, private PreserveLegacyCosts $preserve, private TransactionRunner $transaction) {}

    public function execute(Trip $trip, int $expectedRevision, ?string $expectedFingerprint = null): CostReconciliation
    {
        return $this->transaction->execute(function () use ($trip, $expectedRevision, $expectedFingerprint) {
            $current = Trip::whereKey($trip->id)->lockForUpdate()->firstOrFail();
            if ($current->revision !== $expectedRevision) {
                throw ValidationException::withMessages(['conflict' => 'La revisión cambió. Vuelve a comparar antes de guardar.']);
            }
            $review = $this->compare->execute($current);
            $data = $review['comparison'];
            if ($expectedFingerprint !== null && ! hash_equals($data['fingerprint'], $expectedFingerprint)) {
                throw ValidationException::withMessages(['conflict' => 'Los datos de origen cambiaron. Vuelve a comparar; no se guardó otra revisión.']);
            }
            $this->preserve->execute($current);

            return CostReconciliation::firstOrCreate(['trip_id' => $current->id, 'fingerprint' => $data['fingerprint']],
                ['actor_id' => auth()->id(), 'base_revision' => $current->revision, 'comparison' => $data, 'snapshot' => $review['snapshot'], 'created_at' => now()]);
        });
    }
}
