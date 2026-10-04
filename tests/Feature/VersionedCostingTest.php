<?php

namespace Tests\Feature;

use App\Models\CostRun;
use App\Models\Product;
use App\Models\Trip;
use App\Models\User;
use App\Modules\Reporting\ReportTrip;
use App\Services\ProrrateoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VersionedCostingTest extends TestCase
{
    use RefreshDatabase;

    private function trip(): Trip
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        return Trip::create(['user_id' => $user->id, 'nombre' => 'Caso sintético', 'moneda_trabajo' => 'USD', 'moneda_base' => 'CLP', 'tipo_cambio' => '900.12345678', 'estado' => 'activo']);
    }

    private function addProduct(Trip $trip): Product
    {
        $this->post("/trips/{$trip->id}/products", ['nombre' => 'Producto', 'moneda' => 'USD', 'cantidad' => 3, 'costo_unitario' => '100', 'expected_revision' => $trip->fresh()->revision])->assertSessionHasNoErrors();

        return $trip->products()->firstOrFail();
    }

    public function test_mutations_record_revision_actor_and_exact_result(): void
    {
        $trip = $this->trip();
        $this->addProduct($trip);
        $this->assertSame(2, $trip->fresh()->revision);
        $run = $trip->costRuns()->firstOrFail();
        $this->assertSame('270037', $run->result['purchase']);
        $this->assertSame(auth()->id(), $run->actor_id);
        $this->assertSame('900.12345678', $run->input['reference_rate']);
        $this->assertSame(1, $trip->auditEvents()->count());
    }

    public function test_stale_revision_is_rejected_without_overwriting(): void
    {
        $trip = $this->trip();
        $p = $this->addProduct($trip);
        $this->patch("/trips/{$trip->id}/products/{$p->id}/precio-venta", ['precio_venta' => '500000', 'expected_revision' => 1])->assertSessionHasErrors('conflict');
        $this->assertNull($p->fresh()->precio_venta);
        $this->assertSame(2, $trip->fresh()->revision);
        $this->assertSame(1, CostRun::count());
    }

    public function test_paid_expense_preserves_historical_rate_on_revaluation(): void
    {
        $trip = $this->trip();
        $p = $this->addProduct($trip);
        $this->post("/trips/{$trip->id}/expenses", ['expected_revision' => $trip->fresh()->revision, 'concepto' => 'Pago', 'moneda' => 'USD', 'monto' => '10', 'rate_mode' => 'paid', 'applied_rate' => '800', 'rate_source' => 'Comprobante bancario', 'rate_date' => '2026-10-03'])->assertSessionHasNoErrors();
        $this->post("/trips/{$trip->id}/expenses", ['expected_revision' => $trip->fresh()->revision, 'concepto' => 'Estimación', 'moneda' => 'USD', 'monto' => '10'])->assertSessionHasNoErrors();
        $this->put("/trips/{$trip->id}", ['expected_revision' => $trip->fresh()->revision, 'nombre' => $trip->nombre, 'moneda_trabajo' => 'USD', 'tipo_cambio' => '1000'])->assertSessionHasNoErrors();
        $this->assertEquals(8000, $trip->expenses()->where('concepto', 'Pago')->first()->monto_clp);
        $this->assertEquals(10000, $trip->expenses()->where('concepto', 'Estimación')->first()->monto_clp);
        $this->assertEquals(318000, $p->fresh()->costo_final_total_clp);
    }

    public function test_closed_snapshot_survives_reopen_and_edit_and_exports_original(): void
    {
        $trip = $this->trip();
        $p = $this->addProduct($trip);
        $this->patch("/trips/{$trip->id}/close", ['expected_revision' => $trip->fresh()->revision])->assertSessionHasNoErrors();
        $run = $trip->costRuns()->where('reason', 'close')->firstOrFail();
        $original = $run->snapshot;
        $this->patch("/trips/{$trip->id}/reopen", ['reason' => 'Corregir una nueva operación documentada', 'expected_revision' => $trip->fresh()->revision])->assertSessionHasNoErrors();
        $this->put("/trips/{$trip->id}/products/{$p->id}", ['expected_revision' => $trip->fresh()->revision, 'nombre' => 'Actualizado', 'moneda' => 'USD', 'cantidad' => 3, 'costo_unitario' => '200'])->assertSessionHasNoErrors();
        $this->assertSame($original, $run->fresh()->snapshot);
        $historical = app(ReportTrip::class)->resolve($trip->fresh(), $run->id);
        $this->assertSame('Producto', $historical->products[0]->nombre);
        $this->assertEquals(270037, $historical->products[0]->subtotal_clp);
        $this->get("/trips/{$trip->id}/export/pdf?run={$run->id}")->assertOk();
        $this->get("/trips/{$trip->id}/export/excel?run={$run->id}")->assertOk();
    }

    public function test_every_normal_mutation_is_forbidden_after_close(): void
    {
        $trip = $this->trip();
        $p = $this->addProduct($trip);
        $this->post("/trips/{$trip->id}/expenses", ['expected_revision' => $trip->fresh()->revision, 'concepto' => 'Gasto', 'moneda' => 'CLP', 'monto' => '30']);
        $e = $trip->expenses()->firstOrFail();
        $this->patch("/trips/{$trip->id}/close", ['expected_revision' => $trip->fresh()->revision])->assertSessionHasNoErrors();
        $version = $trip->fresh()->revision;
        $this->put("/trips/{$trip->id}", ['nombre' => 'Cambiado'])->assertForbidden();
        $this->put("/trips/{$trip->id}/products/{$p->id}", [])->assertForbidden();
        $this->delete("/trips/{$trip->id}/products/{$p->id}")->assertForbidden();
        $this->patch("/trips/{$trip->id}/products/{$p->id}/precio-venta", ['precio_venta' => 1])->assertForbidden();
        $this->post("/trips/{$trip->id}/expenses", [])->assertForbidden();
        $this->put("/trips/{$trip->id}/expenses/{$e->id}", [])->assertForbidden();
        $this->delete("/trips/{$trip->id}/expenses/{$e->id}")->assertForbidden();
        $this->assertSame($version, $trip->fresh()->revision);
    }

    public function test_mid_calculation_error_rolls_back_data_revision_and_uploaded_file(): void
    {
        Storage::fake('local');
        $trip = $this->trip();
        $this->addProduct($trip);
        $this->mock(ProrrateoService::class, fn ($mock) => $mock->shouldReceive('calcularProrrateo')->once()->andThrow(new \RuntimeException('Synthetic failure')));
        $this->withoutExceptionHandling();
        try {
            $this->post("/trips/{$trip->id}/expenses", ['expected_revision' => $trip->fresh()->revision, 'concepto' => 'Rollback', 'moneda' => 'USD', 'monto' => '10', 'comprobante' => UploadedFile::fake()->image('receipt.jpg')]);
            $this->fail('Failure expected');
        } catch (\RuntimeException $e) {
            $this->assertSame('Synthetic failure', $e->getMessage());
        }
        $this->assertDatabaseMissing('expenses', ['concepto' => 'Rollback']);
        $this->assertSame(2, $trip->fresh()->revision);
        $this->assertSame(1, CostRun::count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_snapshot_cannot_be_rewritten(): void
    {
        $trip = $this->trip();
        $this->addProduct($trip);
        $this->expectException(\LogicException::class);
        $trip->costRuns()->firstOrFail()->update(['reason' => 'fake']);
    }

    public function test_estimated_form_clears_null_metadata_and_invalid_paid_clp_is_rejected(): void
    {
        $trip = $this->trip();
        $this->post("/trips/{$trip->id}/expenses", ['expected_revision' => $trip->fresh()->revision, 'concepto' => 'Estimado', 'moneda' => 'USD', 'monto' => '10', 'rate_mode' => 'estimated', 'rate_source' => null, 'applied_rate' => null, 'rate_date' => null])->assertSessionHasNoErrors();
        $this->assertSame('trip-reference', $trip->expenses()->firstOrFail()->rate_source);
        $this->post("/trips/{$trip->id}/expenses", ['expected_revision' => $trip->fresh()->revision, 'concepto' => 'CLP inválido', 'moneda' => 'CLP', 'monto' => '10', 'rate_mode' => 'paid', 'applied_rate' => '2', 'rate_source' => 'Banco', 'rate_date' => '2026-10-03'])->assertSessionHasErrors('applied_rate');
        $this->assertDatabaseMissing('expenses', ['concepto' => 'CLP inválido']);
    }

    public function test_legacy_paid_unknown_currency_cannot_bypass_currency_registry(): void
    {
        $trip = $this->trip();
        $trip->expenses()->create(['concepto' => 'Legado', 'moneda' => 'XYZ', 'monto' => '10', 'monto_clp' => 10, 'rate_mode' => 'paid', 'applied_rate' => '1', 'rate_source' => 'Fuente heredada', 'rate_date' => '2026-10-03']);
        $this->put("/trips/{$trip->id}", ['expected_revision' => $trip->fresh()->revision, 'nombre' => 'Intento', 'moneda_trabajo' => 'USD', 'tipo_cambio' => '1000'])->assertSessionHasErrors('applied_rate');
        $this->assertSame('Caso sintético', $trip->fresh()->nombre);
        $this->assertSame(1, $trip->fresh()->revision);
    }
}
