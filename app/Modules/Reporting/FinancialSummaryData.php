<?php

namespace App\Modules\Reporting;

use App\Models\Trip;
use App\Modules\Pricing\Domain\Projection;
use Brick\Math\BigDecimal;

/** One exact summary for the screen, workbook and current or historical PDF. */
final readonly class FinancialSummaryData
{
    private function __construct(public array $statistics, public array $projection) {}

    public static function fromTrip(Trip $trip): self
    {
        // Loaded snapshot relations must never be replaced by current database rows.
        $trip->loadMissing(['products', 'expenses']);
        $sum = fn ($rows, $field) => (string) $rows->reduce(fn (BigDecimal $s, $row) => $s->plus($row->{$field}), BigDecimal::zero());
        $expenses = $sum($trip->expenses, 'monto_clp');
        $purchase = $sum($trip->products, 'subtotal_clp');
        $assigned = $sum($trip->products, 'gastos_prorrateados_clp');
        $unassigned = (string) BigDecimal::of($expenses)->minus($assigned);

        return new self([
            'total_gastos_clp' => $expenses,
            'total_productos_clp' => $purchase,
            'total_gastos_prorrateados_clp' => $assigned,
            'gastos_sin_asignar_clp' => $unassigned,
            'costo_total_final_clp' => $sum($trip->products, 'costo_final_total_clp'),
            'costo_total' => (string) BigDecimal::of($expenses)->plus($purchase),
        ], (new Projection)->summarize($trip->products, $unassigned));
    }
}
