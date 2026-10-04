<?php

namespace App\Modules\Audit;

use App\Models\Trip;
use App\Modules\Access\AccessContext;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

final class ListAuditEvents
{
    public function read(AccessContext $context, Trip $trip, string $pageName = 'page'): LengthAwarePaginator
    {
        Gate::forUser($context->user)->authorize('view', $trip);

        return $trip->auditEvents()->select('id', 'trip_id', 'actor_id', 'action', 'revision', 'summary', 'reason', 'created_at')
            ->with('actor:id,name')->latest('id')->paginate(20, pageName: $pageName)->through(fn ($event) => AuditEventData::summary($event));
    }
}
