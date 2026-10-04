<?php

namespace App\Modules\Costing\Reconciliation;

use App\Models\LegacyCostBaseline;
use App\Models\Trip;
use App\Modules\Access\AccessContext;

/** Called inside a locked transaction, before the first financial mutation. */
final class PreserveLegacyCosts
{
    public function __construct(private CompareStoredCosts $compare) {}

    public function execute(Trip $trip, ?AccessContext $context = null): ?LegacyCostBaseline
    {
        $existing = LegacyCostBaseline::where('trip_id', $trip->id)->first();
        if ($existing) {
            return $existing;
        }
        if ($trip->costRuns()->exists() || (! $trip->products()->exists() && ! $trip->expenses()->exists())) {
            return null;
        }
        $snapshot = $this->compare->snapshot($trip);

        return LegacyCostBaseline::create(['trip_id' => $trip->id, 'actor_id' => $context?->actorId(), 'revision' => $trip->revision,
            'fingerprint' => $this->compare->fingerprint($snapshot), 'snapshot' => $snapshot, 'created_at' => now()]);
    }
}
