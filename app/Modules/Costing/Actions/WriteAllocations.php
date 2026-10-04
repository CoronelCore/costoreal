<?php

namespace App\Modules\Costing\Actions;

use App\Modules\Costing\Domain\AllocationSequence;
use App\Modules\Shared\InsertBatchSize;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/** Complete trace rows with standard Laravel transactions, query events, and error handling. */
final class WriteAllocations
{
    public function __construct(private InsertBatchSize $batches) {}

    public function execute(int $runId, array|AllocationSequence $allocations): void
    {
        if (! count($allocations)) {
            return;
        }
        if (DB::getDriverName() === 'sqlite' && $allocations instanceof AllocationSequence && $this->jsonbAvailable()) {
            foreach ($allocations->jsonBatches() as $json) {
                DB::insert(<<<'SQL'
                    insert into "cost_allocations" ("cost_run_id", "expense_id", "product_id", "amount", "method", "trace")
                    with items as materialized (select jsonb(value) as data from json_each(?))
                    select ?, jsonb_extract(data, '$.expense_id'), jsonb_extract(data, '$.product_id'),
                        jsonb_extract(data, '$.amount'), jsonb_extract(data, '$.method'), json_extract(data, '$.trace')
                    from items
                    SQL, [$json, $runId]);
            }

            return;
        }
        $this->writePortable($runId, $allocations);
    }

    public function writePortable(int $runId, array|AllocationSequence $allocations): void
    {
        $rows = [];
        $size = $this->batches->forColumns(6);
        foreach ($allocations as $allocation) {
            $rows[] = ['cost_run_id' => $runId, 'expense_id' => $allocation['expense_id'],
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
    }

    private function jsonbAvailable(): bool
    {
        try {
            return DB::selectOne("select typeof(jsonb('[]')) as kind")?->kind === 'blob';
        } catch (QueryException $error) {
            if (! str_contains($error->getPrevious()?->getMessage() ?? '', 'no such function: jsonb')) {
                throw $error;
            }

            return false;
        }
    }
}
