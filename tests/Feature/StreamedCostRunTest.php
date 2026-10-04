<?php

namespace Tests\Feature;

use App\Models\CostRun;
use App\Models\Trip;
use App\Models\User;
use App\Modules\Costing\Actions\ProjectCostRun;
use App\Modules\Costing\Actions\RecordCostRun;
use App\Services\ProrrateoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StreamedCostRunTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): Trip
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $trip = Trip::create(['user_id' => $user->id, 'nombre' => 'Asignaciones por bloques',
            'moneda_base' => 'CLP', 'moneda_trabajo' => 'USD', 'tipo_cambio' => '900', 'estado' => 'activo']);
        foreach (array_chunk(range(1, 600), 100) as $numbers) {
            DB::table('products')->insert(array_map(fn ($i) => ['trip_id' => $trip->id, 'nombre' => 'Línea '.$i,
                'cantidad' => 1, 'moneda' => 'CLP', 'costo_unitario' => '10', 'subtotal_clp' => '10',
                'costo_final_total_clp' => '10', 'costo_final_unitario_clp' => '10'], $numbers));
        }
        DB::table('expenses')->insert(array_map(fn ($i) => ['trip_id' => $trip->id, 'concepto' => 'Gasto '.$i,
            'moneda' => 'CLP', 'monto' => '101', 'monto_clp' => '101'], range(1, 12)));

        return $trip->fresh();
    }

    public function test_complete_trace_persistence_matches_stream_without_duplicate_result_json(): void
    {
        $trip = $this->fixture();
        $result = app(ProrrateoService::class)->preview($trip);
        $hash = hash_init('sha256');
        foreach ($result->allocations as $row) {
            hash_update($hash, json_encode($row, JSON_THROW_ON_ERROR)."\n");
        }
        $expected = hash_final($hash);
        $inserts = 0;
        DB::listen(function ($query) use (&$inserts) {
            if (str_contains($query->sql, 'insert into "cost_allocations"')) {
                $inserts++;
            }
        });
        $run = app(RecordCostRun::class)->execute($trip, $result, 'stream_fixture');
        $this->assertSame(2, $inserts);
        $this->assertSame(2, $run->result['storage_version']);
        $this->assertSame('cost_allocations', $run->result['allocation_source']);
        $this->assertSame(7200, $run->result['allocation_count']);
        $this->assertArrayNotHasKey('allocations', $run->result);
        $this->assertSame('7212', $run->result['total']);
        $this->assertSame(7200, DB::table('cost_allocations')->where('cost_run_id', $run->id)->count());
        $hash = hash_init('sha256');
        foreach (DB::table('cost_allocations')->where('cost_run_id', $run->id)->orderBy('expense_id')->orderBy('product_id')->cursor() as $row) {
            hash_update($hash, json_encode(['expense_id' => $row->expense_id, 'product_id' => $row->product_id,
                'amount' => $row->amount, 'method' => $row->method, 'trace' => json_decode($row->trace, true, flags: JSON_THROW_ON_ERROR)], JSON_THROW_ON_ERROR)."\n");
        }
        $this->assertSame($expected, hash_final($hash));
        $this->assertSame(600, DB::table('cost_run_lines')->where('cost_run_id', $run->id)->count());
    }

    public function test_failure_after_a_complete_batch_rolls_back_revision_product_and_every_trace(): void
    {
        $trip = $this->fixture();
        $product = $trip->products()->first();
        $before = $product->getRawOriginal();
        $inserts = 0;
        DB::listen(function ($query) use (&$inserts) {
            if (str_contains($query->sql, 'insert into "cost_allocations"') && ++$inserts === 2) {
                throw new \RuntimeException('Synthetic second batch failure');
            }
        });
        $this->withoutExceptionHandling();
        try {
            $this->put("/trips/{$trip->id}/products/{$product->id}", ['expected_revision' => 1,
                'nombre' => 'Debe revertirse', 'cantidad' => 1, 'moneda' => 'CLP', 'costo_unitario' => '20']);
            $this->fail('The second batch must fail.');
        } catch (\RuntimeException $error) {
            $this->assertSame('Synthetic second batch failure', $error->getMessage());
        }
        $this->assertSame(2, $inserts);
        $this->assertSame(1, $trip->fresh()->revision);
        $this->assertSame($before, $product->fresh()->getRawOriginal());
        foreach (['cost_runs', 'cost_allocations', 'cost_run_views', 'cost_run_lines', 'audit_events', 'legacy_cost_baselines'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    public function test_original_full_result_json_remains_supported_and_unchanged(): void
    {
        $trip = $this->fixture();
        $result = app(ProrrateoService::class)->preview($trip);
        $trip->load(['products', 'expenses']);
        $run = CostRun::create(['trip_id' => $trip->id, 'revision' => 1, 'algorithm' => $result->algorithm,
            'reason' => 'legacy_full_json_fixture', 'input' => ['currency' => 'USD', 'reference_rate' => '900', 'products' => [], 'expenses' => []],
            'result' => $result->toArray(), 'snapshot' => $trip->toArray(), 'input_hash' => hash('sha256', 'legacy'), 'created_at' => now()]);
        $run = $run->fresh();
        $raw = $run->getRawOriginal();
        $this->assertTrue(app(ProjectCostRun::class)->execute($run));
        $this->assertFalse(app(ProjectCostRun::class)->execute($run->fresh()));
        $this->assertSame($raw, $run->fresh()->getRawOriginal());
        $this->assertSame(600, DB::table('cost_run_lines')->where('cost_run_id', $run->id)->count());
    }
}
