<?php

namespace Tests\Feature;

use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LargeCostRunTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_thousand_lines_reconcile_with_bounded_bulk_queries(): void
    {
        $u = User::factory()->create();
        $t = Trip::create(['user_id' => $u->id, 'nombre' => 'Mil líneas sintéticas', 'moneda_base' => 'CLP', 'moneda_trabajo' => 'USD', 'tipo_cambio' => '900', 'estado' => 'activo']);
        for ($i = 0; $i < 1000; $i++) {
            $t->products()->create(['nombre' => 'Producto '.$i, 'moneda' => 'CLP', 'cantidad' => 3, 'costo_unitario' => '1', 'subtotal_clp' => '3', 'costo_final_total_clp' => '3', 'costo_final_unitario_clp' => '1']);
        }
        DB::enableQueryLog();
        $start = microtime(true);
        $this->actingAs($u)->post('/trips/'.$t->id.'/expenses', ['concepto' => 'Reparto exacto', 'moneda' => 'CLP', 'monto' => '1001', 'expected_revision' => 1])->assertSessionHasNoErrors();
        $elapsed = round((microtime(true) - $start) * 1000);
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();
        $r = $t->costRuns()->firstOrFail();
        $this->assertSame('4001', $r->result['total']);
        $this->assertSame('0', $r->result['unassigned']);
        $this->assertSame(1000, DB::table('cost_allocations')->where('cost_run_id', $r->id)->count());
        $this->assertEquals(1001, $t->products()->sum('gastos_prorrateados_clp'));
        $this->assertLessThan(80, $queries);
        fwrite(STDOUT, "\nCosteo 1000 líneas: $queries consultas; {$elapsed} ms en test local\n");
    }
}
