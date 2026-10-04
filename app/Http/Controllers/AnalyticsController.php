<?php

namespace App\Http\Controllers;

use App\Modules\Access\AccessContext;
use App\Modules\Analytics\AnalyticsQuery;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AnalyticsController extends Controller
{
    public function index(Request $request, AnalyticsQuery $query)
    {
        $filters = $request->validate([
            'state' => 'nullable|in:activo,finalizado,archivado',
            'from' => 'nullable|date_format:Y-m-d',
            'to' => ['nullable', 'date_format:Y-m-d', ...($request->filled('from') ? ['after_or_equal:from'] : [])],
            'imports_page' => 'nullable|integer|min:1|max:100000',
            'categories_page' => 'nullable|integer|min:1|max:100000',
            'months_page' => 'nullable|integer|min:1|max:100000',
        ], [
            'state.in' => 'Selecciona un estado válido.',
            'from.date_format' => 'Ingresa una fecha de inicio válida.',
            'to.date_format' => 'Ingresa una fecha de fin válida.',
            'to.after_or_equal' => 'Hasta debe ser igual o posterior a Desde.',
        ]);

        return Inertia::render('Analytics/Index', $query->read(AccessContext::forRequest($request), $filters, $request));
    }
}
