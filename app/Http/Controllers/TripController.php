<?php

namespace App\Http\Controllers;

use App\Models\Trip;
use App\Services\ProrrateoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class TripController extends Controller
{
    protected $prorrateoService;

    public function __construct(ProrrateoService $prorrateoService)
    {
        $this->prorrateoService = $prorrateoService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $query = Auth::user()->trips()->with('company')->withCount(['expenses', 'products']);

        // Filtrar por empresa si hay una seleccionada
        $activeCompanyId = session('active_company_id');
        if ($activeCompanyId) {
            $query->where('company_id', $activeCompanyId);
        }

        $trips = $query->orderBy('created_at', 'desc')->get();

        return Inertia::render('Trips/Index', [
            'trips' => $trips,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $companies = Auth::user()->companies()->where('activa', true)->get();
        $selectedCompanyId = session('active_company_id');

        return Inertia::render('Trips/Create', [
            'companies' => $companies,
            'selectedCompanyId' => $selectedCompanyId,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'company_id' => 'nullable|exists:companies,id',
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'fecha_inicio' => 'nullable|date',
            'fecha_fin' => 'nullable|date|after_or_equal:fecha_inicio',
            'moneda_trabajo' => 'required|in:USD,BRL,PEN',
            'tipo_cambio' => 'required|numeric|min:0',
        ]);

        $validated['user_id'] = Auth::id();
        $validated['moneda_base'] = 'CLP';
        $validated['estado'] = 'activo';

        // Si no se especificó empresa, usar la de la sesión si existe
        if (!isset($validated['company_id']) && session('active_company_id')) {
            $validated['company_id'] = session('active_company_id');
        }

        $trip = Trip::create($validated);

        return redirect()->route('trips.show', $trip)
            ->with('success', 'Importación creada exitosamente');
    }

    /**
     * Display the specified resource.
     */
    public function show(Trip $trip)
    {
        $this->authorize('view', $trip);

        $trip->load(['expenses.category', 'products', 'company']);
        $estadisticas = $this->prorrateoService->obtenerEstadisticas($trip);
        $categories = \App\Models\ExpenseCategory::all();

        return Inertia::render('Trips/Show', [
            'trip' => $trip,
            'estadisticas' => $estadisticas,
            'categories' => $categories,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Trip $trip)
    {
        $this->authorize('update', $trip);
        $companies = Auth::user()->companies()->where('activa', true)->get();

        return Inertia::render('Trips/Edit', [
            'trip' => $trip,
            'companies' => $companies,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Trip $trip)
    {
        $this->authorize('update', $trip);

        $validated = $request->validate([
            'company_id' => 'nullable|exists:companies,id',
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'fecha_inicio' => 'nullable|date',
            'fecha_fin' => 'nullable|date|after_or_equal:fecha_inicio',
            'moneda_trabajo' => 'required|in:USD,BRL,PEN',
            'tipo_cambio' => 'required|numeric|min:0',
            'estado' => 'required|in:activo,finalizado,archivado',
        ]);

        $trip->update($validated);

        // Recalcular prorrateo si cambió el tipo de cambio
        $this->prorrateoService->calcularProrrateo($trip);

        return redirect()->route('trips.show', $trip)
            ->with('success', 'Importación actualizada exitosamente');
    }

    /**
     * Close/finalize a trip
     */
    public function close(Trip $trip)
    {
        $this->authorize('update', $trip);

        $trip->update(['estado' => 'finalizado']);

        return redirect()->route('trips.show', $trip)
            ->with('success', 'Importación finalizada exitosamente');
    }

    /**
     * Reopen a finalized trip
     */
    public function reopen(Trip $trip)
    {
        $this->authorize('update', $trip);

        $trip->update(['estado' => 'activo']);

        return redirect()->route('trips.show', $trip)
            ->with('success', 'Importación reabierta exitosamente');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Trip $trip)
    {
        $this->authorize('delete', $trip);

        $trip->delete();

        return redirect()->route('trips.index')
            ->with('success', 'Importación eliminada exitosamente');
    }
}
