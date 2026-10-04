<?php

namespace App\Http\Controllers;

use App\Exports\TripExport;
use App\Models\Trip;
use App\Modules\Reporting\FinancialSummaryData;
use App\Modules\Reporting\ReportTrip;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ExportController extends Controller
{
    public function exportPdf(Request $request, Trip $trip)
    {
        $this->authorize('view', $trip);
        $request->validate(['run' => 'nullable|integer|min:1']);
        $trip = app(ReportTrip::class)->resolve($trip, $request->integer('run') ?: null);

        $summary = FinancialSummaryData::fromTrip($trip);

        return Pdf::loadView('exports.trip-pdf', ['trip' => $trip, 'stats' => $summary->statistics, 'projection' => $summary->projection])
            ->download('importacion-'.$trip->id.'-'.now()->format('Y-m-d').'.pdf');
    }

    public function exportExcel(Request $request, Trip $trip)
    {
        $this->authorize('view', $trip);
        $request->validate(['run' => 'nullable|integer|min:1']);
        $trip = app(ReportTrip::class)->resolve($trip, $request->integer('run') ?: null);

        return Excel::download(new TripExport($trip), 'importacion-'.$trip->id.'-'.now()->format('Y-m-d').'.xlsx');
    }
}
