<?php

namespace App\Http\Controllers;

use App\Http\Requests\TripRequest;
use App\Models\ExpenseCategory;
use App\Models\Trip;
use App\Modules\Access\AccessContext;
use App\Modules\Importations\Actions\MutateTrip;
use App\Modules\Pricing\Domain\Projection;
use App\Services\ProrrateoService;
use Brick\Math\BigDecimal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class TripController extends Controller
{
    public function __construct(private ProrrateoService $prorrateoService, private MutateTrip $mutation) {}

    public function index(Request $request)
    {
        $filters = $request->validate(['q' => 'nullable|string|max:150', 'state' => 'nullable|in:activo,finalizado,archivado',
            'from' => 'nullable|date_format:Y-m-d', 'to' => ['nullable', 'date_format:Y-m-d', ...($request->filled('from') ? ['after_or_equal:from'] : [])],
            'sort' => 'nullable|in:latest,cost,name', 'page' => 'nullable|integer|min:1']);
        $base = AccessContext::forRequest($request)->trips();
        if ($request->filled('q')) {
            $base->where('nombre', 'like', '%'.$request->string('q').'%');
        }
        if ($request->filled('state')) {
            $base->where('estado', $request->string('state'));
        }
        if ($request->filled('from')) {
            $base->whereDate('fecha_inicio', '>=', $request->string('from'));
        }
        if ($request->filled('to')) {
            $base->whereDate('fecha_inicio', '<=', $request->string('to'));
        }
        $financial = (clone $base)->withFinancialTotals()->withCount(['products', 'expenses',
            'products as priced_count' => fn ($q) => $q->whereNotNull('precio_venta')->where('precio_venta', '>', 0)]);
        $aggregate = DB::query()->fromSub((clone $financial)->toBase(), 'filtered')->selectRaw(
            "COUNT(*) as count, COALESCE(SUM(total_productos_clp),0) as purchase, COALESCE(SUM(total_gastos_clp),0) as expenses,
            COALESCE(SUM(CASE WHEN estado='activo' THEN 1 ELSE 0 END),0) as active,
            COALESCE(SUM(products_count),0) as products, COALESCE(SUM(priced_count),0) as priced")->first();
        $sort = $request->input('sort', 'latest');
        match ($sort) {
            'cost' => $financial->orderByDesc('costo_calculado'),'name' => $financial->orderBy('nombre'),default => $financial->latest()
        };
        $trips = $financial->orderBy('id', 'desc')->with('company')->paginate(20)->withQueryString();

        return Inertia::render('Trips/Index', ['trips' => $trips, 'filters' => (object) array_diff_key($filters, ['page' => true]),
            'metrics' => ['count' => (int) $aggregate->count, 'active' => (int) $aggregate->active, 'purchase' => (string) $aggregate->purchase,
                'expenses' => (string) $aggregate->expenses, 'total' => (string) BigDecimal::of((string) $aggregate->purchase)->plus((string) $aggregate->expenses),
                'products' => (int) $aggregate->products, 'priced' => (int) $aggregate->priced]]);
    }

    public function create(Request $request)
    {
        $context = AccessContext::forRequest($request);

        return Inertia::render('Trips/Create', ['companies' => $context->companies(), 'selectedCompanyId' => $context->activeCompany()?->id]);
    }

    public function store(TripRequest $request)
    {
        $data = $request->validated();
        if (! $request->exists('company_id')) {
            $data['company_id'] = AccessContext::forRequest($request)->activeCompany()?->id;
        }
        $trip = Trip::create([...$data, 'user_id' => $request->user()->id, 'moneda_base' => 'CLP', 'estado' => 'activo']);

        return to_route('trips.show', $trip)->with('success', 'Importación creada');
    }

    public function show(Request $request, Trip $trip)
    {
        $this->authorize('view', $trip);
        $trip->load(['expenses.category', 'products', 'company', 'documents']);

        $stats = $this->prorrateoService->obtenerEstadisticas($trip);

        return Inertia::render('Trips/Show', ['trip' => $trip, 'projection' => app(Projection::class)->summarize($trip->products, $stats['gastos_sin_asignar_clp']), 'estadisticas' => $stats,
            'trashedDocuments' => $trip->documents()->onlyTrashed()->latest()->get(),
            'history' => $trip->auditEvents()->with('actor:id,name')->latest('id')->limit(50)->get(),
            'costRuns' => $trip->costRuns()->select('id', 'revision', 'reason', 'algorithm', 'created_at')->latest('id')->limit(50)->get(),
            'costBreakdown' => $trip->costRuns()->latest('id')->first()?->result,
            'categories' => ExpenseCategory::visibleTo($request->user()->id)->orderBy('nombre')->get()]);
    }

    public function edit(Request $request, Trip $trip)
    {
        $this->authorize('update', $trip);

        return Inertia::render('Trips/Edit', ['trip' => $trip, 'companies' => AccessContext::forRequest($request)->companies()]);
    }

    public function update(TripRequest $request, Trip $trip)
    {
        $this->mutation->execute($trip, function ($current) use ($request) {
            $data = $request->safe()->except('expected_revision');
            if ($data['moneda_trabajo'] !== $current->moneda_trabajo &&
                ($current->products()->whereNotIn('moneda', ['CLP', $data['moneda_trabajo']])->exists() ||
                 $current->expenses()->whereNotIn('moneda', ['CLP', $data['moneda_trabajo']])->exists())) {
                throw ValidationException::withMessages(['moneda_trabajo' => 'Esta importación tiene operaciones en su moneda original. Conserva esa moneda para no perder su conversión.']);
            }
            $current->update($data);
        }, 'configuration');

        return to_route('trips.show', $trip)->with('success', 'Importación actualizada y costos recalculados');
    }

    public function close(Request $request, Trip $trip)
    {
        $request->validate(['expected_revision' => 'nullable|integer|min:1']);
        $this->mutation->execute($trip, fn ($current) => $current->update(['estado' => 'finalizado']), 'close', ability: 'close', verify: function ($current, $result) {
            if (! $result->lines || $result->unassigned !== '0') {
                throw ValidationException::withMessages(['cierre' => 'Agrega productos y distribuye todos los gastos antes de cerrar.']);
            }
        });

        return to_route('trips.show', $trip)->with('success', 'Importación cerrada; revisión conservada');
    }

    public function reopen(Request $request, Trip $trip)
    {
        $request->validate(['expected_revision' => 'nullable|integer|min:1']);
        $this->mutation->execute($trip, fn ($current) => $current->update(['estado' => 'activo']), 'reopen', ability: 'reopen');

        return to_route('trips.show', $trip)->with('success', 'Importación reabierta; el cierre anterior permanece en el historial');
    }

    public function destroy(Trip $trip)
    {
        $this->authorize('delete', $trip);
        $trip->delete();

        return to_route('trips.index')->with('success', 'Importación eliminada');
    }
}
