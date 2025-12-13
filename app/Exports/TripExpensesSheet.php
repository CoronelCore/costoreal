<?php

namespace App\Exports;

use App\Models\Trip;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TripExpensesSheet implements FromArray, WithTitle, WithHeadings, WithStyles
{
    protected $trip;

    public function __construct(Trip $trip)
    {
        $this->trip = $trip;
    }

    public function array(): array
    {
        $data = [];

        foreach ($this->trip->expenses as $expense) {
            $data[] = [
                $expense->category->nombre,
                $expense->concepto,
                $expense->moneda,
                number_format($expense->monto, 2),
                '$' . number_format($expense->monto_clp, 0, ',', '.'),
            ];
        }

        // Agregar total
        $data[] = [];
        $data[] = [
            '',
            '',
            '',
            'TOTAL:',
            '$' . number_format($this->trip->total_gastos, 0, ',', '.'),
        ];

        return $data;
    }

    public function headings(): array
    {
        return [
            'Categoría',
            'Concepto',
            'Moneda',
            'Monto Original',
            'Monto CLP'
        ];
    }

    public function title(): string
    {
        return 'Gastos';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
