<?php

namespace App\Http\Controllers;

use App\Models\Trip;
use App\Models\ExpenseCategory;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class AnalyticsController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $activeCompanyId = session('active_company_id');

        // Consulta base de trips con filtro de empresa
        $tripsQuery = $user->trips();
        if ($activeCompanyId) {
            $tripsQuery->where('company_id', $activeCompanyId);
        }

        // KPIs Principales
        $totalTrips = (clone $tripsQuery)->count();
        $totalGastado = (clone $tripsQuery)->get()->sum('costo_total');
        $promedioViaje = $totalTrips > 0 ? $totalGastado / $totalTrips : 0;

        $viajeMasCostoso = (clone $tripsQuery)
            ->withCount(['expenses', 'products'])
            ->get()
            ->sortByDesc('costo_total')
            ->first();

        // Gastos por categoría (filtrado por empresa si corresponde)
        $gastosPorCategoria = ExpenseCategory::query()
            ->select('expense_categories.id', 'expense_categories.nombre', 'expense_categories.icono', 'expense_categories.color')
            ->selectRaw('COUNT(expenses.id) as total_gastos')
            ->selectRaw('COALESCE(SUM(expenses.monto_clp), 0) as total_monto')
            ->leftJoin('expenses', 'expense_categories.id', '=', 'expenses.expense_category_id')
            ->leftJoin('trips', function($join) use ($user, $activeCompanyId) {
                $join->on('expenses.trip_id', '=', 'trips.id')
                     ->where('trips.user_id', '=', $user->id);
                if ($activeCompanyId) {
                    $join->where('trips.company_id', '=', $activeCompanyId);
                }
            })
            ->groupBy('expense_categories.id', 'expense_categories.nombre', 'expense_categories.icono', 'expense_categories.color')
            ->orderByDesc('total_monto')
            ->get();

        // Top 5 viajes más costosos (filtrado por empresa)
        $topViajes = (clone $tripsQuery)
            ->select('trips.*')
            ->selectRaw('(SELECT COALESCE(SUM(monto_clp), 0) FROM expenses WHERE expenses.trip_id = trips.id) +
                         (SELECT COALESCE(SUM(subtotal_clp), 0) FROM products WHERE products.trip_id = trips.id) as costo_calculado')
            ->orderByDesc('costo_calculado')
            ->limit(5)
            ->get();

        // Últimos 10 gastos registrados (filtrado por empresa)
        $ultimosGastosQuery = (clone $tripsQuery)
            ->with(['expenses' => function($query) {
                $query->with('category')->latest()->limit(10);
            }])
            ->get()
            ->pluck('expenses')
            ->flatten()
            ->sortByDesc('created_at')
            ->take(10);
        $ultimosGastos = $ultimosGastosQuery;

        // Evolución temporal - Gastos por mes (últimos 6 meses, filtrado por empresa)
        $evolucionMensualQuery = DB::table('trips')
            ->join('expenses', 'trips.id', '=', 'expenses.trip_id')
            ->where('trips.user_id', $user->id)
            ->where('expenses.created_at', '>=', now()->subMonths(6));

        if ($activeCompanyId) {
            $evolucionMensualQuery->where('trips.company_id', $activeCompanyId);
        }

        $evolucionMensual = $evolucionMensualQuery
            ->selectRaw("DATE_FORMAT(expenses.created_at, '%Y-%m') as mes")
            ->selectRaw('SUM(expenses.monto_clp) as total')
            ->groupBy('mes')
            ->orderBy('mes')
            ->get();

        return Inertia::render('Analytics/Index', [
            'totalTrips' => $totalTrips,
            'totalGastado' => $totalGastado,
            'promedioViaje' => $promedioViaje,
            'viajeMasCostoso' => $viajeMasCostoso,
            'gastosPorCategoria' => $gastosPorCategoria,
            'topViajes' => $topViajes,
            'ultimosGastos' => $ultimosGastos,
            'evolucionMensual' => $evolucionMensual,
        ]);
    }
}
