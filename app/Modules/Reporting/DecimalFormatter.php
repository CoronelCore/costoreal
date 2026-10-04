<?php

namespace App\Modules\Reporting;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

final class DecimalFormatter
{
    public static function format(string|int $value, int $scale = 0): string
    {
        $rounded = BigDecimal::of($value)->toScale($scale, RoundingMode::HALF_UP);
        $negative = $rounded->isNegative();
        [$whole, $fraction] = array_pad(explode('.', (string) $rounded->abs()), 2, '');
        $grouped = preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $whole);

        return ($negative ? '-' : '').$grouped.($scale ? ','.$fraction : '');
    }
}
