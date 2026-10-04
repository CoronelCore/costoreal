<?php

namespace App\Modules\Costing;

use App\Modules\Costing\Domain\CostResult;

/** One immutable pure result per request/job scope; never cache models, actors, or authorization. */
final class CalculationMemo
{
    private ?string $key = null;

    private ?CostResult $result = null;

    public function remember(array $lines, array $expenses, callable $calculate): CostResult
    {
        $key = hash('sha256', json_encode([$lines, $expenses], JSON_THROW_ON_ERROR));
        if ($this->key === $key && $this->result !== null) {
            return $this->result;
        }
        $result = $calculate();
        $this->result = $result;
        $this->key = $key;

        return $result;
    }
}
