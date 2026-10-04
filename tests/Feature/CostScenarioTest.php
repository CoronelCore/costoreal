<?php

namespace Tests\Feature;

use App\Models\CostScenario;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CostScenarioTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $trip = Trip::create(['user_id' => $user->id, 'nombre' => 'Escenario QA', 'moneda_trabajo' => 'USD', 'moneda_base' => 'CLP', 'tipo_cambio' => '900', 'estado' => 'activo']);
        $this->post("/trips/{$trip->id}/products", ['nombre' => 'Producto', 'cantidad' => 1, 'moneda' => 'USD', 'costo_unitario' => '100', 'expected_revision' => 1])->assertSessionHasNoErrors();
        $product = $trip->products()->firstOrFail();
        $this->patch("/trips/{$trip->id}/products/{$product->id}/precio-venta", ['precio_venta' => '200000', 'expected_revision' => 2])->assertSessionHasNoErrors();
        $this->post("/trips/{$trip->id}/expenses", ['concepto' => 'Estimado', 'moneda' => 'USD', 'monto' => '10', 'expected_revision' => 3])->assertSessionHasNoErrors();
        $this->post("/trips/{$trip->id}/expenses", ['concepto' => 'Pagado', 'moneda' => 'USD', 'monto' => '10', 'rate_mode' => 'paid', 'applied_rate' => '800', 'rate_source' => 'Banco QA', 'rate_date' => '2026-10-03', 'expected_revision' => 4])->assertSessionHasNoErrors();
        $input = ['expected_revision' => 5, 'name' => 'Tasa 1000 + flete 20 %', 'idempotency_key' => (string) Str::uuid(), 'reference_rate' => '1000', 'expense_variation_percent' => '20', 'products' => [], 'expenses' => [], 'pricing' => null];

        return [$trip->fresh(), $product, $input];
    }

    public function test_preview_and_saved_scenario_do_not_change_real_costs_or_revisions(): void
    {
        [$trip, $product, $input] = $this->fixture();
        $runs = $trip->costRuns()->count();
        $this->postJson("/trips/{$trip->id}/scenarios/preview", $input)->assertOk()->assertJsonPath('result.before.cost', '107000')->assertJsonPath('result.after.cost', '120000')->assertJsonPath('result.cost_delta', '13000')->assertJsonPath('result.after.projection.profit', '80000')->assertJsonPath('result.paid_expenses_preserved', 1);
        $this->assertSame(0, CostScenario::count());
        $this->postJson("/trips/{$trip->id}/scenarios", $input)->assertOk();
        $this->assertSame(5, $trip->fresh()->revision);
        $this->assertSame('900.00000000', $trip->fresh()->tipo_cambio);
        $this->assertSame('107000.00', $product->fresh()->costo_final_total_clp);
        $this->assertSame($runs, $trip->costRuns()->count());
        $this->postJson("/trips/{$trip->id}/scenarios", $input)->assertOk();
        $this->assertSame(1, CostScenario::count());
    }

    public function test_apply_promotes_exact_inputs_and_preserves_paid_records_and_saved_result(): void
    {
        [$trip, $product, $input] = $this->fixture();
        $this->postJson("/trips/{$trip->id}/scenarios", $input)->assertOk();
        $scenario = $trip->scenarios()->firstOrFail();
        $saved = $scenario->result;
        $this->post("/trips/{$trip->id}/scenarios/{$scenario->id}/apply", ['expected_revision' => 5])->assertSessionHasNoErrors();
        $this->assertSame(6, $trip->fresh()->revision);
        $this->assertSame('120000.00', $product->fresh()->costo_final_total_clp);
        $this->assertSame('12.00', $trip->expenses()->where('concepto', 'Estimado')->first()->monto);
        $paid = $trip->expenses()->where('concepto', 'Pagado')->first();
        $this->assertSame('10.00', $paid->monto);
        $this->assertSame('800', $paid->applied_rate);
        $this->assertEquals(8000, $paid->monto_clp);
        $this->assertSame($saved, $scenario->fresh()->result);
        $this->assertSame(6, $scenario->fresh()->applied_revision);
        $this->assertSame('scenario_apply', $trip->costRuns()->latest('id')->first()->reason);
        $this->post("/trips/{$trip->id}/scenarios/{$scenario->id}/apply", ['expected_revision' => 5])->assertSessionHasNoErrors();
        $this->assertSame(6, $trip->fresh()->revision);
    }

    public function test_old_revision_and_out_of_band_input_changes_cannot_be_promoted(): void
    {
        [$trip, $product, $input] = $this->fixture();
        $this->postJson("/trips/{$trip->id}/scenarios", $input)->assertOk();
        $scenario = $trip->scenarios()->firstOrFail();
        $product->update(['costo_unitario' => '101']);
        $this->post("/trips/{$trip->id}/scenarios/{$scenario->id}/apply", ['expected_revision' => 5])->assertSessionHasErrors('conflict');
        $this->assertSame(5, $trip->fresh()->revision);
        $this->assertNull($scenario->fresh()->applied_at);
        $this->patch("/trips/{$trip->id}/products/{$product->id}/precio-venta", ['precio_venta' => '210000', 'expected_revision' => 5])->assertSessionHasNoErrors();
        $this->post("/trips/{$trip->id}/scenarios/{$scenario->id}/apply", ['expected_revision' => 5])->assertSessionHasErrors('conflict');
        $this->postJson("/trips/{$trip->id}/scenarios/preview", $input)->assertUnprocessable()->assertJsonValidationErrors('conflict');
    }

    public function test_product_expense_rules_and_target_prices_are_simulated_and_applied(): void
    {
        [$trip, $product, $input] = $this->fixture();
        $expense = $trip->expenses()->where('concepto', 'Estimado')->first();
        $input['expense_variation_percent'] = '0';
        $input['products'] = [['id' => $product->id, 'cantidad' => 2, 'costo_unitario' => '100', 'weight' => '3', 'volume' => '0.5']];
        $input['expenses'] = [['id' => $expense->id, 'monto' => '20', 'allocation_method' => 'weight']];
        $input['pricing'] = ['method' => 'margin', 'target' => '20', 'commission' => '5', 'quantum' => 1000, 'product_ids' => [$product->id]];
        $this->postJson("/trips/{$trip->id}/scenarios/preview", $input)->assertOk()->assertJsonPath('result.after.cost', '228000')->assertJsonPath('result.lines.0.price_after', '152000.00')->assertJsonPath('result.pricing.totals.profit_after_commission', '60800.00');
        $this->postJson("/trips/{$trip->id}/scenarios", $input)->assertOk();
        $scenario = $trip->scenarios()->firstOrFail();
        $this->post("/trips/{$trip->id}/scenarios/{$scenario->id}/apply", ['expected_revision' => 5])->assertSessionHasNoErrors();
        $this->assertSame(2, $product->fresh()->cantidad);
        $this->assertSame('152000.00', $product->fresh()->precio_venta);
        $this->assertSame('weight', $expense->fresh()->allocation_method);
        $this->assertSame(1, DB::table('pricing_batches')->count());
        $this->assertSame('228000', $trip->costRuns()->latest('id')->first()->result['total']);
    }

    public function test_foreign_children_scenarios_and_users_are_rejected(): void
    {
        [$trip, $product, $input] = $this->fixture();
        $this->postJson("/trips/{$trip->id}/scenarios", $input)->assertOk();
        $scenario = $trip->scenarios()->firstOrFail();
        $other = Trip::create(['user_id' => auth()->id(), 'nombre' => 'Otro', 'moneda_trabajo' => 'USD', 'moneda_base' => 'CLP', 'tipo_cambio' => '900', 'estado' => 'activo']);
        $this->getJson("/trips/{$other->id}/scenarios/{$scenario->id}")->assertNotFound();
        $this->postJson("/trips/{$other->id}/scenarios/preview", [...$input, 'expected_revision' => 1, 'products' => [['id' => $product->id, 'cantidad' => 2]]])->assertUnprocessable()->assertJsonValidationErrors('products.0.id');
        $this->actingAs(User::factory()->create())->getJson("/trips/{$trip->id}/scenarios/{$scenario->id}")->assertForbidden();
        $this->postJson("/trips/{$trip->id}/scenarios/preview", $input)->assertForbidden();
        $this->post("/trips/{$trip->id}/scenarios/{$scenario->id}/apply", ['expected_revision' => 5])->assertForbidden();
    }

    public function test_paid_amounts_and_invalid_rules_cannot_be_overwritten_by_simulation(): void
    {
        [$trip, $product, $input] = $this->fixture();
        $paid = $trip->expenses()->where('rate_mode', 'paid')->first();
        $this->postJson("/trips/{$trip->id}/scenarios/preview", [...$input, 'expenses' => [['id' => $paid->id, 'monto' => '20']]])->assertUnprocessable()->assertJsonValidationErrors('expenses.0.monto');
        $this->postJson("/trips/{$trip->id}/scenarios/preview", [...$input, 'expenses' => [['id' => $paid->id, 'allocation_method' => 'weight']]])->assertUnprocessable()->assertJsonValidationErrors('allocation_method');
        $this->assertSame(5, $trip->fresh()->revision);
        $this->assertSame('10.00', $paid->fresh()->monto);
    }

    public function test_closed_import_can_simulate_but_cannot_apply_and_saved_input_is_immutable(): void
    {
        [$trip, $product, $input] = $this->fixture();
        $this->patch("/trips/{$trip->id}/close", ['expected_revision' => 5])->assertSessionHasNoErrors();
        $input['expected_revision'] = 6;
        $this->postJson("/trips/{$trip->id}/scenarios/preview", $input)->assertOk();
        $this->postJson("/trips/{$trip->id}/scenarios", $input)->assertOk();
        $scenario = $trip->scenarios()->firstOrFail();
        $this->post("/trips/{$trip->id}/scenarios/{$scenario->id}/apply", ['expected_revision' => 6])->assertForbidden();
        $this->assertNull($scenario->fresh()->applied_at);
        $this->expectException(\LogicException::class);
        $scenario->update(['result' => []]);
    }

    public function test_paid_purchase_is_not_revalued_and_cannot_change_quantity_in_scenario(): void
    {
        [$trip, $product, $input] = $this->fixture();
        $this->put("/trips/{$trip->id}/products/{$product->id}", ['nombre' => $product->nombre, 'cantidad' => 1, 'moneda' => 'USD', 'costo_unitario' => '100', 'rate_mode' => 'paid', 'applied_rate' => '800', 'rate_source' => 'Banco QA', 'rate_date' => '2026-10-03', 'expected_revision' => 5])->assertSessionHasNoErrors();
        $input['expected_revision'] = 6;
        $this->postJson("/trips/{$trip->id}/scenarios/preview", $input)->assertOk()->assertJsonPath('result.before.cost', '97000')->assertJsonPath('result.after.cost', '100000')->assertJsonPath('result.paid_products_preserved', 1);
        $this->postJson("/trips/{$trip->id}/scenarios/preview", [...$input, 'products' => [['id' => $product->id, 'cantidad' => 2]]])->assertUnprocessable()->assertJsonValidationErrors('products.0.cantidad');
        $this->assertSame(1, $product->fresh()->cantidad);
    }

    public function test_failure_after_cost_calculation_rolls_back_scenario_prices_rates_and_metadata(): void
    {
        [$trip, $product, $input] = $this->fixture();
        $input['pricing'] = ['method' => 'margin', 'target' => '20', 'commission' => '0', 'quantum' => 1, 'product_ids' => [$product->id]];
        $this->postJson("/trips/{$trip->id}/scenarios", $input)->assertOk();
        $scenario = $trip->scenarios()->firstOrFail();
        $runs = $trip->costRuns()->count();
        DB::listen(function ($query) {
            if (str_contains($query->sql, 'insert into "cost_runs"')) {
                throw new \RuntimeException('Synthetic recording failure');
            }
        });
        $this->withoutExceptionHandling();
        try {
            $this->post("/trips/{$trip->id}/scenarios/{$scenario->id}/apply", ['expected_revision' => 5]);
            $this->fail('Failure expected');
        } catch (\RuntimeException $e) {
            $this->assertSame('Synthetic recording failure', $e->getMessage());
        }
        $this->assertSame(5, $trip->fresh()->revision);
        $this->assertSame('900.00000000', $trip->fresh()->tipo_cambio);
        $this->assertSame('107000.00', $product->fresh()->costo_final_total_clp);
        $this->assertSame('200000.00', $product->fresh()->precio_venta);
        $this->assertNull($scenario->fresh()->applied_at);
        $this->assertSame(0, DB::table('pricing_batches')->count());
        $this->assertSame($runs, $trip->costRuns()->count());
    }

    public function test_scenario_list_is_paginated_and_excludes_full_result_payloads(): void
    {
        [$trip, $product, $input] = $this->fixture();
        for ($i = 1; $i <= 21; $i++) {
            $this->postJson("/trips/{$trip->id}/scenarios", [...$input, 'idempotency_key' => (string) Str::uuid(), 'name' => "Escenario {$i}"])->assertOk();
        }
        $this->getJson("/trips/{$trip->id}/scenarios")->assertOk()->assertJsonCount(20, 'data')->assertJsonPath('total', 21)->assertJsonMissingPath('data.0.result')->assertJsonMissingPath('data.0.input');
        $this->getJson("/trips/{$trip->id}/scenarios?page=2")->assertOk()->assertJsonCount(1, 'data');
    }
}
