<?php

namespace App\Modules\Costing\Domain;

use Brick\Math\BigDecimal;

final readonly class CostResult
{
    public string $allocatedTotal;

    public function __construct(public array $lines, public array $allocations, public string $purchase,
        public string $expenses, public string $assigned, public string $unassigned, public string $total,
        public string $algorithm = 'largest-remainder-clp-v1')
    {
        $this->allocatedTotal = (string) BigDecimal::of($purchase)->plus($assigned);
    }

    public function toArray(): array
    {
        return ['currency' => 'CLP', 'algorithm' => $this->algorithm, 'purchase' => $this->purchase, 'expenses' => $this->expenses,
            'assigned' => $this->assigned, 'unassigned' => $this->unassigned, 'total' => $this->total, 'allocated_total' => $this->allocatedTotal,
            'lines' => $this->lines, 'allocations' => $this->allocations];
    }
}
