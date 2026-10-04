<?php

namespace App\Modules\Reporting;

use App\Models\Company;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Product;
use App\Models\Trip;
use Illuminate\Support\Collection;

final class ReportTrip
{
    public function resolve(Trip $trip, ?int $runId = null): Trip
    {
        $query = $trip->costRuns();
        $run = $runId ? $query->findOrFail($runId) : ($trip->estado === 'finalizado' ? $query->where('reason', 'close')->latest('id')->first() : null);
        if (! $run) {
            return $trip->load(['expenses.category', 'products', 'company']);
        }
        $s = $run->snapshot;
        $attrs = $s;
        unset($attrs['products'],$attrs['expenses'],$attrs['company'],$attrs['costo_total']);
        $historical = (new Trip)->newFromBuilder($attrs);
        $historical->setRelation('products', new Collection(array_map(fn ($p) => (new Product)->newFromBuilder($p), $s['products'])));
        $historical->setRelation('expenses', new Collection(array_map(function ($e) {
            $category = $e['category'] ?? null;
            unset($e['category']);
            $expense = (new Expense)->newFromBuilder($e);
            $expense->setRelation('category', $category ? (new ExpenseCategory)->newFromBuilder($category) : null);

            return $expense;
        }, $s['expenses'])));
        $historical->setRelation('company', ($s['company'] ?? null) ? (new Company)->newFromBuilder($s['company']) : null);

        return $historical;
    }
}
