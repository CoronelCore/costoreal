<?php

namespace Tests\Unit;

use App\Modules\Costing\Domain\DecimalSum;
use App\Modules\Pricing\Domain\ProjectionAccumulator;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use PHPUnit\Framework\TestCase;

class DecimalSumTest extends TestCase
{
    public function test_signed_fractional_totals_match_arbitrary_precision_after_every_addition(): void
    {
        $sum = new DecimalSum;
        $expected = BigDecimal::zero();
        // Deterministic range of signs, quantities and mixed presentation scales.
        for ($i = 0; $i < 1000; $i++) {
            $value = ($i % 3 ? '' : '-').(string) (($i * 7919) % 99991);
            $value .= match ($i % 3) {
                0 => '', 1 => '.'.($i % 10), 2 => '.'.str_pad((string) ($i % 100), 2, '0', STR_PAD_LEFT)
            };
            $quantity = ($i * 13) % 1000;
            $sum->add($value, $quantity);
            $expected = $expected->plus(BigDecimal::of($value)->multipliedBy($quantity));
            $this->assertSame((string) $expected, (string) $sum->value());
        }
    }

    public function test_integer_boundaries_product_overflow_and_non_cent_values_fall_back_exactly(): void
    {
        foreach ([
            [['92233720368547758.07', 1], ['0.01', 1], ['-0.01', 1]],
            [['-92233720368547758.07', 1], ['-0.01', 1], ['-0.01', 1], ['0.02', 1]],
            [['9000000000000000.01', 1000000], ['-0.015', 1]],
            [['100.10', PHP_INT_MAX], ['1E-8', 1]],
            [['0.10', 2], ['2.12345678', 3], ['-0.00000001', 9]],
            [['001.20', 1], ['+1.5', 1], ['1e3', 1], ['2', -3]],
        ] as $values) {
            $sum = new DecimalSum;
            $expected = BigDecimal::zero();
            foreach ($values as [$value, $quantity]) {
                $sum->add($value, $quantity);
                $expected = $expected->plus(BigDecimal::of($value)->multipliedBy($quantity));
                $this->assertSame((string) $expected, (string) $sum->value());
            }
        }
    }

    public function test_storage_normalization_and_positive_checks_match_decimal_cast_semantics(): void
    {
        foreach (['0', '-0.00', '001.20', '9000000000000000.01', '0.005', '-0.005', '1e3', '+1.5', '0.00000001', '123.456789', 10.1, -0.015] as $value) {
            $decimal = BigDecimal::of((string) $value);
            $this->assertSame((string) $decimal->toScale(2, RoundingMode::HALF_UP), DecimalSum::money($value));
            $this->assertSame($decimal->isGreaterThan(0), DecimalSum::positive((string) $value));
        }
    }

    public function test_projection_cohorts_match_independent_decimal_oracle_without_rounding_each_line(): void
    {
        $cohorts = [new ProjectionAccumulator, new ProjectionAccumulator];
        $revenue = BigDecimal::zero();
        $cost = BigDecimal::zero();
        $units = 0;
        for ($i = 0; $i < 500; $i++) {
            $quantity = $i + 1;
            $price = $i % 2 ? '9000000000000000.01' : '0.015';
            $lineCost = $i % 2 ? '9000000000000000.02' : '0.12345678';
            $cohorts[$i % 2]->add((object) ['cantidad' => $quantity, 'precio_venta' => $price, 'costo_final_total_clp' => $lineCost]);
            $units += $quantity;
            $revenue = $revenue->plus(BigDecimal::of($price)->multipliedBy($quantity));
            $cost = $cost->plus($lineCost);
        }
        $cohorts[0]->merge($cohorts[1]);
        $summary = $cohorts[0]->summary('0.05');
        $profit = $revenue->minus($cost);
        $this->assertSame($units, $summary['units']);
        $this->assertSame(500, $summary['priced_lines']);
        $this->assertSame((string) $cost, $summary['covered_cost']);
        $this->assertSame((string) $revenue->toScale(0, RoundingMode::HALF_UP), $summary['covered_revenue']);
        $this->assertSame((string) $profit->toScale(0, RoundingMode::HALF_UP), $summary['covered_profit']);
        $this->assertSame((string) $profit->minus('0.05')->toScale(0, RoundingMode::HALF_UP), $summary['profit']);
        $this->assertSame((string) $profit->minus('0.05')->multipliedBy(100)->dividedBy($revenue, 2, RoundingMode::HALF_UP), $summary['margin']);
    }
}
