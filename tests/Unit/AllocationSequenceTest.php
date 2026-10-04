<?php

namespace Tests\Unit;

use App\Modules\Costing\Domain\AllocationBuffer;
use App\Modules\Costing\Domain\CostEngine;
use LogicException;
use PHPUnit\Framework\TestCase;
use Tests\Support\ReferenceCostEngine;

class AllocationSequenceTest extends TestCase
{
    public function test_chunk_boundaries_repeatable_iteration_indexing_and_immutability(): void
    {
        $buffer = new AllocationBuffer;
        $expected = [];
        for ($i = 0; $i < 777; $i++) {
            $expected[] = $row = ['expense_id' => $i, 'product_id' => 100 + $i, 'amount' => (string) $i,
                'method' => 'weight', 'trace' => ['basis' => '1.25000000', 'name' => 'Ámbito sintético']];
            $buffer->add($row);
        }
        $sequence = $buffer->finish();
        $this->assertCount(777, $sequence);
        $this->assertSame($expected, iterator_to_array($sequence));
        $this->assertSame($expected, iterator_to_array($sequence));
        foreach ([0, 255, 256, 511, 512, 776] as $index) {
            $this->assertSame($expected[$index], $sequence[$index]);
        }
        foreach ([fn () => $sequence[0] = [], fn () => $buffer->add([]), fn () => $sequence->offsetUnset(0)] as $mutate) {
            try {
                $mutate();
                $this->fail('Completed result must remain immutable.');
            } catch (LogicException) {
                $this->assertSame($expected, iterator_to_array($sequence));
            }
        }
    }

    public function test_all_rules_decimal_scales_large_values_and_natural_order_match_frozen_reference(): void
    {
        mt_srand(20261004);
        $engine = new CostEngine;
        $reference = new ReferenceCostEngine;
        foreach (range(1, 60) as $case) {
            $lines = [];
            foreach ([1, 2, 10, 11, 20] as $id) {
                $lines[] = ['id' => $id, 'quantity' => mt_rand(1, 10), 'unit_amount' => ($case % 3 ? '123.45' : '90000000000000000000.12'),
                    'rate' => '900.12345678', 'weight' => mt_rand(0, 100).'.'.str_pad((string) mt_rand(0, 999), 3, '0'),
                    'volume' => $case % 2 ? '0.00000001' : '90000000000000000000000.00000001'];
            }
            $expenses = [];
            foreach (['value', 'units', 'weight', 'volume', 'direct', 'direct', 'weight'] as $index => $method) {
                $expenses[] = ['id' => $index + 1, 'amount_clp' => $case % 3 ? (string) mt_rand(0, 1000000) : '900000000000000000000.50',
                    'method' => $method, 'target_id' => $index === 5 ? 999 : 2];
            }
            $expected = $reference->calculate($lines, $expenses)->toArray();
            $actual = $engine->calculate(array_reverse($lines), array_reverse($expenses));
            $this->assertSame($expected, $actual->toArray(), 'Exact v1 trace contract, case '.$case);
            $this->assertSame(array_diff_key($expected, ['lines' => true, 'allocations' => true]), $actual->summary());
            $storage = $actual->storageData();
            $this->assertArrayNotHasKey('allocations', $storage);
            $this->assertSame(count($expected['allocations']), $storage['allocation_count']);
        }
    }

    public function test_empty_allocations_remain_an_empty_sequence_and_zero_count(): void
    {
        $result = (new CostEngine)->calculate([], [['id' => 1, 'amount_clp' => '20']]);
        $this->assertCount(0, $result->allocations);
        $this->assertSame([], $result->toArray()['allocations']);
        $this->assertSame('20', $result->unassigned);
    }
}
