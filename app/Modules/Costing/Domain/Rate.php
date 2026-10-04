<?php

namespace App\Modules\Costing\Domain;

use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use DomainException;

/** CLP por unidad de moneda original; no admite una tasa implícita. */
final readonly class Rate
{
    public BigDecimal $value;

    public function __construct(public Currency $source, string $value)
    {
        try {
            $decimal = BigDecimal::of($value);
        } catch (MathException $e) {
            throw new DomainException('La tasa debe ser un decimal válido.', previous: $e);
        }
        if ($decimal->isLessThanOrEqualTo(0) || $decimal->isGreaterThan('1000000') || $decimal->getScale() > 8) {
            throw new DomainException('La tasa debe ser positiva, hasta 1.000.000 y con ocho decimales como máximo.');
        }
        if ($source === Currency::CLP && ! $decimal->isEqualTo(1)) {
            throw new DomainException('Una operación en CLP utiliza tasa 1.');
        }
        $this->value = $decimal;
    }

    public function convert(Money $money): Money
    {
        if ($money->currency !== $this->source->value) {
            throw new DomainException('La tasa corresponde a otra moneda original.');
        }

        return Money::of((string) $money->amount->multipliedBy($this->value), Currency::CLP->value);
    }
}
