<?php

namespace App\Http\Controllers;

use App\Models\Trip;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\TripExport;

class ExportController extends Controller
{
    public function exportPdf(Trip $trip)
    {
        $this->authorize('view', $trip);

        $trip->load(['expenses.category', 'products']);

        // Agrupar gastos por categoría
        $expensesByCategory = $trip->expenses->groupBy('category.nombre')->map(function ($expenses) {
            return [
                'categoria' => $expenses->first()->category->nombre,
                'icono' => $expenses->first()->category->icono,
                'color' => $expenses->first()->category->color,
                'gastos' => $expenses,
                'total' => $expenses->sum('monto_clp')
            ];
        });

        $pdf = Pdf::loadView('exports.trip-pdf', [
            'trip' => $trip,
            'expensesByCategory' => $expensesByCategory,
        ]);

        return $pdf->download('viaje-' . $trip->id . '-' . now()->format('Y-m-d') . '.pdf');
    }

    public function exportExcel(Trip $trip)
    {
        $this->authorize('view', $trip);

        return Excel::download(
            new TripExport($trip),
            'viaje-' . $trip->id . '-' . now()->format('Y-m-d') . '.xlsx'
        );
    }
}
