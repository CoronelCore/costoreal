<?php

namespace App\Modules\Costing\Actions;

use App\Models\CostRun;
use Illuminate\Support\Facades\DB;

/** Derived read data; original financial records are never rewritten or recalculated. */
final class ProjectCostRun
{
    public function execute(CostRun $run): bool
    {
        return DB::transaction(function () use ($run) {
            DB::table('cost_runs')->where('id', $run->id)->lockForUpdate()->value('id');
            if (DB::table('cost_run_views')->where('cost_run_id', $run->id)->exists()) {
                return false;
            }
            $products = collect($run->snapshot['products'] ?? [])->keyBy('id');
            $inputs = collect($run->input['products'] ?? [])->keyBy('id');
            $rows = [];
            foreach ($run->result['lines'] as $line) {
                $source = [...($products[$line['id']] ?? []), ...($inputs[$line['id']] ?? [])];
                $currency = $source['moneda'] ?? $run->input['currency'];
                $mode = $source['rate_mode'] ?? 'estimated';
                $rate = $currency === 'CLP' ? '1' : ($mode === 'paid' ? ($source['applied_rate'] ?? null) : ($run->input['reference_rate'] ?? null));
                $data = ['id' => (int) $line['id'], 'name' => $source['nombre'] ?? "Producto {$line['id']}", 'quantity' => (int) $line['quantity'],
                    'purchase' => (string) $line['purchase'], 'assigned' => (string) $line['assigned'], 'total' => (string) $line['total'],
                    'unit_cost' => (string) $line['unit_cost'], 'currency' => $currency, 'rate_mode' => $mode,
                    'rate' => $rate === null ? null : (string) $rate, 'rate_source' => $source['rate_source'] ?? 'legacy-unverified', 'rate_date' => $source['rate_date'] ?? null];
                $rows[] = ['cost_run_id' => $run->id, 'product_id' => $line['id'], 'data' => json_encode($data, JSON_THROW_ON_ERROR)];
            }
            foreach (array_chunk($rows, 200) as $chunk) {
                DB::table('cost_run_lines')->insert($chunk);
            }
            $expenses = collect($run->snapshot['expenses'] ?? [])->keyBy('id');
            $expenses = collect($run->input['expenses'] ?? [])->keyBy('id')->union($expenses);
            foreach (array_chunk($expenses->map(fn ($expense) => ['cost_run_id' => $run->id, 'expense_id' => $expense['id'], 'name' => $expense['concepto'] ?? "Gasto {$expense['id']}"])->values()->all(), 200) as $chunk) {
                DB::table('cost_run_expenses')->insert($chunk);
            }
            $summary = array_intersect_key($run->result, array_flip(['currency', 'algorithm', 'purchase', 'expenses', 'assigned', 'unassigned', 'total', 'allocated_total']));
            DB::table('cost_run_views')->insert(['cost_run_id' => $run->id, 'summary' => json_encode($summary, JSON_THROW_ON_ERROR)]);

            return true;
        });
    }
}
