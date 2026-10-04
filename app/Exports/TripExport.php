<?php

namespace App\Exports;

use App\Models\Trip;
use App\Modules\Reporting\FinancialSummaryData;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class TripExport implements WithMultipleSheets
{
    protected $trip;

    public function __construct(Trip $trip)
    {
        $this->trip = $trip->loadMissing(['expenses.category', 'products']);
    }

    public function sheets(): array
    {
        $summary = FinancialSummaryData::fromTrip($this->trip);

        return [
            new TripSummarySheet($this->trip, $summary),
            new TripExpensesSheet($this->trip, $summary),
            new TripProductsSheet($this->trip, $summary),
        ];
    }
}
