<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Product;
use App\Models\Trip;
use App\Modules\Costing\Domain\CostEngine;
use App\Modules\Costing\Domain\CostResult;
use App\Modules\Costing\Domain\Currency;
use App\Modules\Costing\Domain\Rate;
use App\Modules\Reporting\FinancialSummaryData;
use App\Modules\Shared\InsertBatchSize;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Compatibility boundary for the original controllers. */
class ProrrateoService
{
    public function __construct(private CostEngine $engine, private InsertBatchSize $batches) {}

    public function calcularProrrateo(Trip $trip): CostResult
    {
        return DB::transaction(function () use ($trip) {
            $products = $trip->products()->orderBy('id')->get();
            $expenses = $trip->expenses()->orderBy('id')->get();
            $result = $this->preview($trip->setRelation('products', $products)->setRelation('expenses', $expenses));
            foreach ($expenses as $expense) {
                $expense->monto_clp = $this->convertirACLP($expense->monto, $expense->moneda, $trip, $expense);
            }
            $byId = $products->keyBy('id');
            foreach ($result->lines as $line) {
                $byId[$line['id']]->fill([
                    'subtotal_clp' => $line['purchase'], 'gastos_prorrateados_clp' => $line['assigned'],
                    'costo_final_total_clp' => $line['total'], 'costo_final_unitario_clp' => $line['unit_cost'],
                ]);
            }
            foreach (array_chunk($expenses->map->getAttributes()->all(), $expenses->isEmpty() ? 1 : $this->batches->forColumns(count($expenses->first()->getAttributes()), 100)) as $chunk) {
                Expense::upsert($chunk, ['id'], ['monto_clp']);
            }
            foreach (array_chunk($products->map->getAttributes()->all(), $products->isEmpty() ? 1 : $this->batches->forColumns(count($products->first()->getAttributes()), 100)) as $chunk) {
                Product::upsert($chunk, ['id'], ['subtotal_clp', 'gastos_prorrateados_clp', 'costo_final_total_clp', 'costo_final_unitario_clp']);
            }
            $trip->unsetRelation('products')->unsetRelation('expenses');

            return $result;
        });
    }

    /** Same domain calculation for previews, without updating models or database rows. */
    public function preview(Trip $trip): CostResult
    {
        $trip->loadMissing(['products', 'expenses']);
        try {
            $result = $this->engine->calculate(
                $trip->products->map(fn ($p) => ['id' => $p->id, 'quantity' => $p->cantidad, 'unit_amount' => $p->costo_unitario, 'rate' => $this->getTipoCambio($p->moneda, $trip, $p), 'weight' => $p->weight, 'volume' => $p->volume])->all(),
                $trip->expenses->map(fn ($e) => ['id' => $e->id, 'amount_clp' => $this->convertirACLP($e->monto, $e->moneda, $trip, $e), 'method' => $e->allocation_method, 'target_id' => $e->target_product_id])->all()
            );
        } catch (\DomainException $e) {
            throw ValidationException::withMessages(['allocation_method' => $e->getMessage()]);
        }
        if (BigDecimal::of($result->total)->isGreaterThan('9000000000000000')) {
            throw ValidationException::withMessages(['monto' => 'El total supera el límite certificado de 9.000.000.000.000.000 CLP.']);
        }

        return $result;
    }

    public function convertirACLP(string|int|float $monto, string $moneda, Trip $trip, ?Model $record = null): string
    {
        return (string) BigDecimal::of((string) $monto)->multipliedBy($this->getTipoCambio($moneda, $trip, $record))->toScale(0, RoundingMode::HALF_UP);
    }

    private function getTipoCambio(string $moneda, Trip $trip, ?Model $record = null): string
    {
        $field = $record?->rate_mode === 'paid' ? 'applied_rate' : 'tipo_cambio';
        try {
            $currency = Currency::supported($moneda);
            if ($currency === Currency::CLP) {
                return '1';
            }
            if ($record?->rate_mode === 'paid') {
                if (! $record->applied_rate || ! $record->rate_source || ! $record->rate_date) {
                    throw new \DomainException('Registra tasa, fuente y fecha del pago histórico.');
                }
                $value = (string) $record->applied_rate;
            } else {
                if ($moneda !== $trip->moneda_trabajo) {
                    throw new \DomainException("No existe una tasa de referencia para {$moneda}.");
                }
                $value = (string) $trip->tipo_cambio;
            }

            return (string) (new Rate($currency, $value))->value;
        } catch (\DomainException $e) {
            throw ValidationException::withMessages([$field => $e->getMessage()]);
        }
    }

    public function obtenerEstadisticas(Trip $trip): array
    {
        return FinancialSummaryData::fromTrip($trip)->statistics;
    }

    public function obtenerGastosPorCategoria(Trip $trip): array
    {
        return $trip->expenses()->with('category')->get()->groupBy(fn ($e) => $e->category?->nombre ?? 'Sin categoría')
            ->map(fn ($rows) => (string) $rows->reduce(fn (BigDecimal $s, $e) => $s->plus($e->monto_clp), BigDecimal::zero()))->all();
    }
}
