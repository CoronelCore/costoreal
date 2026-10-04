<?php

namespace Tests\Unit;

use App\Modules\Pricing\Domain\TargetPrice;
use DomainException;
use PHPUnit\Framework\TestCase;

class TargetPriceTest extends TestCase
{
    public function test_margin_and_markup_are_distinct_and_match_reference_examples(): void
    {
        $calculator = new TargetPrice;
        $margin = $calculator->calculate('30000', 1, 'margin', '20');
        $markup = $calculator->calculate('30000', 1, 'markup', '5');
        $this->assertSame('37500', $margin['price']);
        $this->assertSame('20.0000', $margin['margin_after_commission']);
        $this->assertSame('31500', $markup['price']);
        $this->assertSame('5.0000', $markup['markup_after_commission']);
    }

    public function test_commission_rounding_and_fractional_unit_cost_preserve_target(): void
    {
        $r = (new TargetPrice)->calculate('90000', 3, 'margin', '20', '5', 1000);
        $this->assertSame('40000', $r['price']);
        $this->assertSame('6000.00', $r['commission_amount']);
        $this->assertSame('24000.00', $r['profit_after_commission']);
        $this->assertSame('20.0000', $r['margin_after_commission']);
        $r = (new TargetPrice)->calculate('1', 3, 'margin', '0', '0', 1);
        $this->assertSame('1', $r['price']);
        $this->assertSame('2.00', $r['profit_after_commission']);
    }

    public function test_invalid_targets_and_prices_fail_explicitly(): void
    {
        foreach ([['30000', 1, 'margin', '95', '5'], ['0', 1, 'margin', '20'], ['99999999999', 1, 'margin', '20'], ['30000', 0, 'markup', '5'], ['30000', 1, 'margin', '-1']] as $args) {
            try {
                (new TargetPrice)->calculate(...$args);
                $this->fail('Invalid target accepted');
            } catch (DomainException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
