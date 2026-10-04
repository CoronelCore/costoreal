<?php

namespace App\Modules\Reporting;

use App\Models\Company;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\LegacyCostBaseline;
use App\Models\Product;
use App\Models\Trip;
use App\Modules\Access\AccessContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

final class ReportTrip
{
    public function resolve(Trip $trip, ?int $runId = null, ?int $baselineId = null, ?AccessContext $context = null): Trip
    {
        $context ??= new AccessContext(auth()->user(), null);
        Gate::forUser($context->user)->authorize('view', $trip);
        $baseline = $baselineId ? LegacyCostBaseline::where('trip_id', $trip->id)->findOrFail($baselineId) : null;
        $query = $trip->costRuns();
        $run = $runId ? $query->findOrFail($runId) : ($trip->estado === 'finalizado' ? $query->where('reason', 'close')->latest('id')->first() : null);
        if (! $run && ! $baseline && $trip->estado === 'finalizado') {
            $candidate = LegacyCostBaseline::where('trip_id', $trip->id)->first();
            if (($candidate?->snapshot['estado'] ?? null) === 'finalizado') {
                $baseline = $candidate;
            }
        }
        if (! $run && ! $baseline) {
            return $trip->load($context->financialRelations());
        }
        $s = $baseline ? $baseline->snapshot : $run->snapshot;
        $attrs = $s;
        unset($attrs['products'],$attrs['expenses'],$attrs['company'],$attrs['costo_total']);
        $historical = (new Trip)->newFromBuilder($attrs);
        if ($baseline) {
            $historical->report_provenance = 'Copia heredada · importes originales; algoritmo y tasas históricas sin verificar';
        }
        $historical->setRelation('products', new Collection(array_map(fn ($p) => (new Product)->newFromBuilder($p), $s['products'])));
        $historical->setRelation('expenses', new Collection(array_map(function ($e) use ($context) {
            $category = $e['category'] ?? null;
            unset($e['category']);
            $expense = (new Expense)->newFromBuilder($e);
            $expense->setRelation('category', $context->seesCategoryMetadata($category) ? (new ExpenseCategory)->newFromBuilder($category) : null);

            return $expense;
        }, $s['expenses'])));
        $historical->setRelation('company', $context->ownsMetadata($s['company'] ?? null) ? (new Company)->newFromBuilder($s['company']) : null);

        return $historical;
    }
}
