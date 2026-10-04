<?php

namespace App\Http\Controllers;

use App\Exports\TripExport;
use App\Models\Trip;
use App\Modules\Access\AccessContext;
use App\Modules\Reporting\FinancialPdf;
use App\Modules\Reporting\FinancialSummaryData;
use App\Modules\Reporting\ReportTrip;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ExportController extends Controller
{
    public function exportPdf(Request $request, Trip $trip)
    {
        $this->authorize('view', $trip);
        $request->validate(['run' => 'nullable|integer|min:1|prohibits:baseline', 'baseline' => 'nullable|integer|min:1|prohibits:run']);
        $trip = app(ReportTrip::class)->resolve($trip, $request->integer('run') ?: null, $request->integer('baseline') ?: null, AccessContext::forRequest($request));

        $summary = FinancialSummaryData::fromTrip($trip);

        return response((new FinancialPdf($trip, $summary))->render(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="importacion-'.$trip->id.'-'.now()->format('Y-m-d').'.pdf"',
        ]);
    }

    public function exportExcel(Request $request, Trip $trip)
    {
        $this->authorize('view', $trip);
        $request->validate(['run' => 'nullable|integer|min:1|prohibits:baseline', 'baseline' => 'nullable|integer|min:1|prohibits:run']);
        $trip = app(ReportTrip::class)->resolve($trip, $request->integer('run') ?: null, $request->integer('baseline') ?: null, AccessContext::forRequest($request));

        return Excel::download(new TripExport($trip), 'importacion-'.$trip->id.'-'.now()->format('Y-m-d').'.xlsx');
    }
}
