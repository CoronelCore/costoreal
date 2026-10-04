<?php

namespace Tests\Unit;

use App\Modules\Costing\CalculationMemo;
use App\Modules\Costing\Domain\CostEngine;
use Tests\TestCase;

class CalculationMemoTest extends TestCase
{
    public function test_all_financial_dimensions_invalidate_the_cached_result_and_only_one_result_is_retained(): void
    {
        $memo = new CalculationMemo;
        $engine = new CostEngine;
        $lines = [['id' => 1, 'quantity' => 2, 'unit_amount' => '10.25', 'rate' => '900.12345678', 'weight' => '1.20', 'volume' => '0.50']];
        $expenses = [['id' => 1, 'amount_clp' => '123', 'method' => 'value', 'target_id' => null]];
        $calls = 0;
        $calculate = function ($l, $e) use ($memo, $engine, &$calls) {
            return $memo->remember($l, $e, function () use ($engine, $l, $e, &$calls) {
                $calls++;

                return $engine->calculate($l, $e);
            });
        };
        $original = $calculate($lines, $expenses);
        $this->assertSame($original, $calculate($lines, $expenses));
        $this->assertSame(1, $calls);
        foreach ([['id', 2], ['quantity', 3], ['unit_amount', '10.26'], ['rate', '901.12345678'], ['weight', '1.21'], ['volume', '0.51']] as [$field, $value]) {
            $changed = $lines;
            $changed[0][$field] = $value;
            $before = $calls;
            $actual = $calculate($changed, $expenses);
            $this->assertSame($before + 1, $calls);
            $this->assertSame($engine->calculate($changed, $expenses)->toArray(), $actual->toArray());
            $this->assertSame($actual, $calculate($changed, $expenses));
        }
        foreach ([['id', 2], ['amount_clp', '124'], ['method', 'units'], ['target_id', 1]] as [$field, $value]) {
            $changed = $expenses;
            $changed[0][$field] = $value;
            $before = $calls;
            $actual = $calculate($lines, $changed);
            $this->assertSame($before + 1, $calls);
            $this->assertSame($engine->calculate($lines, $changed)->toArray(), $actual->toArray());
        }
        $before = $calls;
        $this->assertNotSame($original, $calculate($lines, $expenses));
        $this->assertSame($before + 1, $calls);
    }

    public function test_scoped_memo_is_released_between_requests_and_a_failure_does_not_publish_a_cached_result(): void
    {
        $memo = app(CalculationMemo::class);
        $this->assertSame($memo, app(CalculationMemo::class));
        app()->forgetScopedInstances();
        $this->assertNotSame($memo, app(CalculationMemo::class));
        $calls = 0;
        for ($i = 0; $i < 2; $i++) {
            try {
                $memo->remember([], [], function () use (&$calls) {
                    $calls++;
                    throw new \DomainException('Invalid calculation');
                });
                $this->fail('The invalid calculation must fail.');
            } catch (\DomainException $error) {
                $this->assertSame('Invalid calculation', $error->getMessage());
            }
        }
        $this->assertSame(2, $calls);
    }

    public function test_an_invalid_callback_return_cannot_pair_a_new_key_with_an_old_result(): void
    {
        $memo = new CalculationMemo;
        $lines = [['id' => 1, 'quantity' => 1, 'unit_amount' => '10', 'rate' => '1']];
        $original = $memo->remember($lines, [], fn () => (new CostEngine)->calculate($lines, []));
        $calls = 0;
        for ($i = 0; $i < 2; $i++) {
            try {
                $memo->remember([], [], function () use (&$calls) {
                    $calls++;

                    return [];
                });
                $this->fail('An invalid callback result must be rejected.');
            } catch (\TypeError) {
                $this->assertSame($i + 1, $calls);
            }
        }
        $this->assertSame($original, $memo->remember($lines, [], fn () => throw new \LogicException('Original result should remain valid')));
    }
}
