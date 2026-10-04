<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Trip;
use App\Models\User;
use App\Modules\Pricing\Domain\Projection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ProjectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_prices_do_not_become_zero_or_fake_losses(): void
    {
        $products = [new Product(['cantidad' => 2, 'precio_venta' => '60', 'costo_final_total_clp' => '200']), new Product(['cantidad' => 1, 'precio_venta' => null, 'costo_final_total_clp' => '100'])];
        $r = (new Projection)->summarize($products);
        $this->assertFalse($r['complete']);
        $this->assertNull($r['profit']);
        $this->assertNull($r['revenue']);
        $this->assertSame('-80', $r['covered_profit']);
        $products[1]->precio_venta = '90';
        $r = (new Projection)->summarize($products);
        $this->assertTrue($r['complete']);
        $this->assertSame('-90', $r['profit']);
        $this->assertSame('-42.86', $r['margin']);
    }

    public function test_complete_projection_includes_unassigned_expense(): void
    {
        $r = (new Projection)->summarize([new Product(['cantidad' => 1, 'precio_venta' => '100', 'costo_final_total_clp' => '0'])], '20');
        $this->assertSame('100', $r['revenue']);
        $this->assertSame('80', $r['profit']);
        $this->assertSame('80.00', $r['margin']);
    }

    public function test_detail_projection_reconciles_with_all_expenses(): void
    {
        $u = User::factory()->create();
        $t = Trip::create(['user_id' => $u->id, 'nombre' => 'Caso sin asignación', 'moneda_base' => 'CLP', 'moneda_trabajo' => 'USD', 'tipo_cambio' => '900', 'estado' => 'activo']);
        $t->products()->create(['nombre' => 'Producto', 'moneda' => 'CLP', 'cantidad' => 1, 'costo_unitario' => '0', 'subtotal_clp' => '0', 'costo_final_unitario_clp' => '0', 'costo_final_total_clp' => '0', 'precio_venta' => '100']);
        $t->expenses()->create(['concepto' => 'Gasto', 'moneda' => 'CLP', 'monto' => '20', 'monto_clp' => '20']);
        $this->actingAs($u)->get('/trips/'.$t->id)->assertInertia(fn (AssertableInertia $p) => $p->where('projection.profit', '80')->where('projection.revenue', '100')->etc());
    }
}
