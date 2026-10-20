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

    /** Per-unit pesos: cents only when the rounded amount has them ($31.500 but $40.347,70). */
    public static function pesos(string|int $value): string
    {
        $formatted = preg_replace('/,00$/', '', self::format($value, 2));

        return str_starts_with($formatted, '-') ? '-$'.substr($formatted, 1) : '$'.$formatted;
    }

    /** Exchange rates keep up to eight decimals without padded zeros: 900 and 950,12. */
    public static function rate(string|int $value): string
    {
        return self::format($value, max(0, BigDecimal::of($value)->toScale(8, RoundingMode::HALF_UP)->strippedOfTrailingZeros()->getScale()));
    }
}
