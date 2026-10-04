<?php

namespace App\Http\Controllers;

use App\Http\Requests\TripRequest;
use App\Models\ExpenseCategory;
use App\Models\LegacyCostBaseline;
use App\Models\Trip;
use App\Modules\Access\AccessContext;
use App\Modules\Importations\Actions\CloseImportation;
use App\Modules\Importations\Actions\CreateImportation;
use App\Modules\Importations\Actions\MutateTrip;
use App\Modules\Importations\Actions\RecoverTrip;
use App\Modules\Importations\Data\ImportationData;
use App\Modules\Pricing\Data\PricingData;
use App\Modules\Reporting\FinancialSummaryData;
use Brick\Math\BigDecimal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class TripController extends Controller
{
    public function __construct(private MutateTrip $mutation) {}

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
        $trips = $financial->orderBy('id', 'desc')->with('company')->paginate(20)->withQueryString()->through(fn ($trip) => ImportationData::listing($trip));

        return Inertia::render('Trips/Index', ['trips' => $trips, 'filters' => (object) array_diff_key($filters, ['page' => true]),
            'metrics' => ['count' => (int) $aggregate->count, 'active' => (int) $aggregate->active, 'purchase' => (string) $aggregate->purchase,
                'expenses' => (string) $aggregate->expenses, 'total' => (string) BigDecimal::of((string) $aggregate->purchase)->plus((string) $aggregate->expenses),
                'products' => (int) $aggregate->products, 'priced' => (int) $aggregate->priced]]);
    }

    public function create(Request $request)
    {
        return app(ImportDraftController::class)->create($request);
    }

    public function store(TripRequest $request)
    {
        $data = $request->validated();
        if (! $request->exists('company_id')) {
            $data['company_id'] = AccessContext::forRequest($request)->activeCompany()?->id;
        }
        $trip = app(CreateImportation::class)->execute($request->user(), $data);

        return to_route('trips.show', $trip)->with('success', 'Importación creada');
    }

    public function show(Request $request, Trip $trip)
    {
        $this->authorize('view', $trip);
        $request->validate(['section' => 'nullable|in:resumen,productos,gastos,precio-venta,revision,historial,documentos,escenarios,config']);
        $trip->load(['expenses.category', 'products', 'company', 'documents']);

        $summary = FinancialSummaryData::fromTrip($trip);

        $batch = DB::table('pricing_batches')->where('trip_id', $trip->id)->latest('id')->first();
        $lastBatch = $batch ? ['revision' => (int) $batch->revision, 'policy' => PricingData::policy(json_decode($batch->policy, true, flags: JSON_THROW_ON_ERROR)),
            'lines_count' => count(json_decode($batch->result, true, flags: JSON_THROW_ON_ERROR)['lines'])] : null;
        $latestRun = $trip->costRuns()->select('id', 'revision', 'reason', 'algorithm', 'created_at')->latest('id')->first();

        return Inertia::render('Trips/Show', ['initialTab' => $request->input('section'), 'trip' => ImportationData::detail($trip, $summary), 'lastPricingBatch' => $lastBatch, 'projection' => $summary->projection, 'estadisticas' => $summary->statistics,
            'scenarios' => fn () => app(ScenarioController::class)->listing($trip, 'scenario_page'),
            'trashedDocuments' => $trip->documents()->onlyTrashed()->latest()->get()->map(fn ($d) => ImportationData::document($d)),
            'history' => fn () => app(AuditController::class)->listing($trip, 'history_page'),
            'latestCostRun' => $latestRun,
            'legacyBaseline' => LegacyCostBaseline::where('trip_id', $trip->id)->first(['id', 'revision', 'created_at']),
            'categories' => ExpenseCategory::visibleTo($request->user()->id)->orderBy('nombre')->get(['id', 'nombre'])]);
    }

    public function edit(Request $request, Trip $trip)
    {
        $this->authorize('update', $trip);

        return Inertia::render('Trips/Edit', ['trip' => ImportationData::configuration($trip), 'companies' => AccessContext::forRequest($request)->companies()]);
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
        $this->authorize('close', $trip);
        $request->validate(['expected_revision' => 'required|integer|min:1']);
        app(CloseImportation::class)->execute($trip, $request->integer('expected_revision'));

        return to_route('trips.show', $trip)->with('success', 'Importación cerrada; revisión conservada');
    }

    public function reopen(Request $request, Trip $trip)
    {
        $this->authorize('reopen', $trip);
        $data = $request->validate(['expected_revision' => 'required|integer|min:1', 'reason' => 'required|string|min:3|max:1000'],
            ['reason.required' => 'Explica por qué necesitas reabrir la importación.', 'reason.min' => 'El motivo debe contener al menos tres caracteres.', 'reason.max' => 'El motivo admite hasta 1.000 caracteres.']);
        $this->mutation->execute($trip, fn ($current) => $current->update(['estado' => 'activo']), 'reopen', expectedRevision: (int) $data['expected_revision'], ability: 'reopen', recalculate: false, reason: $data['reason']);

        return to_route('trips.show', $trip)->with('success', 'Importación reabierta; motivo registrado y cierre anterior conservado');
    }

    public function destroy(Request $request, Trip $trip, RecoverTrip $recovery)
    {
        $this->authorize('delete', $trip);
        $data = $request->validate(['expected_revision' => 'required|integer|min:1']);
        $recovery->execute($trip, (int) $data['expected_revision'], false);

        return to_route('trips.index')->with('success', 'Importación movida a la papelera; puedes restaurarla');
    }
}
