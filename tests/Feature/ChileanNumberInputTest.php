<?php

namespace Tests\Feature;

use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChileanNumberInputTest extends TestCase
{
    use RefreshDatabase;

    public function test_rates_amounts_costs_and_prices_typed_the_chilean_way_are_stored_exactly(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $trip = Trip::create(['user_id' => $user->id, 'nombre' => 'Viaje QA', 'moneda_trabajo' => 'BRL', 'moneda_base' => 'CLP', 'tipo_cambio' => '180', 'estado' => 'activo']);
        // Before this change "1.050" was silently stored as a rate of 1,05 pesos.
        $this->put("/trips/{$trip->id}", ['expected_revision' => 1, 'nombre' => $trip->nombre, 'moneda_trabajo' => 'BRL', 'tipo_cambio' => '1.050'])->assertSessionHasNoErrors();
        $this->assertSame('1050.00000000', $trip->fresh()->tipo_cambio);
        $this->put("/trips/{$trip->id}", ['expected_revision' => 2, 'nombre' => $trip->nombre, 'moneda_trabajo' => 'BRL', 'tipo_cambio' => '175,5'])->assertSessionHasNoErrors();
        $this->assertSame('175.50000000', $trip->fresh()->tipo_cambio);

        $this->post("/trips/{$trip->id}/products", ['nombre' => 'Secador', 'cantidad' => '1.000', 'moneda' => 'BRL', 'costo_unitario' => '1.250,50',
            'weight' => '2,5', 'expected_revision' => 3])->assertSessionHasNoErrors();
        $product = $trip->products()->sole();
        $this->assertSame(1000, $product->cantidad);
        $this->assertSame('1250.50', $product->costo_unitario);
        $this->assertSame('2.5', (string) $product->weight);
        $this->assertSame('219462750.00', $product->subtotal_clp);

        $this->post("/trips/{$trip->id}/expenses", ['concepto' => 'Pasaje', 'moneda' => 'CLP', 'monto' => '450.000', 'expected_revision' => 4])->assertSessionHasNoErrors();
        $this->assertSame('450000.00', $trip->expenses()->sole()->monto);

        $this->patch("/trips/{$trip->id}/products/{$product->id}/precio-venta", ['precio_venta' => '$ 12.990', 'expected_revision' => 5])->assertSessionHasNoErrors();
        $this->assertSame('12990.00', $product->fresh()->precio_venta);
    }

    public function test_an_amount_that_is_still_not_a_number_explains_how_to_write_it(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $trip = Trip::create(['user_id' => $user->id, 'nombre' => 'Viaje QA', 'moneda_trabajo' => 'USD', 'moneda_base' => 'CLP', 'tipo_cambio' => '900', 'estado' => 'activo']);
        $this->put("/trips/{$trip->id}", ['expected_revision' => 1, 'nombre' => $trip->nombre, 'moneda_trabajo' => 'USD', 'tipo_cambio' => 'novecientos'])
            ->assertSessionHasErrors(['tipo_cambio' => 'Escribe la tasa como un número, por ejemplo 950 o 950,5.']);
        $this->assertSame('900.00000000', $trip->fresh()->tipo_cambio);
    }
}
