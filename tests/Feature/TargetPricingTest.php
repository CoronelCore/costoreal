<?php

namespace Tests\Feature;

use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TargetPricingTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $trip = Trip::create(['user_id' => $user->id, 'nombre' => 'Pricing QA', 'moneda_base' => 'CLP', 'moneda_trabajo' => 'USD', 'tipo_cambio' => '900', 'estado' => 'activo']);
        $this->post("/trips/{$trip->id}/products", ['nombre' => 'Producto', 'moneda' => 'CLP', 'cantidad' => 1, 'costo_unitario' => '30000', 'expected_revision' => 1])->assertSessionHasNoErrors();
        $product = $trip->products()->firstOrFail();

        return [$trip->fresh(), $product, ['expected_revision' => 2, 'product_ids' => [$product->id], 'method' => 'margin', 'target' => '20', 'commission' => '0', 'quantum' => 1]];
    }

    public function test_preview_never_writes_and_apply_records_policy_atomically(): void
    {
        [$trip, $product, $policy] = $this->fixture();
        $this->postJson("/trips/{$trip->id}/pricing/preview", $policy)->assertOk()->assertJsonPath('lines.0.price', '37500')->assertJsonPath('revision', 2);
        $this->assertNull($product->fresh()->precio_venta);
        $this->assertSame(2, $trip->fresh()->revision);
        $this->assertSame(0, DB::table('pricing_batches')->count());
        $this->post("/trips/{$trip->id}/pricing/apply", $policy)->assertSessionHasNoErrors();
        $this->assertSame('37500.00', $product->fresh()->precio_venta);
        $this->assertSame(3, $trip->fresh()->revision);
        $batch = DB::table('pricing_batches')->first();
        $this->assertSame('20', json_decode($batch->policy, true)['target']);
        $this->assertSame(3, (int) $batch->revision);
        $this->assertSame('price_target', $trip->costRuns()->latest('id')->firstOrFail()->reason);
        $this->assertSame('37500.00', $trip->costRuns()->latest('id')->first()->snapshot['products'][0]['precio_venta']);
    }

    public function test_stale_preview_cannot_overwrite_a_manual_price(): void
    {
        [$trip, $product, $policy] = $this->fixture();
        $this->patch("/trips/{$trip->id}/products/{$product->id}/precio-venta", ['precio_venta' => '45000', 'expected_revision' => 2])->assertSessionHasNoErrors();
        $this->post("/trips/{$trip->id}/pricing/apply", $policy)->assertSessionHasErrors('conflict');
        $this->postJson("/trips/{$trip->id}/pricing/preview", $policy)->assertUnprocessable()->assertJsonValidationErrors('conflict');
        $this->assertSame('45000.00', $product->fresh()->precio_venta);
        $this->assertSame(0, DB::table('pricing_batches')->count());
    }

    public function test_foreign_selection_duplicate_and_closed_apply_are_rejected(): void
    {
        [$trip, $product, $policy] = $this->fixture();
        $this->postJson("/trips/{$trip->id}/pricing/preview", [...$policy, 'product_ids' => [$product->id + 1000]])->assertUnprocessable()->assertJsonValidationErrors('product_ids');
        $this->postJson("/trips/{$trip->id}/pricing/preview", [...$policy, 'product_ids' => [$product->id, $product->id]])->assertUnprocessable()->assertJsonValidationErrors('product_ids.0');
        $this->actingAs(User::factory()->create())->postJson("/trips/{$trip->id}/pricing/preview", $policy)->assertForbidden();
        $this->actingAs($trip->user)->patch("/trips/{$trip->id}/close", ['expected_revision' => 2])->assertSessionHasNoErrors();
        $this->post("/trips/{$trip->id}/pricing/apply", [...$policy, 'expected_revision' => 3])->assertForbidden();
        $this->assertNull($product->fresh()->precio_venta);
    }

    public function test_invalid_target_or_unassigned_expense_does_not_partially_apply(): void
    {
        [$trip, $product, $policy] = $this->fixture();
        $this->post("/trips/{$trip->id}/pricing/apply", [...$policy, 'target' => '95', 'commission' => '5'])->assertSessionHasErrors("products.{$product->id}");
        $this->assertNull($product->fresh()->precio_venta);
        $this->assertSame(2, $trip->fresh()->revision);
        $trip->expenses()->create(['concepto' => 'Sin asignar', 'moneda' => 'CLP', 'monto' => 1, 'monto_clp' => 1, 'allocation_method' => 'direct', 'target_product_id' => 99999]);
        $this->postJson("/trips/{$trip->id}/pricing/preview", $policy)->assertUnprocessable()->assertJsonValidationErrors('pricing');
        $this->assertSame(0, DB::table('pricing_batches')->count());
    }

    public function test_preview_uses_domain_inputs_without_trusting_stale_legacy_totals(): void
    {
        [$trip, $product, $policy] = $this->fixture();
        $product->update(['moneda' => 'USD', 'costo_unitario' => '100']);
        $this->postJson("/trips/{$trip->id}/pricing/preview", $policy)->assertOk()->assertJsonPath('lines.0.cost', '90000')->assertJsonPath('lines.0.price', '112500');
        $this->assertSame('30000.00', $product->fresh()->costo_final_total_clp);
        $this->post("/trips/{$trip->id}/pricing/apply", $policy)->assertSessionHasNoErrors();
        $this->assertSame('90000.00', $product->fresh()->costo_final_total_clp);
        $this->assertSame('112500.00', $product->fresh()->precio_venta);
    }
}
