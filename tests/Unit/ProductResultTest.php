<?php

namespace Tests\Unit;

use App\Modules\Reporting\DecimalFormatter;
use App\Modules\Reporting\ProductResult;
use PHPUnit\Framework\TestCase;

class ProductResultTest extends TestCase
{
    private function line(?string $price): object
    {
        // Demo hub: 20 units at USD 35 × 900, with 176.954 CLP of assigned expenses.
        return (object) ['cantidad' => 20, 'subtotal_clp' => '630000.00', 'gastos_prorrateados_clp' => '176954.00',
            'costo_final_total_clp' => '806954.00', 'costo_final_unitario_clp' => '40347.70000000', 'precio_venta' => $price];
    }

    public function test_paid_cost_and_profit_per_unit_come_from_the_stored_line_totals(): void
    {
        $this->assertSame([
            'compra_unitaria_clp' => '31500.00000000', 'gastos_unitarios_clp' => '8847.70000000',
            'costo_unitario_clp' => '40347.70000000', 'precio_venta' => '58000.00',
            'ganancia_unitaria_clp' => '17652.30000000', 'ganancia_total_clp' => '353046', 'margen' => '30.44',
        ], ProductResult::of($this->line('58000.00')));
    }

    public function test_missing_or_zero_prices_keep_profit_pending_instead_of_inventing_a_loss(): void
    {
        foreach ([null, '0.00'] as $price) {
            $result = ProductResult::of($this->line($price));
            $this->assertNull($result['precio_venta']);
            $this->assertNull($result['ganancia_unitaria_clp']);
            $this->assertNull($result['ganancia_total_clp']);
            $this->assertNull($result['margen']);
            $this->assertSame('31500.00000000', $result['compra_unitaria_clp']);
        }
    }

    public function test_a_price_below_the_real_cost_keeps_the_loss_sign(): void
    {
        $result = ProductResult::of($this->line('35000'));
        $this->assertSame('-5347.70000000', $result['ganancia_unitaria_clp']);
        $this->assertSame('-106954', $result['ganancia_total_clp']);
        $this->assertSame('-15.28', $result['margen']);
    }

    public function test_rates_are_shown_without_padded_zeros(): void
    {
        $this->assertSame('900', DecimalFormatter::rate('900.00000000'));
        $this->assertSame('950,12', DecimalFormatter::rate('950.12000000'));
        $this->assertSame('0,00012345', DecimalFormatter::rate('0.00012345'));
        $this->assertSame('$31.500', DecimalFormatter::pesos('31500.00000000'));
        $this->assertSame('$40.347,70', DecimalFormatter::pesos('40347.70000000'));
        $this->assertSame('-$5.347,70', DecimalFormatter::pesos('-5347.70000000'));
    }
}
