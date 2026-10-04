<?php

namespace App\Exports;

use App\Models\Trip;
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
        return [
            new TripSummarySheet($this->trip),
            new TripExpensesSheet($this->trip),
            new TripProductsSheet($this->trip),
        ];
    }
}
