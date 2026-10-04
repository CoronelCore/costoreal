<?php

namespace App\Exports;

use App\Models\Trip;
use App\Modules\Reporting\FinancialSummaryData;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TripExpensesSheet extends SafeValueBinder implements FromArray, WithColumnFormatting, WithCustomValueBinder, WithHeadings, WithStrictNullComparison, WithStyles, WithTitle
{
    private FinancialSummaryData $summary;

    public function __construct(private Trip $trip, ?FinancialSummaryData $summary = null)
    {
        $this->summary = $summary ?? FinancialSummaryData::fromTrip($trip);
        $trip->loadMissing('expenses.category');
    }

    public function array(): array
    {
        $rows = [];
        foreach ($this->trip->expenses as $e) {
            $rows[] = [$e->category?->nombre ?? 'Sin categoría', $e->concepto, $e->moneda, (float) $e->monto, (float) $e->monto_clp];
        }
        $rows[] = [];
        $rows[] = [null, null, null, 'TOTAL CLP', (float) $this->summary->statistics['total_gastos_clp']];

        return $rows;
    }

    public function headings(): array
    {
        return ['Categoría', 'Concepto', 'Moneda original', 'Monto original', 'Monto CLP'];
    }

    public function title(): string
    {
        return 'Gastos';
    }

    public function styles(Worksheet $sheet)
    {
        return [1 => ['font' => ['bold' => true]]];
    }

    public function columnFormats(): array
    {
        return ['D' => '#,##0.00', 'E' => '#,##0'];
    }
}
