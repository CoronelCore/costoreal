<?php

namespace App\Http\Controllers;

use App\Http\Requests\CompanyRequest;
use App\Models\Company;
use App\Models\Trip;
use App\Modules\Companies\ReadCompany;
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
    public function index(Request $request, ReadCompany $read)
    {
        return Inertia::render('Companies/Index', ['companies' => $read->index($request->user())]);
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
    public function store(CompanyRequest $request)
    {
        $validated = $request->validated();

        $validated['user_id'] = Auth::id();
        $validated['activa'] = true;

        $company = Company::create($validated);

        return redirect()->route('companies.index')
            ->with('success', 'Empresa creada exitosamente');
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Company $company, ReadCompany $read)
    {
        $request->validate(['page' => 'nullable|integer|min:1']);

        return Inertia::render('Companies/Show', $read->show($request->user(), $company));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Company $company)
    {
        $this->authorize('update', $company);

        return Inertia::render('Companies/Edit', [
            'companyRecord' => app(ReadCompany::class)->record($company),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(CompanyRequest $request, Company $company)
    {
        $validated = $request->validated();

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
        if ($company->trips()->withTrashed()->exists()) {
            return redirect()->route('companies.index')
                ->with('error', 'No puedes eliminar una empresa que tiene importaciones asignadas. Reasigna primero las importaciones, incluidas las de la papelera.');
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
            'expected_revisions' => 'required|array', 'expected_revisions.*' => 'required|integer|min:1',
        ]);

        if (count($validated['expected_revisions']) !== count($validated['trip_ids']) || array_diff($validated['trip_ids'], array_keys($validated['expected_revisions']))) {
            throw ValidationException::withMessages(['expected_revisions' => 'Incluye la revisión de cada importación seleccionada.']);
        }
        DB::transaction(function () use ($validated, $company) {
            $trips = Trip::whereIn('id', $validated['trip_ids'])->orderBy('id')->lockForUpdate()->get();
            foreach ($trips as $trip) {
                $this->authorize('update', $trip);
            }
            foreach ($trips as $trip) {
                $this->mutation->execute($trip, fn ($current) => $current->update(['company_id' => $company->id]), 'company_assignment', expectedRevision: (int) $validated['expected_revisions'][$trip->id]);
            }
        });

        $count = count($validated['trip_ids']);

        return redirect()->route('companies.index')
            ->with('success', "{$count} importacion(es) asignada(s) exitosamente a {$company->nombre}");
    }
}
