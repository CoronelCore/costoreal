<?php

namespace App\Modules\Pricing\Domain;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

final class Projection
{
    public function summarize(iterable $products, string $unassigned = '0'): array
    {
        $revenue = BigDecimal::zero();
        $coveredCost = BigDecimal::zero();
        $count = 0;
        $priced = 0;
        $units = 0;
        $pricedUnits = 0;
        foreach ($products as $p) {
            $count++;
            $units += $p->cantidad;
            if ($p->precio_venta === null || BigDecimal::of($p->precio_venta)->isLessThanOrEqualTo(0)) {
                continue;
            }
            $priced++;
            $pricedUnits += $p->cantidad;
            $revenue = $revenue->plus(BigDecimal::of($p->precio_venta)->multipliedBy($p->cantidad));
            $coveredCost = $coveredCost->plus($p->costo_final_total_clp);
        }
        $profit = $revenue->minus($coveredCost);
        $fullProfit = $profit->minus($unassigned);

        return ['lines' => $count, 'priced_lines' => $priced, 'units' => $units, 'priced_units' => $pricedUnits, 'complete' => $count > 0 && $count === $priced,
            'covered_revenue' => (string) $revenue->toScale(0, RoundingMode::HALF_UP), 'covered_cost' => (string) $coveredCost,
            'covered_profit' => (string) $profit->toScale(0, RoundingMode::HALF_UP),
            'revenue' => $count > 0 && $count === $priced ? (string) $revenue->toScale(0, RoundingMode::HALF_UP) : null,
            'profit' => $count > 0 && $count === $priced ? (string) $fullProfit->toScale(0, RoundingMode::HALF_UP) : null,
            'margin' => $revenue->isGreaterThan(0) ? (string) ($count === $priced ? $fullProfit : $profit)->multipliedBy(100)->dividedBy($revenue, 2, RoundingMode::HALF_UP) : null];
    }
}
