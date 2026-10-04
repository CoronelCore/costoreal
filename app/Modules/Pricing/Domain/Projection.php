<?php

namespace App\Modules\Pricing\Domain;

final class Projection
{
    public function summarize(iterable $products, string $unassigned = '0'): array
    {
        $accumulator = new ProjectionAccumulator;
        foreach ($products as $product) {
            $accumulator->add($product);
        }

        return $accumulator->summary($unassigned);
    }
}
