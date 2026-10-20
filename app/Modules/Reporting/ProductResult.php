<?php

namespace App\Modules\Reporting;

use App\Modules\Costing\Domain\DecimalSum;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/** Per-unit view of a costed line: paid, assigned expenses, real cost and projected profit. */
final class ProductResult
{
    public static function of(object $product): array
    {
        $quantity = (int) $product->cantidad;
        $total = BigDecimal::of((string) $product->costo_final_total_clp);
        $perUnit = fn (string $amount): string => (string) BigDecimal::of($amount)->dividedBy($quantity, 8, RoundingMode::HALF_UP);
        $price = $product->precio_venta !== null && DecimalSum::positive((string) $product->precio_venta) ? (string) $product->precio_venta : null;
        $result = [
            'compra_unitaria_clp' => $perUnit((string) $product->subtotal_clp),
            'gastos_unitarios_clp' => $perUnit((string) $product->gastos_prorrateados_clp),
            'costo_unitario_clp' => (string) $product->costo_final_unitario_clp,
            'precio_venta' => $price,
            'ganancia_unitaria_clp' => null, 'ganancia_total_clp' => null, 'margen' => null,
        ];
        if ($price !== null) {
            $revenue = BigDecimal::of($price)->multipliedBy($quantity);
            $profit = $revenue->minus($total);
            $result['ganancia_unitaria_clp'] = (string) $profit->dividedBy($quantity, 8, RoundingMode::HALF_UP);
            $result['ganancia_total_clp'] = (string) $profit->toScale(0, RoundingMode::HALF_UP);
            $result['margen'] = (string) $profit->multipliedBy(100)->dividedBy($revenue, 2, RoundingMode::HALF_UP);
        }

        return $result;
    }
}
