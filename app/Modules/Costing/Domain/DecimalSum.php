<?php

namespace App\Modules\Costing\Domain;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/** Exact accumulation: native integer cents when safe, arbitrary precision otherwise. */
final class DecimalSum
{
    private int $minor = 0;

    private int $scale = 0;

    private ?BigDecimal $decimal = null;

    public function add(string $value, int $quantity = 1): void
    {
        if ($this->decimal === null && $quantity >= 0 && preg_match('/^(-?)(\d+)(?:\.(\d{1,2}))?$/D', $value, $parts)) {
            $fraction = $parts[3] ?? '';
            $digits = ltrim($parts[2].str_pad($fraction, 2, '0'), '0') ?: '0';
            $limit = (string) PHP_INT_MAX;
            if (strlen($digits) < strlen($limit) || (strlen($digits) === strlen($limit) && strcmp($digits, $limit) <= 0)) {
                $incoming = (int) $digits;
                if ($quantity === 0 || $incoming <= intdiv(PHP_INT_MAX, $quantity)) {
                    $incoming *= $quantity;
                    if ($parts[1] === '-') {
                        $incoming = -$incoming;
                    }
                    if (($incoming >= 0 && $this->minor <= PHP_INT_MAX - $incoming)
                        || ($incoming < 0 && $this->minor >= PHP_INT_MIN - $incoming)) {
                        $this->minor += $incoming;
                        $this->scale = max($this->scale, strlen($fraction));

                        return;
                    }
                }
            }
        }
        $this->decimal = $this->value()->plus(BigDecimal::of($value)->multipliedBy($quantity));
    }

    public function value(): BigDecimal
    {
        if ($this->decimal !== null) {
            return $this->decimal;
        }
        // String conversion also handles PHP_INT_MIN without abs() becoming a float.
        $digits = str_pad(ltrim((string) $this->minor, '-'), 3, '0', STR_PAD_LEFT);
        $text = ($this->minor < 0 ? '-' : '').substr($digits, 0, -2).'.'.substr($digits, -2);

        return BigDecimal::of($text)->toScale($this->scale);
    }

    /** Match Eloquent decimal:2 casts, including legacy scientific/fractional values. */
    public static function money(string|int|float $value): string
    {
        $text = (string) $value;
        if (preg_match('/^(-?)(\d+)(?:\.(\d{1,2}))?$/D', $text, $parts)) {
            $integer = ltrim($parts[2], '0') ?: '0';
            $fraction = str_pad($parts[3] ?? '', 2, '0');

            return ($parts[1] === '-' && ($integer !== '0' || $fraction !== '00') ? '-' : '').$integer.'.'.$fraction;
        }

        return (string) BigDecimal::of($text)->toScale(2, RoundingMode::HALF_UP);
    }

    public static function positive(string $value): bool
    {
        if (preg_match('/^(-?)(\d+)(?:\.(\d+))?$/D', $value, $parts)) {
            return $parts[1] !== '-' && trim($parts[2].($parts[3] ?? ''), '0') !== '';
        }

        return BigDecimal::of($value)->isGreaterThan(0);
    }
}
