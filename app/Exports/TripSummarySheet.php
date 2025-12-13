<?php

namespace App\Exports;

use App\Models\Trip;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TripSummarySheet implements FromArray, WithTitle, WithStyles
{
    protected $trip;

    public function __construct(Trip $trip)
    {
        $this->trip = $trip;
    }

    public function array(): array
    {
        return [
            ['RESUMEN DEL VIAJE'],
            [],
            ['Nombre:', $this->trip->nombre],
            ['Descripción:', $this->trip->descripcion],
            ['Fecha Inicio:', $this->trip->fecha_inicio->format('d/m/Y')],
            ['Fecha Fin:', $this->trip->fecha_fin->format('d/m/Y')],
            ['Moneda Base:', $this->trip->moneda_base],
            ['Moneda Trabajo:', $this->trip->moneda_trabajo],
            ['Tipo de Cambio:', number_format($this->trip->tipo_cambio, 2)],
            [],
            ['TOTALES'],
            [],
            ['Total Gastos:', '$' . number_format($this->trip->total_gastos, 0, ',', '.')],
            ['Total Productos:', '$' . number_format($this->trip->total_productos, 0, ',', '.')],
            ['COSTO TOTAL:', '$' . number_format($this->trip->costo_total, 0, ',', '.')],
        ];
    }

    public function title(): string
    {
        return 'Resumen';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 16]],
            11 => ['font' => ['bold' => true, 'size' => 14]],
            15 => ['font' => ['bold' => true, 'size' => 12]],
        ];
    }
}
