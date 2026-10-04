<?php

namespace Tests\Unit;

use App\Modules\Pricing\Domain\Projection;
use App\Modules\Pricing\Domain\ProjectionAccumulator;
use PHPUnit\Framework\TestCase;

class ProjectionAccumulatorTest extends TestCase
{
    public function test_merging_cohorts_preserves_unrounded_revenue_cost_and_unassigned_profit(): void
    {
        $product = (object) ['cantidad' => 1, 'precio_venta' => '0.26', 'costo_final_total_clp' => '0.10'];
        $a = new ProjectionAccumulator;
        $b = new ProjectionAccumulator;
        $a->add($product);
        $b->add($product);
        $this->assertSame('0', $a->summary()['revenue']);
        $a->merge($b);
        $this->assertSame((new Projection)->summarize([$product, $product], '0.05'), $a->summary('0.05'));
        $this->assertSame('1', $a->summary('0.05')['revenue']);
        $this->assertSame('51.92', $a->summary('0.05')['margin']);
        $this->assertSame('0', $b->summary()['revenue']);
    }
}
