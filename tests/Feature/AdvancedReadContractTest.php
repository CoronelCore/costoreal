<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Trip;
use App\Models\User;
use App\Modules\Audit\AuditEventData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdvancedReadContractTest extends TestCase
{
    use RefreshDatabase;

    private function trip(): Trip
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        return Trip::create(['user_id' => $user->id, 'nombre' => 'Contratos avanzados',
            'moneda_trabajo' => 'USD', 'moneda_base' => 'CLP', 'tipo_cambio' => '900', 'estado' => 'activo']);
    }

    private function product(Trip $trip): int
    {
        $this->post("/trips/{$trip->id}/products", ['nombre' => 'Sensor', 'cantidad' => 2, 'moneda' => 'USD',
            'costo_unitario' => '100', 'expected_revision' => $trip->fresh()->revision])->assertSessionHasNoErrors();

        return $trip->products()->firstOrFail()->id;
    }

    public function test_bulk_preview_returns_error_objects_and_original_invalid_text_without_writes(): void
    {
        $trip = $this->trip();
        $input = ['expected_revision' => 1, 'source_text' => "nombre,cantidad,moneda,costo_unitario,peso\nSensor,2,USD,10.25,2.12345678\nInválido,0,XYZ,=100,texto",
            'delimiter' => ',', 'has_header' => true, 'default_currency' => 'USD',
            'mapping' => ['nombre' => 0, 'cantidad' => 1, 'moneda' => 2, 'costo_unitario' => 3, 'weight' => 4, 'volume' => null]];
        $response = $this->postJson("/trips/{$trip->id}/products/import/preview", $input)->assertOk()
            ->assertJsonPath('can_apply', false)->assertJsonPath('totals', null)->assertJsonPath('rows.0.data.weight', '2.12345678')
            ->assertJsonPath('rows.1.data.costo_unitario', '=100');
        $decoded = json_decode($response->getContent());
        $this->assertIsObject($decoded->rows[0]->errors);
        $this->assertSame([], get_object_vars($decoded->rows[0]->errors));
        $this->assertIsObject($decoded->cost_errors);
        $this->assertNotEmpty(get_object_vars($decoded->rows[1]->errors));
        $this->assertSame('La cantidad debe ser al menos 1.', $decoded->rows[1]->errors->cantidad[0]);
        $this->assertSame('El peso total debe ser un número; usa punto decimal.', $decoded->rows[1]->errors->weight[0]);
        $this->assertSame('El peso total admite 0-8 decimales.', $decoded->rows[1]->errors->weight[1]);
        $this->assertSame(1, $trip->fresh()->revision);
        $this->assertSame(0, $trip->products()->count());
        $this->assertSame(0, $trip->costRuns()->count());
    }

    public function test_numeric_legacy_scenarios_have_safe_decimal_list_contracts_without_rewriting_history(): void
    {
        $trip = $this->trip();
        $id = $this->product($trip);
        $input = ['expected_revision' => 2, 'name' => 'Cliente anterior', 'idempotency_key' => (string) Str::uuid(),
            'reference_rate' => 950.12345678, 'expense_variation_percent' => 20.25,
            'products' => ['legacy' => ['id' => (string) $id, 'cantidad' => 3, 'costo_unitario' => 100.25, 'weight' => 2.12345678, 'volume' => null]],
            'expenses' => [], 'pricing' => ['method' => 'margin', 'target' => 20.25, 'commission' => 1.25, 'quantum' => 10, 'product_ids' => ['selected' => (string) $id]]];
        $scenario = $this->postJson("/trips/{$trip->id}/scenarios", $input)->assertOk()
            ->assertJsonPath('scenario.input.reference_rate', '950.12345678')->assertJsonPath('scenario.input.products.0.costo_unitario', '100.25')
            ->assertJsonPath('scenario.input.products.0.weight', '2.12345678')->assertJsonPath('scenario.input.products.0.volume', null)
            ->assertJsonPath('scenario.input.pricing.product_ids.0', $id)->assertJsonPath('scenario.input.pricing.target', '20.25')->json('scenario');
        foreach (['trip_id', 'actor_id', 'input_hash', 'baseline_hash', 'idempotency_key'] as $private) {
            $this->assertArrayNotHasKey($private, $scenario);
        }
        $before = DB::table('cost_scenarios')->where('id', $scenario['id'])->first();
        $persisted = json_decode($before->input, true);
        $this->assertArrayHasKey('legacy', $persisted['products']);
        $this->assertIsFloat($persisted['reference_rate']);
        $this->getJson("/trips/{$trip->id}/scenarios/{$scenario['id']}")->assertOk()->assertJsonPath('scenario', $scenario);
        $this->getJson("/trips/{$trip->id}/scenarios")->assertOk()->assertJsonMissingPath('data.0.result')
            ->assertJsonMissingPath('data.0.input')->assertJsonMissingPath('data.0.trip_id')->assertJsonMissingPath('data.0.actor_id');
        $this->assertEquals($before, DB::table('cost_scenarios')->where('id', $scenario['id'])->first());
        $this->assertSame(2, $trip->fresh()->revision);
        $this->assertSame('900.00000000', $trip->fresh()->tipo_cambio);
        $this->assertNull($trip->products()->first()->precio_venta);
        $this->assertSame(1, $trip->costRuns()->count());
    }

    public function test_numeric_pricing_preview_returns_text_money_and_sequential_selected_ids_without_saving(): void
    {
        $trip = $this->trip();
        $id = $this->product($trip);
        $preview = $this->postJson("/trips/{$trip->id}/pricing/preview", ['expected_revision' => 2, 'method' => 'margin',
            'target' => 20.25, 'commission' => 1.25, 'quantum' => 10, 'product_ids' => ['old' => (string) $id]])->assertOk()
            ->assertJsonPath('policy.product_ids.0', $id)->assertJsonPath('policy.target', '20.25')->assertJsonPath('policy.commission', '1.25')->json();
        foreach (['cost', 'price', 'revenue', 'commission_amount', 'profit_after_commission', 'margin_after_commission', 'markup_after_commission'] as $field) {
            $this->assertIsString($preview['lines'][0][$field]);
        }
        $this->assertNull($preview['lines'][0]['previous_price']);
        $this->assertSame(2, $trip->fresh()->revision);
        $this->assertNull($trip->products()->first()->precio_venta);
        $this->assertSame(0, DB::table('pricing_batches')->count());
    }

    public function test_audit_normalizes_legacy_summary_at_read_time_and_keeps_null_states(): void
    {
        $trip = $this->trip();
        $event = AuditEvent::create(['trip_id' => $trip->id, 'actor_id' => auth()->id(), 'action' => 'legacy', 'revision' => 1,
            'before' => [], 'after' => ['tipo_cambio' => 950.12345678, 'costo_total' => 100.25, 'products' => [], 'expenses' => [], 'documents' => []], 'created_at' => now()]);
        $before = DB::table('audit_events')->where('id', $event->id)->first();
        $this->getJson("/trips/{$trip->id}/audit-events")->assertOk()
            ->assertJsonPath('data.0.summary.after.reference_rate', '950.12345678')->assertJsonMissingPath('data.0.actor_id')
            ->assertJsonMissingPath('data.0.before')->assertJsonMissingPath('data.0.after');
        $this->getJson("/trips/{$trip->id}/audit-events/{$event->id}")->assertOk()->assertJsonPath('summary.after.reference_rate', '950.12345678');
        $this->assertEquals($before, DB::table('audit_events')->where('id', $event->id)->first());
        $this->assertNull(AuditEventData::states(null));
        $this->assertSame(['before' => null, 'after' => null], AuditEventData::states(['before' => null, 'after' => null]));
    }
}
