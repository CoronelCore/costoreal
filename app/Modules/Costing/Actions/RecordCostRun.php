<?php

namespace App\Modules\Costing\Actions;

use App\Models\CostRun;
use App\Models\Trip;
use App\Modules\Costing\Domain\CostResult;
use App\Modules\Shared\InsertBatchSize;
use Illuminate\Support\Facades\DB;

final class RecordCostRun
{
    public function __construct(private ProjectCostRun $project, private InsertBatchSize $batches) {}

    public function execute(Trip $trip, CostResult $result, string $reason): CostRun
    {
        return DB::transaction(function () use ($trip, $result, $reason) {
            $trip->load(['products', 'expenses.category', 'company', 'documents']);
            $snapshot = $trip->toArray();
            $input = ['currency' => $trip->moneda_trabajo, 'reference_rate' => (string) $trip->tipo_cambio,
                'products' => $trip->products->map->only(['id', 'nombre', 'cantidad', 'moneda', 'costo_unitario', 'weight', 'volume', 'rate_mode', 'applied_rate', 'rate_source', 'rate_date', 'precio_venta'])->all(),
                'expenses' => $trip->expenses->map->only(['id', 'concepto', 'moneda', 'monto', 'rate_mode', 'applied_rate', 'rate_source', 'rate_date', 'allocation_method', 'target_product_id'])->all()];
            $run = CostRun::create(['trip_id' => $trip->id, 'revision' => $trip->revision, 'algorithm' => $result->algorithm, 'reason' => $reason,
                'actor_id' => auth()->id(), 'input' => $input, 'result' => $result->storageData(), 'snapshot' => $snapshot,
                'input_hash' => hash('sha256', json_encode($input, JSON_THROW_ON_ERROR)), 'created_at' => now()]);
            $rows = [];
            $size = count($result->allocations) ? $this->batches->forColumns(6) : 1;
            foreach ($result->allocations as $allocation) {
                $rows[] = ['cost_run_id' => $run->id, 'expense_id' => $allocation['expense_id'],
                    'product_id' => $allocation['product_id'], 'amount' => $allocation['amount'], 'method' => $allocation['method'],
                    'trace' => isset($allocation['trace']) ? json_encode($allocation['trace'], JSON_THROW_ON_ERROR) : null];
                if (count($rows) === $size) {
                    DB::table('cost_allocations')->insert($rows);
                    $rows = [];
                }
            }
            if ($rows) {
                DB::table('cost_allocations')->insert($rows);
            }
            $this->project->execute($run);

            return $run;
        });
    }
}
