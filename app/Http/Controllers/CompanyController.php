<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Trip;
use App\Modules\Importations\Actions\MutateTrip;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class CompanyController extends Controller
{
    public function __construct(private MutateTrip $mutation) {}

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $companies = Auth::user()->companies()
            ->withCount('trips')
            ->orderBy('created_at', 'desc')
            ->get();

        $tripsUnassigned = Company::getTripsUnassigned(Auth::id());

        return Inertia::render('Companies/Index', [
            'companies' => $companies,
            'tripsUnassigned' => $tripsUnassigned,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('Companies/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'rut' => 'nullable|string|max:255',
            'color' => 'required|string|size:7|regex:/^#[0-9A-Fa-f]{6}$/',
            'icono' => 'nullable|string|max:50',
            'descripcion' => 'nullable|string',
        ]);

        $validated['user_id'] = Auth::id();
        $validated['activa'] = true;

        $company = Company::create($validated);

        return redirect()->route('companies.index')
            ->with('success', 'Empresa creada exitosamente');
    }

    /**
     * Display the specified resource.
     */
    public function show(Company $company)
    {
        $this->authorize('view', $company);

        $company->load(['trips' => function ($query) {
            $query->withFinancialTotals()->orderBy('created_at', 'desc');
        }]);

        return Inertia::render('Companies/Show', [
            'company' => $company,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Company $company)
    {
        $this->authorize('update', $company);

        return Inertia::render('Companies/Edit', [
            'company' => $company,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Company $company)
    {
        $this->authorize('update', $company);

        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'rut' => 'nullable|string|max:255',
            'color' => 'required|string|size:7|regex:/^#[0-9A-Fa-f]{6}$/',
            'icono' => 'nullable|string|max:50',
            'descripcion' => 'nullable|string',
            'activa' => 'boolean',
        ]);

        $company->update($validated);

        return redirect()->route('companies.index')
            ->with('success', 'Empresa actualizada exitosamente');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Company $company)
    {
        $this->authorize('delete', $company);

        // Verificar si tiene importaciones asignadas
        if ($company->trips()->count() > 0) {
            return redirect()->route('companies.index')
                ->with('error', 'No puedes eliminar una empresa que tiene importaciones asignadas. Primero reasigna o elimina las importaciones.');
        }

        $company->delete();

        return redirect()->route('companies.index')
            ->with('success', 'Empresa eliminada exitosamente');
    }

    /**
     * Switch the active company in session
     */
    public function switch(Request $request, $companyId = null)
    {
        if ($companyId === 'all') {
            session()->forget('active_company_id');

            return redirect()->back()->with('success', 'Mostrando todas las empresas');
        }

        if ($companyId) {
            $company = Company::findOrFail($companyId);
            $this->authorize('view', $company);

            session(['active_company_id' => $companyId]);

            return redirect()->back()->with('success', "Empresa seleccionada: {$company->nombre}");
        }

        session()->forget('active_company_id');

        return redirect()->back();
    }

    /**
     * Assign trips to a company
     */
    public function assignTrips(Request $request, Company $company)
    {
        $this->authorize('update', $company);

        $validated = $request->validate([
            'trip_ids' => 'required|array|min:1|max:500',
            'trip_ids.*' => ['required', 'integer', 'distinct', Rule::exists('trips', 'id')->whereNull('deleted_at')],
            'expected_revisions' => 'nullable|array', 'expected_revisions.*' => 'required|integer|min:1',
        ]);

        if (isset($validated['expected_revisions']) && (count($validated['expected_revisions']) !== count($validated['trip_ids']) || array_diff($validated['trip_ids'], array_keys($validated['expected_revisions'])))) {
            throw ValidationException::withMessages(['expected_revisions' => 'Incluye la revisión de cada importación seleccionada.']);
        }
        DB::transaction(function () use ($validated, $company) {
            $trips = Trip::whereIn('id', $validated['trip_ids'])->orderBy('id')->lockForUpdate()->get();
            foreach ($trips as $trip) {
                $this->authorize('update', $trip);
            }
            foreach ($trips as $trip) {
                $this->mutation->execute($trip, fn ($current) => $current->update(['company_id' => $company->id]), 'company_assignment', expectedRevision: $validated['expected_revisions'][$trip->id] ?? null);
            }
        });

        $count = count($validated['trip_ids']);

        return redirect()->route('companies.index')
            ->with('success', "{$count} importacion(es) asignada(s) exitosamente a {$company->nombre}");
    }
}
