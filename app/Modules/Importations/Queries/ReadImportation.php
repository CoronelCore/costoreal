<?php

namespace App\Modules\Importations\Queries;

use App\Models\ExpenseCategory;
use App\Models\LegacyCostBaseline;
use App\Models\Trip;
use App\Modules\Access\AccessContext;
use App\Modules\Audit\ListAuditEvents;
use App\Modules\Importations\Data\ImportationData;
use App\Modules\Pricing\Data\PricingData;
use App\Modules\Reporting\FinancialSummaryData;
use App\Modules\Scenarios\ListScenarios;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class ReadImportation
{
    public function read(AccessContext $context, Trip $trip, array $filters): array
    {
        Gate::forUser($context->user)->authorize('view', $trip);
        $trip->load([...$context->financialRelations(), 'documents']);

        $summary = FinancialSummaryData::fromTrip($trip);

        $batch = DB::table('pricing_batches')->where('trip_id', $trip->id)->latest('id')->first();
        $lastBatch = $batch ? ['revision' => (int) $batch->revision, 'policy' => PricingData::policy(json_decode($batch->policy, true, flags: JSON_THROW_ON_ERROR)),
            'lines_count' => count(json_decode($batch->result, true, flags: JSON_THROW_ON_ERROR)['lines'])] : null;
        $latestRun = $trip->costRuns()->select('id', 'revision', 'reason', 'algorithm', 'created_at')->latest('id')->first();

        return ['initialTab' => $filters['section'] ?? null, 'trip' => ImportationData::detail($trip, $summary), 'lastPricingBatch' => $lastBatch, 'projection' => $summary->projection, 'estadisticas' => $summary->statistics,
            'scenarios' => fn () => app(ListScenarios::class)->read($context, $trip, 'scenario_page'),
            'trashedDocuments' => $trip->documents()->onlyTrashed()->latest()->get()->map(fn ($d) => ImportationData::document($d)),
            'history' => fn () => app(ListAuditEvents::class)->read($context, $trip, 'history_page'),
            'latestCostRun' => $latestRun,
            'legacyBaseline' => LegacyCostBaseline::where('trip_id', $trip->id)->first(['id', 'revision', 'created_at']),
            'categories' => ExpenseCategory::visibleTo($context->user->id)->orderBy('nombre')->get(['id', 'nombre'])];
    }
}
