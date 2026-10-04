<?php

namespace App\Http\Controllers;

use App\Models\Trip;
use App\Modules\Access\AccessContext;
use App\Modules\Importations\Actions\RecoverTrip;
use App\Modules\Importations\Queries\ReadTrash;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TripRecoveryController extends Controller
{
    public function index(Request $request, ReadTrash $query)
    {
        $filters = $request->validate(['q' => 'nullable|string|max:150', 'page' => 'nullable|integer|min:1']);

        return Inertia::render('Trips/Trash', $query->read(AccessContext::forRequest($request), $filters));
    }

    public function restore(Request $request, Trip $trip, RecoverTrip $recovery)
    {
        $this->authorize('restore', $trip);
        $data = $request->validate(['expected_revision' => 'required|integer|min:1']);
        $recovery->execute($trip, (int) $data['expected_revision'], true, AccessContext::forRequest($request));

        return to_route('trips.show', $trip)->with('success', 'Importación restaurada con sus costos, documentos y estado original');
    }
}
