<?php

namespace App\Modules\Pricing\Actions;

use App\Models\Trip;
use App\Modules\Pricing\Domain\TargetPrice;
use App\Services\ProrrateoService;
use Brick\Math\BigDecimal;
use Illuminate\Validation\ValidationException;

final class PreviewPrices
{
    public function __construct(private TargetPrice $calculator, private ProrrateoService $costing) {}

    public function execute(Trip $trip, array $policy): array
    {
        $trip->loadMissing(['products', 'expenses']);
        $selected = $trip->products->whereIn('id', $policy['product_ids'])->sortBy('id');
        if ($selected->count() !== count($policy['product_ids'])) {
            throw ValidationException::withMessages(['product_ids' => 'Selecciona solo productos de esta importación.']);
        }
        $cost = $this->costing->preview($trip);
        $costs = collect($cost->lines)->keyBy('id');
        if ($cost->unassigned !== '0') {
            throw ValidationException::withMessages(['pricing' => 'Distribuye todos los gastos antes de calcular precios por objetivo.']);
        }
        $lines = $selected->map(function ($p) use ($policy, $costs) {
            $lineCost = $costs[$p->id]['total'];
            try {
                $result = $this->calculator->calculate($lineCost, (int) $p->cantidad, $policy['method'], (string) $policy['target'], (string) $policy['commission'], (int) $policy['quantum']);
            } catch (\DomainException $e) {
                throw ValidationException::withMessages(["products.{$p->id}" => "{$p->nombre}: {$e->getMessage()}"]);
            }

            return ['id' => $p->id, 'name' => $p->nombre, 'quantity' => (int) $p->cantidad, 'cost' => $lineCost, 'previous_price' => $p->precio_venta, ...$result];
        })->values()->all();
        $sum = fn ($field) => (string) array_reduce($lines, fn ($s, $line) => $s->plus($line[$field]), BigDecimal::zero());

        return ['revision' => $trip->revision, 'policy' => $policy, 'lines' => $lines,
            'totals' => ['revenue' => $sum('revenue'), 'commission' => $sum('commission_amount'), 'profit_after_commission' => $sum('profit_after_commission')]];
    }
}
