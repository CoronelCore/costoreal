<?php

namespace App\Modules\Scenarios;

use App\Models\Trip;
use App\Modules\Access\AccessContext;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

final class ListScenarios
{
    public function read(AccessContext $context, Trip $trip, string $pageName = 'page'): LengthAwarePaginator
    {
        Gate::forUser($context->user)->authorize('view', $trip);

        return $trip->scenarios()->select('id', 'trip_id', 'actor_id', 'name', 'base_revision', 'applied_revision', 'applied_at', 'created_at')
            ->with('actor:id,name')->latest('id')->paginate(20, ['*'], $pageName)->through(fn ($scenario) => ScenarioData::summary($scenario));
    }
}
