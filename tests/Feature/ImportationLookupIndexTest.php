<?php

namespace Tests\Feature;

use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ImportationLookupIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_sqlite_financial_lookups_use_indexes_and_rollback_preserves_records(): void
    {
        $trip = Trip::create(['user_id' => User::factory()->create()->id, 'nombre' => 'Índices sin recálculo',
            'moneda_base' => 'CLP', 'moneda_trabajo' => 'USD', 'tipo_cambio' => '900', 'estado' => 'activo']);
        $product = $trip->products()->create(['nombre' => 'Línea fraccionaria', 'moneda' => 'CLP', 'cantidad' => 1,
            'costo_unitario' => '10.10', 'subtotal_clp' => '10.10', 'gastos_prorrateados_clp' => '0.10', 'costo_final_total_clp' => '10.20', 'costo_final_unitario_clp' => '10.20']);
        $trip->expenses()->create(['concepto' => 'Centésimas heredadas', 'moneda' => 'CLP', 'monto' => '0.10', 'monto_clp' => '0.10']);
        $before = ['trip' => DB::table('trips')->find($trip->id), 'product' => DB::table('products')->find($product->id),
            'expenses' => DB::table('expenses')->get()->all()];
        foreach (['products' => 'subtotal_clp', 'expenses' => 'monto_clp'] as $table => $field) {
            $plan = DB::select("EXPLAIN QUERY PLAN SELECT SUM($field) FROM $table WHERE trip_id = ?", [$trip->id]);
            $this->assertStringContainsString('USING INDEX '.$table.'_importation_lookup', $plan[0]->detail);
        }
        $migration = require database_path('migrations/2026_10_04_070000_index_importation_children.php');
        $migration->down();
        foreach (['products', 'expenses', 'documents', 'pricing_batches'] as $table) {
            $this->assertFalse(Schema::hasIndex($table, $table.'_importation_lookup'));
        }
        $migration->up();
        $migration->up();
        foreach (['products', 'expenses', 'documents', 'pricing_batches'] as $table) {
            $this->assertTrue(Schema::hasIndex($table, $table.'_importation_lookup'));
        }
        $this->assertEquals($before, ['trip' => DB::table('trips')->find($trip->id), 'product' => DB::table('products')->find($product->id),
            'expenses' => DB::table('expenses')->get()->all()]);
        $this->assertSame([], DB::select('PRAGMA foreign_key_check'));
    }
}
