<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportControlRequest;
use App\Http\Requests\ImportDetailRequest;
use App\Http\Requests\TripRequest;
use App\Models\Trip;
use App\Modules\Access\AccessContext;
use App\Modules\Importations\Actions\CloseImportation;
use App\Modules\Importations\Actions\CreateImportation;
use App\Modules\Importations\Actions\MutateTrip;
use App\Modules\Importations\Actions\RecoverTrip;
use App\Modules\Importations\Data\ImportationData;
use App\Modules\Importations\Queries\ControlImports;
use App\Modules\Importations\Queries\ReadImportation;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class TripController extends Controller
{
    public function __construct(private MutateTrip $mutation) {}

    public function index(ImportControlRequest $request, ControlImports $query)
    {
        return Inertia::render('Trips/Index', $query->read(AccessContext::forRequest($request), $request->validated()));
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

    public function show(ImportDetailRequest $request, Trip $trip, ReadImportation $query)
    {
        return Inertia::render('Trips/Show', $query->read(AccessContext::forRequest($request), $trip, $request->validated()));
    }

    public function edit(Request $request, Trip $trip)
    {
        $this->authorize('update', $trip);
        $trip->load(AccessContext::forRequest($request)->companyRelation());

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
        app(CloseImportation::class)->execute($trip, $request->integer('expected_revision'), AccessContext::forRequest($request));

        return to_route('trips.show', $trip)->with('success', 'Importación cerrada; revisión conservada');
    }

    public function reopen(Request $request, Trip $trip)
    {
        $this->authorize('reopen', $trip);
        $data = $request->validate(['expected_revision' => 'required|integer|min:1', 'reason' => 'required|string|min:3|max:1000'],
            ['reason.required' => 'Explica por qué necesitas reabrir la importación.', 'reason.min' => 'El motivo debe contener al menos tres caracteres.', 'reason.max' => 'El motivo admite hasta 1.000 caracteres.']);
        $this->mutation->execute($trip, fn ($current) => $current->update(['estado' => 'activo']), 'reopen', expectedRevision: (int) $data['expected_revision'], ability: 'reopen', recalculate: false, reason: $data['reason'], context: AccessContext::forRequest($request));

        return to_route('trips.show', $trip)->with('success', 'Importación reabierta; motivo registrado y cierre anterior conservado');
    }

    public function destroy(Request $request, Trip $trip, RecoverTrip $recovery)
    {
        $this->authorize('delete', $trip);
        $data = $request->validate(['expected_revision' => 'required|integer|min:1']);
        $recovery->execute($trip, (int) $data['expected_revision'], false, AccessContext::forRequest($request));

        return to_route('trips.index')->with('success', 'Importación movida a la papelera; puedes restaurarla');
    }
}
