<?php

namespace App\Modules\Costing\Actions;

use App\Models\CostRun;
use App\Models\Trip;
use App\Modules\Costing\Domain\CostResult;
use Illuminate\Support\Facades\DB;

final class RecordCostRun
{
    public function __construct(private ProjectCostRun $project) {}

    public function execute(Trip $trip, CostResult $result, string $reason): CostRun
    {
        return DB::transaction(function () use ($trip, $result, $reason) {
            $trip->load(['products', 'expenses.category', 'company', 'documents']);
            $snapshot = $trip->toArray();
            $input = ['currency' => $trip->moneda_trabajo, 'reference_rate' => (string) $trip->tipo_cambio,
                'products' => $trip->products->map->only(['id', 'nombre', 'cantidad', 'moneda', 'costo_unitario', 'weight', 'volume', 'rate_mode', 'applied_rate', 'rate_source', 'rate_date', 'precio_venta'])->all(),
                'expenses' => $trip->expenses->map->only(['id', 'concepto', 'moneda', 'monto', 'rate_mode', 'applied_rate', 'rate_source', 'rate_date', 'allocation_method', 'target_product_id'])->all()];
            $run = CostRun::create(['trip_id' => $trip->id, 'revision' => $trip->revision, 'algorithm' => $result->algorithm, 'reason' => $reason,
                'actor_id' => auth()->id(), 'input' => $input, 'result' => $result->toArray(), 'snapshot' => $snapshot,
                'input_hash' => hash('sha256', json_encode($input, JSON_THROW_ON_ERROR)), 'created_at' => now()]);
            if ($result->allocations) {
                foreach (array_chunk($result->allocations, 100) as $chunk) {
                    DB::table('cost_allocations')->insert(array_map(fn ($a) => [
                        'cost_run_id' => $run->id, 'expense_id' => $a['expense_id'], 'product_id' => $a['product_id'], 'amount' => $a['amount'], 'method' => $a['method'],
                        'trace' => isset($a['trace']) ? json_encode($a['trace'], JSON_THROW_ON_ERROR) : null], $chunk));
                }
            }
            $this->project->execute($run);

            return $run;
        });
    }
}
