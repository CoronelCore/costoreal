<?php

namespace App\Exports;

use App\Models\Trip;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TripProductsSheet implements FromArray, WithTitle, WithHeadings, WithStyles
{
    protected $trip;

    public function __construct(Trip $trip)
    {
        $this->trip = $trip;
    }

    public function array(): array
    {
        $data = [];

        foreach ($this->trip->products as $product) {
            $data[] = [
                $product->nombre,
                $product->cantidad,
                '$' . number_format($product->precio_unitario, 0, ',', '.'),
                '$' . number_format($product->subtotal, 0, ',', '.'),
                number_format($product->porcentaje_prorrateo, 2) . '%',
                '$' . number_format($product->gastos_prorrateados, 0, ',', '.'),
                '$' . number_format($product->costo_final, 0, ',', '.'),
                '$' . number_format($product->costo_unitario, 0, ',', '.'),
            ];
        }

        // Agregar total
        $data[] = [];
        $data[] = [
            'TOTAL:',
            '',
            '',
            '$' . number_format($this->trip->products->sum('subtotal'), 0, ',', '.'),
            '',
            '$' . number_format($this->trip->products->sum('gastos_prorrateados'), 0, ',', '.'),
            '$' . number_format($this->trip->products->sum('costo_final'), 0, ',', '.'),
            '',
        ];

        return $data;
    }

    public function headings(): array
    {
        return [
            'Producto',
            'Cantidad',
            'Precio Unit.',
            'Subtotal',
            '% Prorrateo',
            'Gastos Prorr.',
            'Costo Final',
            'Costo Unit. Final'
        ];
    }

    public function title(): string
    {
        return 'Productos';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
