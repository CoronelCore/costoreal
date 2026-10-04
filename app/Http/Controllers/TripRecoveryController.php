<?php

namespace App\Http\Controllers;

use App\Models\Trip;
use App\Modules\Access\AccessContext;
use App\Modules\Importations\Actions\RecoverTrip;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TripRecoveryController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate(['q' => 'nullable|string|max:150', 'page' => 'nullable|integer|min:1']);
        $trips = AccessContext::forRequest($request)->trips()->onlyTrashed()
            ->when($request->filled('q'), fn ($q) => $q->where('nombre', 'like', '%'.$request->string('q').'%'))
            ->withFinancialTotals()->withCount(['products', 'expenses', 'documents'])->with('company')
            ->orderByDesc('deleted_at')->orderByDesc('id')->paginate(20)->withQueryString();

        return Inertia::render('Trips/Trash', ['trips' => $trips, 'filters' => (object) array_diff_key($filters, ['page' => true])]);
    }

    public function restore(Request $request, Trip $trip, RecoverTrip $recovery)
    {
        $this->authorize('restore', $trip);
        $data = $request->validate(['expected_revision' => 'required|integer|min:1']);
        $recovery->execute($trip, (int) $data['expected_revision'], true);

        return to_route('trips.show', $trip)->with('success', 'Importación restaurada con sus costos, documentos y estado original');
    }
}
