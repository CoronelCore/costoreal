<?php

namespace App\Modules\Costing\Reconciliation;

use App\Models\Trip;
use App\Services\ProrrateoService;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Brick\Math\RoundingMode;
use Illuminate\Validation\ValidationException;

/** Compares stored amounts, never a recalculated "before". No financial writes. */
final class CompareStoredCosts
{
    public function __construct(private ProrrateoService $costing) {}

    public function snapshot(Trip $trip): array
    {
        $trip->load(['products' => fn ($q) => $q->orderBy('id'), 'expenses' => fn ($q) => $q->orderBy('id')->with('category'), 'company']);

        return [...$trip->attributesToArray(), 'products' => $trip->products->toArray(), 'expenses' => $trip->expenses->toArray(), 'company' => $trip->company?->toArray()];
    }

    public function fingerprint(array $snapshot): string
    {
        // Include stored amounts, provenance, state and row IDs to catch writes outside CAS.
        return hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR));
    }

    public function execute(Trip $trip): array
    {
        $snapshot = $this->snapshot($trip);
        $fingerprint = $this->fingerprint($snapshot);
        $warnings = [];
        foreach (['products', 'expenses'] as $kind) {
            foreach ($snapshot[$kind] as $row) {
                if (($row['rate_source'] ?? null) === 'legacy-unverified' || (($row['moneda'] ?? 'CLP') !== 'CLP' && ! ($row['rate_source'] ?? null))) {
                    $warnings[] = ['kind' => $kind, 'id' => (int) $row['id'], 'message' => 'Procedencia histórica de tasa sin verificar; la comparación usa la referencia o el pago registrado y no acredita su origen.'];
                }
            }
        }
        $comparison = ['version' => 1, 'revision' => $trip->revision, 'algorithm' => 'largest-remainder-clp-v1', 'fingerprint' => $fingerprint,
            'before' => null, 'after' => null, 'differences' => [], 'warnings' => $warnings, 'errors' => []];
        try {
            foreach (['products' => ['subtotal_clp', 'gastos_prorrateados_clp', 'costo_final_total_clp', 'costo_final_unitario_clp'], 'expenses' => ['monto_clp']] as $kind => $fields) {
                foreach ($snapshot[$kind] as $row) {
                    foreach ($fields as $field) {
                        if (! isset($row[$field]) || ! is_scalar($row[$field]) || ! is_numeric($row[$field])) {
                            throw new \DomainException('Importe heredado ausente o inválido.');
                        }
                    }
                }
            }
            $summary = fn ($rows, $field) => (string) collect($rows)->reduce(fn (BigDecimal $s, $r) => $s->plus($r[$field]), BigDecimal::zero());
            $purchase = $summary($snapshot['products'], 'subtotal_clp');
            $expenses = $summary($snapshot['expenses'], 'monto_clp');
            $assigned = $summary($snapshot['products'], 'gastos_prorrateados_clp');
            $comparison['before'] = ['purchase' => $purchase, 'expenses' => $expenses, 'assigned' => $assigned,
                'unassigned' => (string) BigDecimal::of($expenses)->minus($assigned), 'total' => (string) BigDecimal::of($purchase)->plus($expenses),
                'allocated_total' => $summary($snapshot['products'], 'costo_final_total_clp')];
            $result = $this->costing->preview($trip);
            $comparison['algorithm'] = $result->algorithm;
            $comparison['after'] = array_intersect_key($result->toArray(), array_flip(['purchase', 'expenses', 'assigned', 'unassigned', 'total', 'allocated_total']));
            $byId = collect($result->lines)->keyBy('id');
            foreach ($snapshot['products'] as $p) {
                $line = $byId[$p['id']];
                $consistent = BigDecimal::of($p['subtotal_clp'])->plus($p['gastos_prorrateados_clp'])->isEqualTo($p['costo_final_total_clp']);
                foreach (['subtotal_clp' => 'purchase', 'gastos_prorrateados_clp' => 'assigned', 'costo_final_total_clp' => 'total', 'costo_final_unitario_clp' => 'unit_cost'] as $field => $target) {
                    $class = match ($target) {
                        'purchase' => 'conversion', 'assigned' => 'allocation', 'total' => $consistent ? 'line_total' : 'stored_inconsistency', default => 'unit_precision'
                    };
                    $this->difference($comparison, 'products', $p['id'], $p['nombre'], $field, $p[$field], $line[$target], $class);
                }
            }
            foreach ($trip->expenses as $e) {
                $this->difference($comparison, 'expenses', $e->id, $e->concepto, 'monto_clp', $e->monto_clp, $this->costing->convertirACLP($e->monto, $e->moneda, $trip, $e), 'conversion');
            }
        } catch (ValidationException $e) {
            $comparison['errors'] = array_values(array_merge(...array_values($e->errors())));
        } catch (MathException|\DomainException $e) {
            $comparison['errors'] = ['Hay importes heredados o cantidades que no permiten una comparación válida. Revisa los registros originales.'];
        }

        // The review identity also changes when calculation policy or diagnostics change.
        // Otherwise a reentrant save could return an obsolete comparison forever.
        $comparison['fingerprint'] = hash('sha256', json_encode(['snapshot' => $snapshot,
            'comparison' => array_diff_key($comparison, ['fingerprint' => true])], JSON_THROW_ON_ERROR));

        return ['snapshot' => $snapshot, 'comparison' => $comparison];
    }

    private function difference(array &$comparison, string $kind, int $id, string $name, string $field, string $before, string $after, string $classification): void
    {
        $old = BigDecimal::of($before);
        $new = BigDecimal::of($after);
        if ($old->isEqualTo($new)) {
            return;
        }
        // Only classify rounding when equality after the documented quantum proves it.
        if ($classification === 'conversion' && $old->toScale(0, RoundingMode::HALF_UP)->isEqualTo($new)) {
            $classification = 'clp_rounding';
        }
        $comparison['differences'][] = ['key' => "{$kind}:{$id}:{$field}", 'kind' => $kind, 'id' => $id, 'name' => $name, 'field' => $field,
            'before' => (string) $old, 'after' => (string) $new, 'delta' => (string) $new->minus($old), 'classification' => $classification];
    }
}
