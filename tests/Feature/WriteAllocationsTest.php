<?php

namespace Tests\Feature;

use App\Models\CostRun;
use App\Models\Trip;
use App\Models\User;
use App\Modules\Costing\Actions\WriteAllocations;
use App\Modules\Costing\Domain\AllocationBuffer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WriteAllocationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_native_and_portable_transport_preserve_decimal_strings_json_types_unicode_and_absent_trace(): void
    {
        $trip = Trip::create(['user_id' => User::factory()->create()->id, 'nombre' => 'Transport fixture',
            'moneda_base' => 'CLP', 'moneda_trabajo' => 'CLP', 'tipo_cambio' => '1', 'estado' => 'activo']);
        $rows = [['expense_id' => 1, 'product_id' => 2, 'amount' => '9000000000000000000000000000000000000000', 'method' => 'weight',
            'trace' => ['version' => 1, 'basis' => '1.25000000', 'floor' => '90000000000000000000',
                'residual_award' => 0, 'label' => 'Ámbito "literal" \\ original']],
            ['expense_id' => 2, 'product_id' => 2, 'amount' => '0', 'method' => 'direct']];
        $buffer = new AllocationBuffer;
        foreach ($rows as $row) {
            $buffer->add($row);
        }
        $sequence = $buffer->finish();
        foreach (['execute', 'legacyJsonb', 'writePortable'] as $method) {
            $run = CostRun::create(['trip_id' => $trip->id, 'revision' => 1, 'algorithm' => 'transport-fixture',
                'reason' => 'codec-test', 'actor_id' => $trip->user_id, 'input' => [], 'result' => [], 'snapshot' => [],
                'input_hash' => hash('sha256', 'fixture'), 'created_at' => now()]);
            $manager = DB::getFacadeRoot();
            if ($method === 'legacyJsonb') {
                $proxy = \Mockery::mock($manager);
                DB::swap($proxy);
                $proxy->shouldReceive('selectOne')
                    ->with("select sqlite_version() as version, typeof(jsonb('[]')) as kind")
                    ->once()->andReturn((object) ['version' => '3.50.0', 'kind' => 'blob']);
            }
            try {
                $writer = $method === 'legacyJsonb' ? 'execute' : $method;
                app(WriteAllocations::class)->{$writer}($run->id, $sequence);
            } finally {
                DB::swap($manager);
            }
            $stored = DB::table('cost_allocations')->where('cost_run_id', $run->id)->orderBy('id')->get();
            $actual = $stored->map(fn ($row) => ['expense_id' => $row->expense_id, 'product_id' => $row->product_id,
                'amount' => $row->amount, 'method' => $row->method,
                ...($row->trace === null ? [] : ['trace' => json_decode($row->trace, true, flags: JSON_THROW_ON_ERROR)])])->all();
            $this->assertSame($rows, $actual);
        }
    }

    public function test_pretend_mode_does_not_write_trace_rows(): void
    {
        $buffer = new AllocationBuffer;
        $buffer->add(['expense_id' => 1, 'product_id' => 2, 'amount' => '10', 'method' => 'value']);
        DB::pretend(fn () => app(WriteAllocations::class)->execute(123, $buffer->finish()));
        $this->assertDatabaseCount('cost_allocations', 0);
    }
}
