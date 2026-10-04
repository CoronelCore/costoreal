<?php

namespace App\Modules\Costing\Domain;

use Brick\Math\BigDecimal;

final readonly class CostResult
{
    public string $allocatedTotal;

    public function __construct(public array $lines, public array|AllocationSequence $allocations, public string $purchase,
        public string $expenses, public string $assigned, public string $unassigned, public string $total,
        public string $algorithm = 'largest-remainder-clp-v1')
    {
        $this->allocatedTotal = (string) BigDecimal::of($purchase)->plus($assigned);
    }

    public function toArray(): array
    {
        return [...$this->summary(), 'lines' => $this->lines,
            'allocations' => is_array($this->allocations) ? $this->allocations : iterator_to_array($this->allocations, false)];
    }

    /** Scalars for previews and comparisons; never expands traces that those screens do not use. */
    public function summary(): array
    {
        return ['currency' => 'CLP', 'algorithm' => $this->algorithm, 'purchase' => $this->purchase, 'expenses' => $this->expenses,
            'assigned' => $this->assigned, 'unassigned' => $this->unassigned, 'total' => $this->total, 'allocated_total' => $this->allocatedTotal];
    }

    /** Trace rows live in cost_allocations, atomically with this immutable record. */
    public function storageData(): array
    {
        return [...$this->summary(), 'lines' => $this->lines, 'storage_version' => 2,
            'allocation_source' => 'cost_allocations', 'allocation_count' => count($this->allocations)];
    }
}
