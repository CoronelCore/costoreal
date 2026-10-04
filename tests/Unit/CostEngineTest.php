<?php

namespace Tests\Unit;

use App\Modules\Costing\Domain\CostEngine;
use App\Modules\Costing\Domain\Money;
use Brick\Math\BigDecimal;
use DomainException;
use PHPUnit\Framework\TestCase;

final class CostEngineTest extends TestCase
{
    private function line(int $id, int $quantity = 1, string $unit = '1', string $rate = '1'): array
    {
        return ['id' => $id, 'quantity' => $quantity, 'unit_amount' => $unit, 'rate' => $rate];
    }

    public function test_residual_is_exact_and_order_independent(): void
    {
        $engine = new CostEngine;
        $lines = [$this->line(10), $this->line(2), $this->line(1)];
        $expenses = [['id' => 1, 'amount_clp' => '2']];
        $result = $engine->calculate($lines, $expenses);
        $this->assertSame(['1', '1', '0'], array_column($result->lines, 'assigned'));
        $this->assertSame($result->toArray(), $engine->calculate(array_reverse($lines), $expenses)->toArray());
        $this->assertSame('0', $result->unassigned);
        $this->assertSame('5', $result->total);
    }

    public function test_decimal_conversion_and_large_values_are_exact(): void
    {
        $result = (new CostEngine)->calculate([$this->line(1, 3, '0.10', '1000.12345678'), $this->line(2, 1000000, '9999999999.99', '1000000')], []);
        $this->assertSame('300', $result->lines[0]['purchase']);
        $this->assertSame('9999999999990000000000', $result->lines[1]['purchase']);
        $this->assertSame('100.00000000', $result->lines[0]['unit_cost']);
    }

    public function test_zero_purchase_keeps_expenses_explicitly_unassigned(): void
    {
        $r = (new CostEngine)->calculate([$this->line(1, 1, '0')], [['id' => 1, 'amount_clp' => '20']]);
        $this->assertSame('20', $r->unassigned);
        $this->assertSame('0', $r->assigned);
        $this->assertSame('20', $r->total);
        $this->assertSame('0', $r->allocatedTotal);
    }

    public function test_alternative_rules_reconcile_and_direct_target_is_explicit(): void
    {
        $lines = [$this->line(1, 1) + ['weight' => '1.25', 'volume' => '2'], $this->line(2, 3) + ['weight' => '3.75', 'volume' => '6']];
        foreach (['units', 'weight', 'volume'] as $method) {
            $r = (new CostEngine)->calculate($lines, [['id' => 1, 'amount_clp' => '100', 'method' => $method]]);
            $this->assertSame(['25', '75'], array_column($r->lines, 'assigned'));
        }
        $r = (new CostEngine)->calculate($lines, [['id' => 1, 'amount_clp' => '100', 'method' => 'direct', 'target_id' => 2]]);
        $this->assertSame(['0', '100'], array_column($r->lines, 'assigned'));
        $r = (new CostEngine)->calculate($lines, [['id' => 1, 'amount_clp' => '100', 'method' => 'direct', 'target_id' => 99]]);
        $this->assertSame('100', $r->unassigned);
    }

    public function test_missing_weight_cannot_silently_fall_back(): void
    {
        $this->expectException(DomainException::class);
        (new CostEngine)->calculate([$this->line(1)], [['id' => 1, 'amount_clp' => '10', 'method' => 'weight']]);
    }

    public function test_invalid_domain_inputs_are_rejected(): void
    {
        foreach ([[$this->line(1, 0)], [$this->line(1, 1, '-1')], [$this->line(1, 1, '1', '0')], [$this->line(1), $this->line(1)]] as $lines) {
            try {
                (new CostEngine)->calculate($lines, []);
                $this->fail('Invalid input accepted');
            } catch (DomainException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_seeded_examples_conserve_every_expense_and_product_total(): void
    {
        mt_srand(1003);
        for ($case = 0; $case < 100; $case++) {
            $lines = [];
            $expenses = [];
            for ($id = 1; $id <= mt_rand(1, 20); $id++) {
                $lines[] = $this->line($id, mt_rand(1, 100), (string) mt_rand(1, 10000), '900.12345678');
            }
            for ($id = 1; $id <= 5; $id++) {
                $expenses[] = ['id' => $id, 'amount_clp' => (string) mt_rand(0, 1000000)];
            }
            $r = (new CostEngine)->calculate($lines, $expenses);
            $sum = array_reduce($r->lines, fn ($s, $line) => $s->plus($line['total']), BigDecimal::zero());
            $this->assertSame($r->total, (string) $sum);
            $this->assertSame($r->expenses, $r->assigned);
            foreach ($expenses as $e) {
                $sum = array_reduce(array_filter($r->toArray()['allocations'], fn ($a) => $a['expense_id'] === $e['id']), fn ($s, $a) => $s->plus($a['amount']), BigDecimal::zero());
                $this->assertSame($e['amount_clp'], (string) $sum);
            }
            $this->assertSame($r->toArray(), (new CostEngine)->calculate(array_reverse($lines), array_reverse($expenses))->toArray());
        }
    }

    public function test_money_refuses_to_add_different_currencies(): void
    {
        $this->expectException(DomainException::class);
        Money::of('10', 'CLP')->plus(Money::of('10', 'USD'));
    }

    public function test_trace_explains_exact_fractions_stable_ties_and_the_residual_award(): void
    {
        $r = (new CostEngine)->calculate([$this->line(10), $this->line(2), $this->line(1)], [['id' => 7, 'amount_clp' => '2']]);
        $this->assertSame([1, 2, 3], array_column(array_column($r->toArray()['allocations'], 'trace'), 'priority'));
        $this->assertSame([1, 1, 0], array_column(array_column($r->toArray()['allocations'], 'trace'), 'residual_award'));
        foreach ($r->allocations as $allocation) {
            $trace = $allocation['trace'];
            $this->assertSame('0', $trace['floor']);
            $this->assertSame('2', $trace['remainder_numerator']);
            $this->assertSame('3', $trace['remainder_denominator']);
            $this->assertSame(2, $trace['residual_pool']);
            $this->assertSame($allocation['amount'], (string) BigDecimal::of($trace['floor'])->plus($trace['residual_award']));
        }
        $r = (new CostEngine)->calculate([$this->line(1) + ['weight' => '1.25'], $this->line(2) + ['weight' => '3.75']], [['id' => 1, 'amount_clp' => '3', 'method' => 'weight']]);
        $this->assertSame('1.25', $r->allocations[0]['trace']['basis']);
        $this->assertSame('5.00', $r->allocations[0]['trace']['basis_total']);
        $this->assertSame('3.75', $r->allocations[0]['trace']['remainder_numerator']);
        $this->assertSame('1', $r->allocations[0]['amount']);
        $this->assertSame('2', $r->allocations[1]['amount']);
    }
}
