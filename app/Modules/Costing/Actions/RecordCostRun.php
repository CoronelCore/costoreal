<?php

namespace App\Modules\Costing\Actions;

use App\Models\CostRun;
use App\Models\Trip;
use App\Modules\Access\AccessContext;
use App\Modules\Costing\Domain\CostResult;
use Illuminate\Support\Facades\DB;

final class RecordCostRun
{
    public function __construct(private ProjectCostRun $project, private WriteAllocations $writer) {}

    public function execute(Trip $trip, CostResult $result, string $reason, ?AccessContext $context = null): CostRun
    {
        $context ??= AccessContext::current();
        $context->authorizeTrip($trip);

        return DB::transaction(function () use ($trip, $result, $reason, $context) {
            $trip->load([...$context->financialRelations(), 'documents']);
            $snapshot = $trip->toArray();
            $input = ['currency' => $trip->moneda_trabajo, 'reference_rate' => (string) $trip->tipo_cambio,
                'products' => $trip->products->map->only(['id', 'nombre', 'cantidad', 'moneda', 'costo_unitario', 'weight', 'volume', 'rate_mode', 'applied_rate', 'rate_source', 'rate_date', 'precio_venta'])->all(),
                'expenses' => $trip->expenses->map->only(['id', 'concepto', 'moneda', 'monto', 'rate_mode', 'applied_rate', 'rate_source', 'rate_date', 'allocation_method', 'target_product_id'])->all()];
            $run = CostRun::create(['trip_id' => $trip->id, 'revision' => $trip->revision, 'algorithm' => $result->algorithm, 'reason' => $reason,
                'actor_id' => $context->actorId(), 'input' => $input, 'result' => $result->storageData(), 'snapshot' => $snapshot,
                'input_hash' => hash('sha256', json_encode($input, JSON_THROW_ON_ERROR)), 'created_at' => now()]);
            $this->writer->execute($run->id, $result->allocations);
            $this->project->execute($run);

            return $run;
        });
    }
}
