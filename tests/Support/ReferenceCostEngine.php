<?php

namespace Tests\Support;

use App\Modules\Costing\Domain\CostResult;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use DomainException;

/** Frozen v1 baseline for independent equality checks, including every trace field. */
final class ReferenceCostEngine
{
    public function calculate(array $lines, array $expenses): CostResult
    {
        usort($lines, fn ($a, $b) => strnatcmp((string) $a['id'], (string) $b['id']));
        usort($expenses, fn ($a, $b) => strnatcmp((string) $a['id'], (string) $b['id']));
        $purchase = BigDecimal::zero();
        $expenseTotal = BigDecimal::zero();
        $assigned = BigDecimal::zero();
        $computed = [];
        $allocations = [];
        $expenseIds = [];
        foreach ($lines as $line) {
            if (isset($computed[$line['id']]) || ! is_int($line['quantity']) || $line['quantity'] < 1) {
                throw new DomainException('Identificadores únicos y cantidades enteras positivas son obligatorios.');
            }
            $unit = BigDecimal::of($line['unit_amount']);
            $rate = BigDecimal::of($line['rate']);
            if ($unit->isNegative() || $rate->isLessThanOrEqualTo(0)) {
                throw new DomainException('El costo no puede ser negativo y la tasa debe ser mayor que cero.');
            }
            $subtotal = $unit->multipliedBy($line['quantity'])->multipliedBy($rate)->toScale(0, RoundingMode::HALF_UP);
            $computed[$line['id']] = ['id' => $line['id'], 'quantity' => $line['quantity'], 'purchase' => (string) $subtotal, 'assigned' => '0'];
            $purchase = $purchase->plus($subtotal);
        }
        foreach ($expenses as $expense) {
            if (isset($expenseIds[$expense['id']])) {
                throw new DomainException('Identificador de gasto duplicado.');
            }
            $expenseIds[$expense['id']] = true;
            $amount = BigDecimal::of($expense['amount_clp'])->toScale(0, RoundingMode::HALF_UP);
            if ($amount->isNegative()) {
                throw new DomainException('Los gastos no pueden ser negativos.');
            }
            $expenseTotal = $expenseTotal->plus($amount);
            $method = $expense['method'] ?? 'value';
            $weights = [];
            foreach ($lines as $line) {
                $value = match ($method) {
                    'value' => $computed[$line['id']]['purchase'], 'units' => $line['quantity'],
                    'weight','volume' => $line[$method] ?? null,
                    'direct' => (string) $line['id'] === (string) ($expense['target_id'] ?? '') ? 1 : 0,
                    default => throw new DomainException('Regla de distribución desconocida.'),
                };
                if ($value === null || BigDecimal::of($value)->isNegative()) {
                    throw new DomainException('Completa el peso o volumen de todos los productos para usar esta regla.');
                }
                $weights[$line['id']] = BigDecimal::of($value);
            }
            foreach ($this->allocate($amount, $weights) as $id => $allocation) {
                $value = $allocation['amount'];
                $computed[$id]['assigned'] = (string) BigDecimal::of($computed[$id]['assigned'])->plus($value);
                $assigned = $assigned->plus($value);
                $allocations[] = ['expense_id' => $expense['id'], 'product_id' => $id, 'amount' => (string) $value, 'method' => $method,
                    'trace' => $allocation['trace']];
            }
        }
        foreach ($computed as &$line) {
            $total = BigDecimal::of($line['purchase'])->plus($line['assigned']);
            $line['total'] = (string) $total;
            $line['unit_cost'] = (string) $total->dividedBy($line['quantity'], 8, RoundingMode::HALF_UP);
        }

        return new CostResult(array_values($computed), $allocations, (string) $purchase, (string) $expenseTotal,
            (string) $assigned, (string) $expenseTotal->minus($assigned), (string) $purchase->plus($expenseTotal));
    }

    private function allocate(BigDecimal $amount, array $weights): array
    {
        $sum = array_reduce($weights, fn (BigDecimal $s, BigDecimal $v) => $s->plus($v), BigDecimal::zero());
        if ($sum->isZero()) {
            return [];
        } // Explicitly unassigned: closing must reject this state.
        $assigned = BigDecimal::zero();
        $shares = [];
        $remainders = [];
        foreach ($weights as $id => $weight) {
            $numerator = $amount->multipliedBy($weight);
            $shares[$id] = $numerator->dividedBy($sum, 0, RoundingMode::DOWN);
            $assigned = $assigned->plus($shares[$id]);
            // Exact remainder numerator; all rows share the denominator.
            $remainders[$id] = $numerator->minus($shares[$id]->multipliedBy($sum));
        }
        $ids = array_keys($weights);
        usort($ids, fn ($a, $b) => $remainders[$b]->compareTo($remainders[$a]) ?: strnatcmp((string) $a, (string) $b));
        $residual = $amount->minus($assigned)->toInt();
        $rank = array_flip($ids);
        $result = [];
        foreach ($shares as $id => $floor) {
            $award = $rank[$id] < $residual ? 1 : 0;
            $result[$id] = ['amount' => $floor->plus($award), 'trace' => [
                'version' => 1, 'expense_amount' => (string) $amount, 'basis' => (string) $weights[$id], 'basis_total' => (string) $sum,
                'floor' => (string) $floor, 'remainder_numerator' => (string) $remainders[$id], 'remainder_denominator' => (string) $sum,
                'priority' => $rank[$id] + 1, 'residual_pool' => $residual, 'residual_award' => $award]];
        }

        return $result;
    }
}
