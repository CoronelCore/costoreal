<?php

namespace App\Modules\Pricing\Domain;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

final class ProjectionAccumulator
{
    private BigDecimal $revenue;

    private BigDecimal $coveredCost;

    private int $count = 0;

    private int $priced = 0;

    private int $units = 0;

    private int $pricedUnits = 0;

    public function __construct()
    {
        $this->revenue = BigDecimal::zero();
        $this->coveredCost = BigDecimal::zero();
    }

    public function add(object $p): void
    {
        $this->count++;
        $this->units += $p->cantidad;
        if ($p->precio_venta === null || BigDecimal::of($p->precio_venta)->isLessThanOrEqualTo(0)) {
            return;
        }
        $this->priced++;
        $this->pricedUnits += $p->cantidad;
        $this->revenue = $this->revenue->plus(BigDecimal::of($p->precio_venta)->multipliedBy($p->cantidad));
        $this->coveredCost = $this->coveredCost->plus($p->costo_final_total_clp);
    }

    public function merge(self $other): void
    {
        $this->count += $other->count;
        $this->priced += $other->priced;
        $this->units += $other->units;
        $this->pricedUnits += $other->pricedUnits;
        $this->revenue = $this->revenue->plus($other->revenue);
        $this->coveredCost = $this->coveredCost->plus($other->coveredCost);
    }

    public function summary(string $unassigned = '0'): array
    {
        $profit = $this->revenue->minus($this->coveredCost);
        $fullProfit = $profit->minus($unassigned);

        return ['lines' => $this->count, 'priced_lines' => $this->priced, 'units' => $this->units, 'priced_units' => $this->pricedUnits, 'complete' => $this->count > 0 && $this->count === $this->priced,
            'covered_revenue' => (string) $this->revenue->toScale(0, RoundingMode::HALF_UP), 'covered_cost' => (string) $this->coveredCost,
            'covered_profit' => (string) $profit->toScale(0, RoundingMode::HALF_UP),
            'revenue' => $this->count > 0 && $this->count === $this->priced ? (string) $this->revenue->toScale(0, RoundingMode::HALF_UP) : null,
            'profit' => $this->count > 0 && $this->count === $this->priced ? (string) $fullProfit->toScale(0, RoundingMode::HALF_UP) : null,
            'margin' => $this->revenue->isGreaterThan(0) ? (string) ($this->count === $this->priced ? $fullProfit : $profit)->multipliedBy(100)->dividedBy($this->revenue, 2, RoundingMode::HALF_UP) : null];
    }
}
