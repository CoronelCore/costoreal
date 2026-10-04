<?php

namespace Tests\Feature;

use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductImportTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(string $source): array
    {
        $u = User::factory()->create();
        $this->actingAs($u);
        $trip = Trip::create(['user_id' => $u->id, 'nombre' => 'CSV QA', 'moneda_base' => 'CLP', 'moneda_trabajo' => 'USD', 'tipo_cambio' => '900', 'estado' => 'activo']);
        $trip->expenses()->create(['concepto' => 'Flete', 'moneda' => 'CLP', 'monto' => '1001', 'monto_clp' => 1001]);
        $data = ['expected_revision' => 1, 'source_text' => $source, 'delimiter' => ',', 'has_header' => true, 'default_currency' => 'USD',
            'mapping' => ['nombre' => 0, 'cantidad' => 1, 'moneda' => 2, 'costo_unitario' => 3, 'weight' => null, 'volume' => null], 'idempotency_key' => (string) Str::uuid()];

        return [$trip, $data];
    }

    public function test_one_hundred_rows_reconcile_and_retry_never_duplicates(): void
    {
        $source = "nombre,cantidad,moneda,costo_unitario\n";
        for ($i = 1; $i <= 100; $i++) {
            $source .= "Producto $i,3,USD,0.10\n";
        }
        [$trip, $data] = $this->fixture($source);
        $this->postJson("/trips/{$trip->id}/products/import/preview", $data)->assertOk()->assertJsonPath('count', 100)->assertJsonPath('can_apply', true)->assertJsonPath('totals.purchase', '27000')->assertJsonPath('totals.total', '28001');
        $this->assertSame(0, $trip->products()->count());
        $this->assertSame(1, $trip->fresh()->revision);
        $this->post("/trips/{$trip->id}/products/import/apply", $data)->assertSessionHasNoErrors();
        $this->assertSame(100, $trip->products()->count());
        $this->assertEquals(28001, $trip->products()->sum('costo_final_total_clp'));
        $this->assertEquals(1001, $trip->products()->sum('gastos_prorrateados_clp'));
        $this->assertSame(2, $trip->fresh()->revision);
        $this->post("/trips/{$trip->id}/products/import/apply", $data)->assertSessionHasNoErrors();
        $this->assertSame(100, $trip->products()->count());
        $this->assertSame(1, DB::table('product_import_batches')->count());
        $this->assertSame(1, $trip->costRuns()->count());
    }

    public function test_row_errors_mapping_and_unknown_currency_reject_the_whole_batch(): void
    {
        [$trip, $data] = $this->fixture("nombre,cantidad,moneda,costo_unitario\nCorrecto,1,USD,100\nInválido,0,XYZ,=100\n");
        $this->postJson("/trips/{$trip->id}/products/import/preview", $data)->assertOk()->assertJsonPath('invalid_count', 1)->assertJsonPath('rows.1.row', 3)->assertJsonPath('can_apply', false);
        $this->post("/trips/{$trip->id}/products/import/apply", $data)->assertSessionHasErrors('import');
        $this->assertSame(0, $trip->products()->count());
        $this->assertSame(1, $trip->fresh()->revision);
        $this->assertSame(0, DB::table('product_import_batches')->count());
        $this->postJson("/trips/{$trip->id}/products/import/preview", [...$data, 'mapping' => [...$data['mapping'], 'cantidad' => 0]])->assertUnprocessable()->assertJsonValidationErrors('mapping');
    }

    public function test_uploaded_csv_mapping_and_decimal_comma_are_explicit(): void
    {
        [$trip, $data] = $this->fixture('');
        unset($data['source_text']);
        $data['file'] = UploadedFile::fake()->createWithContent('products.csv', "costo;nombre;cantidad\n10.25;\"Nombre; literal\";2");
        $data['delimiter'] = ';';
        $data['mapping'] = ['nombre' => 1, 'cantidad' => 2, 'moneda' => null, 'costo_unitario' => 0];
        $this->postJson("/trips/{$trip->id}/products/import/preview", $data)->assertOk()->assertJsonPath('can_apply', true)->assertJsonPath('rows.0.data.nombre', 'Nombre; literal')->assertJsonPath('totals.purchase', '18450');
        $this->post("/trips/{$trip->id}/products/import/apply", $data)->assertSessionHasNoErrors();
        $this->assertSame('Nombre; literal', $trip->products()->firstOrFail()->nombre);
        [$other, $comma] = $this->fixture("nombre,cantidad,moneda,costo_unitario\nSensor,1,USD,\"10,25\"");
        $this->postJson("/trips/{$other->id}/products/import/preview", $comma)->assertOk()->assertJsonPath('can_apply', false);
    }

    public function test_conflict_foreign_closed_and_reused_key_with_different_source_do_not_write(): void
    {
        [$trip, $data] = $this->fixture("nombre,cantidad,moneda,costo_unitario\nProducto,1,USD,1");
        $this->post("/trips/{$trip->id}/products/import/apply", [...$data, 'expected_revision' => 9])->assertSessionHasErrors('conflict');
        $this->actingAs(User::factory()->create())->postJson("/trips/{$trip->id}/products/import/preview", $data)->assertForbidden();
        $this->actingAs($trip->user)->post("/trips/{$trip->id}/products/import/apply", $data)->assertSessionHasNoErrors();
        $this->post("/trips/{$trip->id}/products/import/apply", [...$data, 'source_text' => "nombre,cantidad,moneda,costo_unitario\nOtro,1,USD,1"])->assertSessionHasErrors('idempotency_key');
        $this->patch("/trips/{$trip->id}/close", ['expected_revision' => 2])->assertSessionHasNoErrors();
        $this->post("/trips/{$trip->id}/products/import/apply", [...$data, 'idempotency_key' => (string) Str::uuid(), 'expected_revision' => 3])->assertForbidden();
        $this->assertSame(1, $trip->products()->count());
    }

    public function test_missing_weight_is_identified_in_preview_before_writing(): void
    {
        [$trip, $data] = $this->fixture("nombre,cantidad,moneda,costo_unitario\nProducto,1,USD,1");
        $trip->expenses()->update(['allocation_method' => 'weight']);
        $this->postJson("/trips/{$trip->id}/products/import/preview", $data)->assertOk()->assertJsonPath('can_apply', false)->assertJsonStructure(['cost_errors' => ['allocation_method']]);
        $this->assertSame(0, $trip->products()->count());
    }

    public function test_one_thousand_rows_use_bounded_bulk_queries_and_reconcile(): void
    {
        $source = "nombre,cantidad,moneda,costo_unitario\n";
        for ($i = 1; $i <= 1000; $i++) {
            $source .= "Producto $i,3,USD,0.10\n";
        }
        [$trip, $data] = $this->fixture($source);
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->post("/trips/{$trip->id}/products/import/apply", $data)->assertSessionHasNoErrors();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertLessThan(130, $queries);
        $this->assertSame(1000, $trip->products()->count());
        $this->assertEquals(271001, $trip->products()->sum('costo_final_total_clp'));
        $this->assertSame(1000, count(json_decode(DB::table('product_import_batches')->first()->product_ids, true)));
        fwrite(STDOUT, "\nImportación CSV 1000 filas: {$queries} consultas\n");
    }
}
