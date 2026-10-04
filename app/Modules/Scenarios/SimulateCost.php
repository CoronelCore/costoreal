<?php

namespace App\Modules\Scenarios;

use App\Models\Trip;
use App\Modules\Pricing\Actions\PreviewPrices;
use App\Modules\Pricing\Domain\Projection;
use App\Services\ProrrateoService;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Validation\ValidationException;

final class SimulateCost
{
    public function __construct(private ProrrateoService $costing, private Projection $projection, private PreviewPrices $pricing) {}

    public function execute(Trip $trip, array $input): array
    {
        $trip->loadMissing(['products', 'expenses']);
        $candidate = clone $trip;
        $candidate->setRelation('products', $trip->products->map(fn ($p) => clone $p));
        $candidate->setRelation('expenses', $trip->expenses->map(fn ($e) => clone $e));
        $originalProducts = $trip->products->keyBy('id');
        $candidateProducts = $candidate->products->keyBy('id');
        $candidateExpenses = $candidate->expenses->keyBy('id');
        $before = $this->costing->preview($trip);
        $beforeProducts = $trip->products->map(fn ($p) => clone $p);
        $this->setCosts($beforeProducts, $before->lines);
        $baseline = ['reference_rate' => (string) $trip->tipo_cambio,
            'products' => $trip->products->sortBy('id')->values()->map->only(['id', 'nombre', 'cantidad', 'moneda', 'costo_unitario', 'weight', 'volume', 'rate_mode', 'applied_rate', 'rate_source', 'rate_date', 'precio_venta'])->all(),
            'expenses' => $trip->expenses->sortBy('id')->values()->map->only(['id', 'concepto', 'moneda', 'monto', 'rate_mode', 'applied_rate', 'rate_source', 'rate_date', 'allocation_method', 'target_product_id'])->all()];
        $candidate->tipo_cambio = $input['reference_rate'] ?? $trip->tipo_cambio;
        $variation = BigDecimal::of((string) ($input['expense_variation_percent'] ?? '0'))->dividedBy(100, 4)->plus(1);
        foreach ($candidate->expenses as $expense) {
            if ($expense->rate_mode !== 'paid') {
                $expense->monto = (string) BigDecimal::of($expense->monto)->multipliedBy($variation)->toScale(2, RoundingMode::HALF_UP);
            }
        }
        foreach ($input['products'] ?? [] as $index => $change) {
            $product = $candidateProducts->get($change['id']);
            if (! $product) {
                throw ValidationException::withMessages(["products.{$index}.id" => 'El producto no pertenece a esta importación.']);
            }
            if ($product->rate_mode === 'paid') {
                foreach (['cantidad', 'costo_unitario'] as $field) {
                    if (isset($change[$field]) && ! BigDecimal::of((string) $change[$field])->isEqualTo((string) $product->{$field})) {
                        throw ValidationException::withMessages(["products.{$index}.{$field}" => 'El escenario conserva las compras pagadas. Ajusta solo estimaciones o precios de venta.']);
                    }
                }
            }
            if (! empty($input['pricing']) && array_key_exists('precio_venta', $change)) {
                throw ValidationException::withMessages(['pricing' => 'Elige precios manuales o precio por objetivo para el escenario.']);
            }
            $product->fill(array_diff_key($change, ['id' => true]));
        }
        foreach ($input['expenses'] ?? [] as $index => $change) {
            $expense = $candidateExpenses->get($change['id']);
            if (! $expense) {
                throw ValidationException::withMessages(["expenses.{$index}.id" => 'El gasto no pertenece a esta importación.']);
            }
            if ($expense->rate_mode === 'paid' && isset($change['monto']) && ! BigDecimal::of((string) $change['monto'])->isEqualTo((string) $expense->monto)) {
                throw ValidationException::withMessages(["expenses.{$index}.monto" => 'El escenario conserva el monto histórico del pago.']);
            }
            $expense->fill(array_diff_key($change, ['id' => true]));
            if ($expense->allocation_method === 'direct' && ! $candidate->products->contains('id', $expense->target_product_id)) {
                throw ValidationException::withMessages(["expenses.{$index}.target_product_id" => 'Selecciona el producto que recibirá este gasto directo.']);
            }
        }
        foreach ($candidate->expenses as $expense) {
            if (BigDecimal::of($expense->monto)->isGreaterThan('9999999999.99')) {
                throw ValidationException::withMessages(['expense_variation_percent' => 'La variación produce un gasto fuera del límite permitido.']);
            }
        }
        $after = $this->costing->preview($candidate);
        $this->setCosts($candidate->products, $after->lines);
        $pricing = ! empty($input['pricing']) ? $this->pricing->execute($candidate, $input['pricing']) : null;
        foreach ($pricing['lines'] ?? [] as $line) {
            $candidateProducts[$line['id']]->precio_venta = $line['price'];
        }
        $beforeLines = collect($before->lines)->keyBy('id');
        $lines = $candidate->products->sortBy('id')->values()->map(fn ($p) => ['id' => $p->id, 'name' => $p->nombre,
            'quantity_before' => $originalProducts[$p->id]->cantidad, 'quantity_after' => $p->cantidad,
            'cost_before' => $beforeLines[$p->id]['total'], 'cost_after' => (string) BigDecimal::of($p->costo_final_total_clp)->toScale(0),
            'price_before' => $originalProducts[$p->id]->precio_venta, 'price_after' => $p->precio_venta,
            'cost_delta' => (string) BigDecimal::of($p->costo_final_total_clp)->minus($beforeLines[$p->id]['total'])->toScale(0)])->all();

        return ['baseline_hash' => hash('sha256', json_encode($baseline, JSON_THROW_ON_ERROR)), 'candidate' => $candidate,
            'result' => ['before' => ['reference_rate' => (string) $trip->tipo_cambio, 'cost' => $before->total, 'unassigned' => $before->unassigned,
                'projection' => $this->projection->summarize($beforeProducts, $before->unassigned)],
                'after' => ['reference_rate' => (string) $candidate->tipo_cambio, 'cost' => $after->total, 'unassigned' => $after->unassigned,
                    'projection' => $this->projection->summarize($candidate->products, $after->unassigned)],
                'cost_delta' => (string) BigDecimal::of($after->total)->minus($before->total), 'lines' => $lines, 'pricing' => $pricing,
                'paid_expenses_preserved' => $trip->expenses->where('rate_mode', 'paid')->count(), 'paid_products_preserved' => $trip->products->where('rate_mode', 'paid')->count()]];
    }

    private function setCosts($products, array $lines): void
    {
        $byId = $products->keyBy('id');
        foreach ($lines as $line) {
            $byId[$line['id']]->fill(['subtotal_clp' => $line['purchase'], 'gastos_prorrateados_clp' => $line['assigned'],
                'costo_final_total_clp' => $line['total'], 'costo_final_unitario_clp' => $line['unit_cost']]);
        }
    }
}
