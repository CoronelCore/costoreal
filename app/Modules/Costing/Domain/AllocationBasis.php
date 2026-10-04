<?php

namespace App\Modules\Costing\Domain;

use Brick\Math\BigDecimal;

/** Exact basis shared by expense calculations; scaled native integers only when representable. */
final readonly class AllocationBasis
{
    public BigDecimal $total;

    public int $scale;

    public ?array $nativeWeights;

    public ?int $nativeTotal;

    public int $maximumWeight;

    public function __construct(public array $weights)
    {
        $total = BigDecimal::zero();
        foreach ($weights as $weight) {
            $total = $total->plus($weight);
        }
        $this->total = $total;
        $this->scale = $total->getScale();
        $this->nativeTotal = self::native((string) $total->getUnscaledValue());
        $native = [];
        $maximum = 0;
        foreach ($weights as $id => $weight) {
            $value = self::native((string) $weight->toScale($this->scale)->getUnscaledValue());
            if ($value === null) {
                $native = null;
                break;
            }
            $native[$id] = $value;
            $maximum = max($maximum, $value);
        }
        $this->nativeWeights = $native;
        $this->maximumWeight = $maximum;
    }

    public static function native(string $digits): ?int
    {
        $maximum = (string) PHP_INT_MAX;
        if (! ctype_digit($digits) || strlen($digits) > strlen($maximum)
            || (strlen($digits) === strlen($maximum) && strcmp($digits, $maximum) > 0)) {
            return null;
        }

        return (int) $digits;
    }

    public function remainderText(int $value): string
    {
        if ($this->scale === 0) {
            return (string) $value;
        }
        $digits = str_pad((string) $value, $this->scale + 1, '0', STR_PAD_LEFT);

        return substr($digits, 0, -$this->scale).'.'.substr($digits, -$this->scale);
    }
}
