<?php

namespace App\Exports;

use App\Models\Trip;
use App\Modules\Reporting\FinancialSummaryData;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TripProductsSheet extends SafeValueBinder implements FromArray, WithColumnFormatting, WithCustomValueBinder, WithHeadings, WithStrictNullComparison, WithStyles, WithTitle
{
    private FinancialSummaryData $summary;

    public function __construct(private Trip $trip, ?FinancialSummaryData $summary = null)
    {
        $this->summary = $summary ?? FinancialSummaryData::fromTrip($trip);
        $trip->loadMissing('products');
    }

    public function array(): array
    {
        $rows = [];
        foreach ($this->trip->products as $p) {
            $rows[] = [
                $p->nombre, (int) $p->cantidad, $p->moneda, (float) $p->costo_unitario, (float) $p->subtotal_clp,
                (float) $p->gastos_prorrateados_clp, (float) $p->costo_final_total_clp, (float) $p->costo_final_unitario_clp,
                $p->precio_venta === null ? null : (float) $p->precio_venta,
                $p->precio_venta !== null && BigDecimal::of($p->precio_venta)->isPositive()
                    ? (float) (string) BigDecimal::of($p->precio_venta)->minus($p->costo_final_unitario_clp)->dividedBy($p->precio_venta, 12, RoundingMode::HALF_UP) : null,
            ];
        }
        $s = $this->summary->statistics;
        $rows[] = [];
        $rows[] = ['TOTAL', null, null, null, (float) $s['total_productos_clp'], (float) $s['total_gastos_prorrateados_clp'], (float) $s['costo_total_final_clp']];

        return $rows;
    }

    public function headings(): array
    {
        return ['Producto', 'Cantidad', 'Moneda original', 'Costo unitario original', 'Compra CLP', 'Gastos asignados CLP', 'Costo total CLP', 'Costo unitario final CLP', 'Precio venta CLP', 'Margen proyectado'];
    }

    public function title(): string
    {
        return 'Productos';
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }

    public function columnFormats(): array
    {
        return ['D' => '#,##0.00', 'E' => '#,##0', 'F' => '#,##0', 'G' => '#,##0', 'H' => '#,##0.00', 'I' => '#,##0.00', 'J' => '0.0%'];
    }
}
