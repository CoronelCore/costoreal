<?php

namespace App\Exports;

use App\Models\Trip;
use App\Services\ProrrateoService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TripSummarySheet extends SafeValueBinder implements FromArray, WithColumnFormatting, WithCustomValueBinder, WithStrictNullComparison, WithStyles, WithTitle
{
    public function __construct(private Trip $trip) {}

    public function array(): array
    {
        $s = app(ProrrateoService::class)->obtenerEstadisticas($this->trip);

        return [['RESUMEN DE IMPORTACIÓN'], [], ['Nombre', $this->trip->nombre], ['Descripción', $this->trip->descripcion],
            ['Fecha inicio', $this->trip->fecha_inicio?->format('d/m/Y') ?? 'Sin fecha'], ['Fecha fin', $this->trip->fecha_fin?->format('d/m/Y') ?? 'Sin fecha'],
            ['Moneda base', $this->trip->moneda_base], ['Moneda trabajo', $this->trip->moneda_trabajo], ['Tipo de cambio', (float) $this->trip->tipo_cambio], [],
            ['TOTALES CLP'], [], ['Compra CLP', (float) $s['total_productos_clp']], ['Gastos CLP', (float) $s['total_gastos_clp']],
            ['Costo del lote CLP', (float) $s['costo_total']], ['Gastos sin asignar CLP', (float) $s['gastos_sin_asignar_clp']]];
    }

    public function title(): string
    {
        return 'Resumen';
    }

    public function styles(Worksheet $sheet)
    {
        return [1 => ['font' => ['bold' => true, 'size' => 16]], 11 => ['font' => ['bold' => true]], 15 => ['font' => ['bold' => true]]];
    }

    public function columnFormats(): array
    {
        return ['B' => '#,##0.00'];
    }
}
