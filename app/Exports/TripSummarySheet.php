<?php

namespace App\Exports;

use App\Models\Trip;
use App\Modules\Reporting\FinancialSummaryData;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TripSummarySheet extends SafeValueBinder implements FromArray, WithColumnFormatting, WithCustomValueBinder, WithStrictNullComparison, WithStyles, WithTitle
{
    private FinancialSummaryData $summary;

    public function __construct(private Trip $trip, ?FinancialSummaryData $summary = null)
    {
        $this->summary = $summary ?? FinancialSummaryData::fromTrip($trip);
    }

    public function array(): array
    {
        $s = $this->summary->statistics;
        $projection = $this->summary->projection;

        return [['RESUMEN DE IMPORTACIÓN'], [null], ['Nombre', $this->trip->nombre], ['Descripción', $this->trip->descripcion],
            ['Fecha inicio', $this->trip->fecha_inicio?->format('d/m/Y') ?? 'Sin fecha'], ['Fecha fin', $this->trip->fecha_fin?->format('d/m/Y') ?? 'Sin fecha'],
            ['Moneda base', $this->trip->moneda_base], ['Moneda trabajo', $this->trip->moneda_trabajo], ['Tipo de cambio', (float) $this->trip->tipo_cambio], [null],
            ['TOTALES CLP'], [null], ['Compra CLP', (float) $s['total_productos_clp']], ['Gastos CLP', (float) $s['total_gastos_clp']],
            ['Costo del lote CLP', (float) $s['costo_total']], ['Gastos sin asignar CLP', (float) $s['gastos_sin_asignar_clp']], [null],
            ['PROYECCIÓN · NO REPRESENTA VENTAS REALIZADAS'],
            ['Líneas con precio', $projection['priced_lines']], ['Líneas totales', $projection['lines']],
            ['Ingreso proyectado CLP', $projection['revenue'] === null ? null : (float) $projection['revenue']],
            ['Utilidad proyectada CLP', $projection['profit'] === null ? null : (float) $projection['profit']],
            ['Margen sobre ventas', $projection['complete'] && $projection['margin'] !== null ? (float) (string) BigDecimal::of($projection['margin'])->dividedBy(100, 4, RoundingMode::HALF_UP) : null],
            ['Estado de la proyección', $projection['complete'] ? 'Completa' : 'Pendiente: faltan precios'],
            ['Ingreso de líneas con precio CLP', (float) $projection['covered_revenue']],
            ['Utilidad de líneas con precio CLP', (float) $projection['covered_profit']],
            ['Procedencia del reporte', $this->trip->report_provenance ?? 'Revisión de costeo o importes actuales']];
    }

    public function title(): string
    {
        return 'Resumen';
    }

    public function styles(Worksheet $sheet)
    {
        return [1 => ['font' => ['bold' => true, 'size' => 16]], 11 => ['font' => ['bold' => true]], 15 => ['font' => ['bold' => true]],
            18 => ['font' => ['bold' => true]], 'B9' => ['numberFormat' => ['formatCode' => '0.00000000']],
            'B23' => ['numberFormat' => ['formatCode' => '0.0%']]];
    }

    public function columnFormats(): array
    {
        return ['B' => '#,##0.00'];
    }
}
