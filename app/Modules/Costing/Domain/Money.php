<?php

namespace App\Modules\Costing\Domain;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use DomainException;

final readonly class Money
{
    private function __construct(public BigDecimal $amount, public string $currency) {}

    public static function of(string|int $amount, string $currency): self
    {
        Currency::supported($currency);

        return new self(BigDecimal::of($amount), $currency);
    }

    public function plus(self $other): self
    {
        if ($this->currency !== $other->currency) {
            throw new DomainException('No se pueden sumar monedas distintas sin conversión.');
        }

        return new self($this->amount->plus($other->amount), $this->currency);
    }

    public function rounded(int $scale = 0): self
    {
        return new self($this->amount->toScale($scale, RoundingMode::HALF_UP), $this->currency);
    }

    public function toArray(): array
    {
        return ['amount' => (string) $this->amount, 'currency' => $this->currency];
    }
}
