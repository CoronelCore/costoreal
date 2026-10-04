<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Modules\Access\AccessContext;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class AnalyticsController extends Controller
{
    public function index(Request $request)
    {
        $context = AccessContext::forRequest($request);
        $trips = $context->trips();
        $tripIds = (clone $trips)->select('trips.id');
        $expenseQuery = Expense::query()->whereIn('trip_id', (clone $tripIds));
        $purchase = DB::table('products')->whereIn('trip_id', (clone $tripIds))->sum('subtotal_clp');
        $expenses = (clone $expenseQuery)->sum('monto_clp');
        $spent = BigDecimal::of((string) $purchase)->plus((string) $expenses);
        $count = (clone $trips)->count();
        $top = (clone $trips)->withFinancialTotals()->withCount(['expenses', 'products'])->orderByDesc('costo_calculado')->limit(5)->get();
        $categories = (clone $expenseQuery)->leftJoin('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')
            ->select('expenses.expense_category_id as id')
            ->selectRaw("COALESCE(expense_categories.nombre,'Sin categoría') as nombre, COALESCE(expense_categories.icono,'dots-horizontal') as icono, COALESCE(expense_categories.color,'#64748b') as color")
            ->selectRaw('COUNT(expenses.id) as total_gastos, SUM(expenses.monto_clp) as total_monto')
            ->groupBy('expenses.expense_category_id', 'expense_categories.nombre', 'expense_categories.icono', 'expense_categories.color')
            ->orderByDesc('total_monto')->get();
        $latest = (clone $expenseQuery)->with(['category', 'trip' => fn ($t) => $t->withFinancialTotals()])->latest()->limit(10)->get();
        $month = match (DB::connection()->getDriverName()) {
            'mysql','mariadb' => "DATE_FORMAT(expenses.created_at, '%Y-%m')",
            'pgsql' => "to_char(expenses.created_at, 'YYYY-MM')",
            'sqlite' => "strftime('%Y-%m', expenses.created_at)",
            default => throw new \RuntimeException('Motor de base de datos no certificado para analítica.'),
        };
        $evolution = (clone $expenseQuery)->where('expenses.created_at', '>=', now()->startOfMonth()->subMonths(5))
            ->selectRaw("{$month} as mes, SUM(expenses.monto_clp) as total")->groupByRaw($month)->orderBy('mes')->get();

        return Inertia::render('Analytics/Index', [
            'totalTrips' => $count, 'totalGastado' => (string) $spent,
            'promedioViaje' => (string) ($count ? $spent->dividedBy($count, 0, RoundingMode::HALF_UP) : BigDecimal::zero()),
            'viajeMasCostoso' => $top->first(), 'gastosPorCategoria' => $categories, 'topViajes' => $top,
            'ultimosGastos' => $latest, 'evolucionMensual' => $evolution,
        ]);
    }
}
