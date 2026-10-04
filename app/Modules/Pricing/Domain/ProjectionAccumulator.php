<?php

namespace App\Modules\Pricing\Domain;

use App\Modules\Costing\Domain\DecimalSum;
use Brick\Math\RoundingMode;

final class ProjectionAccumulator
{
    private DecimalSum $revenue;

    private DecimalSum $coveredCost;

    private int $count = 0;

    private int $priced = 0;

    private int $units = 0;

    private int $pricedUnits = 0;

    public function __construct()
    {
        $this->revenue = new DecimalSum;
        $this->coveredCost = new DecimalSum;
    }

    public function add(object $p): void
    {
        $this->count++;
        $this->units += $p->cantidad;
        if ($p->precio_venta === null || ! DecimalSum::positive((string) $p->precio_venta)) {
            return;
        }
        $this->priced++;
        $this->pricedUnits += $p->cantidad;
        $this->revenue->add((string) $p->precio_venta, $p->cantidad);
        $this->coveredCost->add((string) $p->costo_final_total_clp);
    }

    public function merge(self $other): void
    {
        $this->count += $other->count;
        $this->priced += $other->priced;
        $this->units += $other->units;
        $this->pricedUnits += $other->pricedUnits;
        $this->revenue->add((string) $other->revenue->value());
        $this->coveredCost->add((string) $other->coveredCost->value());
    }

    public function summary(string $unassigned = '0'): array
    {
        $revenue = $this->revenue->value();
        $cost = $this->coveredCost->value();
        $profit = $revenue->minus($cost);
        $fullProfit = $profit->minus($unassigned);

        return ['lines' => $this->count, 'priced_lines' => $this->priced, 'units' => $this->units, 'priced_units' => $this->pricedUnits, 'complete' => $this->count > 0 && $this->count === $this->priced,
            'covered_revenue' => (string) $revenue->toScale(0, RoundingMode::HALF_UP), 'covered_cost' => (string) $cost,
            'covered_profit' => (string) $profit->toScale(0, RoundingMode::HALF_UP),
            'revenue' => $this->count > 0 && $this->count === $this->priced ? (string) $revenue->toScale(0, RoundingMode::HALF_UP) : null,
            'profit' => $this->count > 0 && $this->count === $this->priced ? (string) $fullProfit->toScale(0, RoundingMode::HALF_UP) : null,
            'margin' => $revenue->isGreaterThan(0) ? (string) ($this->count === $this->priced ? $fullProfit : $profit)->multipliedBy(100)->dividedBy($revenue, 2, RoundingMode::HALF_UP) : null];
    }
}
