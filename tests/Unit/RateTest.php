<?php

namespace Tests\Unit;

use App\Modules\Costing\Domain\Currency;
use App\Modules\Costing\Domain\Money;
use App\Modules\Costing\Domain\Rate;
use DomainException;
use PHPUnit\Framework\TestCase;

final class RateTest extends TestCase
{
    public function test_decimal_rate_and_line_rounding_are_exact(): void
    {
        $rate = new Rate(Currency::USD, '900.12345678');
        $this->assertSame('270.0370370340', (string) $rate->convert(Money::of('0.30', 'USD'))->amount);
        $this->assertSame('270', (string) $rate->convert(Money::of('0.30', 'USD'))->rounded()->amount);
    }

    public function test_invalid_unknown_and_mismatched_rates_fail(): void
    {
        foreach (['0', '-1', '1000001', '1.123456789', ''] as $value) {
            try {
                new Rate(Currency::USD, $value);
                $this->fail('Invalid rate accepted');
            } catch (DomainException) {
                $this->addToAssertionCount(1);
            }
        }
        foreach ([fn () => Money::of('1', 'XYZ'), fn () => new Rate(Currency::CLP, '2'), fn () => (new Rate(Currency::USD, '900'))->convert(Money::of('1', 'EUR'))] as $invalid) {
            try {
                $invalid();
                $this->fail('Invalid currency accepted');
            } catch (DomainException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
