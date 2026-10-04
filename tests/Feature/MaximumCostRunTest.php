<?php

namespace Tests\Feature;

use App\Models\Trip;
use App\Models\User;
use App\Modules\Costing\Domain\DecimalSum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Tests\TestCase;

class MaximumCostRunTest extends TestCase
{
    use RefreshDatabase;

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_http_update_and_close_keep_a_million_trace_rows_within_128_mib(): void
    {
        $previousLimit = ini_get('memory_limit');
        ini_set('memory_limit', '128M');
        try {
            $this->actingAs(User::factory()->create());
            $trip = Trip::create(['user_id' => auth()->id(), 'nombre' => 'Máximo sintético', 'moneda_base' => 'CLP',
                'moneda_trabajo' => 'USD', 'tipo_cambio' => '900', 'estado' => 'activo']);
            foreach (array_chunk(range(1, 1000), 100) as $numbers) {
                DB::table('products')->insert(array_map(fn ($i) => ['trip_id' => $trip->id, 'nombre' => 'Línea '.$i,
                    'cantidad' => 1, 'moneda' => 'CLP', 'costo_unitario' => '10', 'subtotal_clp' => '10',
                    'costo_final_total_clp' => '10', 'costo_final_unitario_clp' => '10', 'rate_mode' => 'estimated',
                    'rate_source' => 'trip-reference'], $numbers));
            }
            DB::table('expenses')->insert(array_map(fn ($i) => ['trip_id' => $trip->id, 'concepto' => 'Gasto '.$i,
                'moneda' => 'CLP', 'monto' => '1', 'monto_clp' => '1', 'rate_mode' => 'estimated',
                'rate_source' => 'trip-reference'], range(1, 500)));
            $expense = $trip->expenses()->firstOrFail();
            $this->put("/trips/{$trip->id}/expenses/{$expense->id}", ['expected_revision' => 1,
                'concepto' => $expense->concepto, 'moneda' => 'CLP', 'monto' => '2', 'rate_mode' => 'estimated'])
                ->assertRedirect()->assertSessionHasNoErrors();
            $run = $trip->costRuns()->firstOrFail();
            $this->assertSame('10501', $run->result['total']);
            $this->assertSame(500000, $run->result['allocation_count']);
            $this->assertArrayNotHasKey('allocations', $run->result);
            $originalHash = hash('sha256', json_encode($run->getRawOriginal(), JSON_THROW_ON_ERROR));
            $this->assertDatabaseCount('cost_allocations', 500000);
            $lineTotal = new DecimalSum;
            foreach (DB::table('cost_run_lines')->where('cost_run_id', $run->id)->cursor() as $line) {
                $lineTotal->add(json_decode($line->data, true, flags: JSON_THROW_ON_ERROR)['total']);
            }
            $this->assertSame('10501', (string) $lineTotal->value());
            $this->patch("/trips/{$trip->id}/close", ['expected_revision' => 2])
                ->assertRedirect()->assertSessionHasNoErrors();
            $this->assertSame('finalizado', $trip->fresh()->estado);
            $this->assertSame(3, $trip->fresh()->revision);
            $this->assertDatabaseCount('cost_runs', 2);
            $this->assertDatabaseCount('cost_allocations', 1000000);
            $this->assertSame($originalHash, hash('sha256', json_encode($run->fresh()->getRawOriginal(), JSON_THROW_ON_ERROR)));
            $this->assertLessThanOrEqual(128 * 1024 * 1024, memory_get_peak_usage(true));
        } finally {
            ini_set('memory_limit', $previousLimit);
        }
    }
}
