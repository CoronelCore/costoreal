<?php

namespace App\Modules\Pricing\Domain;

use Brick\Math\BigRational;
use Brick\Math\RoundingMode;
use DomainException;

/** Margen sobre venta bruta; recargo sobre costo. Comisión sobre venta bruta. */
final class TargetPrice
{
    public function calculate(string $lineCost, int $quantity, string $method, string $target, string $commission = '0', int $quantum = 1): array
    {
        if ($quantity < 1 || ! in_array($method, ['margin', 'markup'], true) || ! in_array($quantum, [1, 10, 100, 1000], true)) {
            throw new DomainException('La cantidad, el método y el redondeo deben ser válidos.');
        }
        $cost = BigRational::of($lineCost);
        $goal = BigRational::of($target)->dividedBy(100);
        $fee = BigRational::of($commission)->dividedBy(100);
        if ($cost->isLessThanOrEqualTo(0)) {
            throw new DomainException('Un costo cero requiere fijar el precio manualmente.');
        }
        if ($goal->isLessThan(0) || $goal->isGreaterThan(10) || $fee->isLessThan(0) || $fee->isGreaterThanOrEqualTo(1)) {
            throw new DomainException('El objetivo o la comisión están fuera de rango.');
        }
        $denominator = BigRational::one()->minus($fee);
        if ($method === 'margin') {
            $denominator = $denominator->minus($goal);
            if ($denominator->isLessThanOrEqualTo(0)) {
                throw new DomainException('Margen y comisión deben sumar menos del 100 %.');
            }
        }
        $price = $cost->dividedBy($quantity)->dividedBy($denominator);
        if ($method === 'markup') {
            $price = $price->multipliedBy(BigRational::one()->plus($goal));
        }
        $rounded = $price->dividedBy($quantum)->toScale(0, RoundingMode::CEILING)->multipliedBy($quantum);
        if ($rounded->isGreaterThan('9999999999.99')) {
            throw new DomainException('El precio calculado supera el límite permitido.');
        }
        $revenue = $rounded->multipliedBy($quantity);
        $profit = BigRational::of($revenue)->multipliedBy(BigRational::one()->minus($fee))->minus($cost);
        $format = fn (BigRational $value, int $scale) => (string) $value->toScale($scale, RoundingMode::HALF_UP);

        return ['price' => (string) $rounded, 'revenue' => (string) $revenue, 'commission_amount' => $format(BigRational::of($revenue)->multipliedBy($fee), 2),
            'profit_after_commission' => $format($profit, 2), 'margin_after_commission' => $format($profit->dividedBy($revenue)->multipliedBy(100), 4),
            'markup_after_commission' => $format($profit->dividedBy($cost)->multipliedBy(100), 4)];
    }
}
