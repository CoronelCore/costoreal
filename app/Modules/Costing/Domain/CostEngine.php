<?php

namespace App\Modules\Costing\Domain;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use DomainException;

/** Pure deterministic calculation. Monetary inputs are decimal strings. */
final class CostEngine
{
    public function calculate(array $lines, array $expenses): CostResult
    {
        usort($lines, fn ($a, $b) => strnatcmp((string) $a['id'], (string) $b['id']));
        usort($expenses, fn ($a, $b) => strnatcmp((string) $a['id'], (string) $b['id']));
        $purchase = BigDecimal::zero();
        $expenseTotal = BigDecimal::zero();
        $assigned = new DecimalSum;
        $lineAssigned = [];
        $basisCache = [];
        $allocationCache = [];
        $computed = [];
        $allocations = new AllocationBuffer;
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
            $lineAssigned[$line['id']] = new DecimalSum;
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
            $basisKey = json_encode([$method, $method === 'direct' ? (string) ($expense['target_id'] ?? '') : null], JSON_THROW_ON_ERROR);
            if (! isset($basisCache[$basisKey])) {
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
                if (count($basisCache) === 4) {
                    array_shift($basisCache);
                }
                $basisCache[$basisKey] = new AllocationBasis($weights);
            }
            $cacheKey = json_encode([$basisKey, (string) $amount], JSON_THROW_ON_ERROR);
            if (count($lines) <= 1000) {
                if (! isset($allocationCache[$cacheKey])) {
                    if (count($allocationCache) === 4) {
                        array_shift($allocationCache);
                    }
                    $allocationCache[$cacheKey] = iterator_to_array($this->allocate($amount, $basisCache[$basisKey]));
                }
                $shares = $allocationCache[$cacheKey];
            } else {
                $shares = $this->allocate($amount, $basisCache[$basisKey]);
            }
            foreach ($shares as $id => $allocation) {
                $value = $allocation['amount'];
                $lineAssigned[$id]->add((string) $value);
                $assigned->add((string) $value);
                $allocations->add(['expense_id' => $expense['id'], 'product_id' => $id, 'amount' => (string) $value, 'method' => $method,
                    'trace' => $allocation['trace']]);
            }
        }
        $assigned = $assigned->value();
        foreach ($computed as &$line) {
            $line['assigned'] = (string) $lineAssigned[$line['id']]->value();
            $total = BigDecimal::of($line['purchase'])->plus($line['assigned']);
            $line['total'] = (string) $total;
            $line['unit_cost'] = (string) $total->dividedBy($line['quantity'], 8, RoundingMode::HALF_UP);
        }

        return new CostResult(array_values($computed), $allocations->finish(), (string) $purchase, (string) $expenseTotal,
            (string) $assigned, (string) $expenseTotal->minus($assigned), (string) $purchase->plus($expenseTotal));
    }

    private function allocate(BigDecimal $amount, AllocationBasis $basis): iterable
    {
        $sum = $basis->total;
        if ($sum->isZero()) {
            return;
        } // Explicitly unassigned: closing must reject this state.
        $nativeAmount = AllocationBasis::native((string) $amount);
        $native = $nativeAmount !== null && $basis->nativeWeights !== null && $basis->nativeTotal !== null
            && ($nativeAmount === 0 || $basis->maximumWeight <= intdiv(PHP_INT_MAX, $nativeAmount));
        $assigned = $native ? 0 : new DecimalSum;
        $shares = [];
        $remainders = [];
        foreach ($basis->weights as $id => $weight) {
            if ($native) {
                $numerator = $nativeAmount * $basis->nativeWeights[$id];
                $shares[$id] = intdiv($numerator, $basis->nativeTotal);
                $remainders[$id] = $numerator % $basis->nativeTotal;
                $assigned += $shares[$id];
            } else {
                [$shares[$id], $remainders[$id]] = $amount->multipliedBy($weight)->quotientAndRemainder($sum);
                $assigned->add((string) $shares[$id]);
            }
        }
        $ids = array_keys($basis->weights);
        usort($ids, fn ($a, $b) => ($native ? $remainders[$b] <=> $remainders[$a] : $remainders[$b]->compareTo($remainders[$a]))
            ?: strnatcmp((string) $a, (string) $b));
        $residual = $native ? $nativeAmount - $assigned : $amount->minus($assigned->value())->toInt();
        $rank = array_flip($ids);
        foreach ($shares as $id => $floor) {
            $award = $rank[$id] < $residual ? 1 : 0;
            yield $id => ['amount' => $native ? (string) ($floor + $award) : (string) $floor->plus($award), 'trace' => [
                'version' => 1, 'expense_amount' => (string) $amount, 'basis' => (string) $basis->weights[$id], 'basis_total' => (string) $sum,
                'floor' => (string) $floor, 'remainder_numerator' => $native ? $basis->remainderText($remainders[$id]) : (string) $remainders[$id], 'remainder_denominator' => (string) $sum,
                'priority' => $rank[$id] + 1, 'residual_pool' => $residual, 'residual_award' => $award]];
        }
    }
}
