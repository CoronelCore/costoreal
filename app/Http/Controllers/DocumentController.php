<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Trip;
use App\Modules\Access\AccessContext;
use App\Modules\Documents\ReadDocument;
use App\Modules\Documents\StoreDocument;
use App\Modules\Importations\Actions\MutateTrip;
use Illuminate\Http\Request;

class DocumentController extends Controller
{
    public function store(Request $request, Trip $trip, StoreDocument $storage, MutateTrip $mutation)
    {
        $this->authorize('update', $trip);
        $request->validate(['file' => 'required|file|mimes:jpeg,png,webp,pdf|max:10240', 'expected_revision' => 'required|integer|min:1']);
        $context = AccessContext::forRequest($request);
        $stage = $storage->stage($request->file('file'), $context);
        try {
            $mutation->execute($trip, fn ($current) => $storage->attach($stage, $current, 'other', context: $context), 'document_upload', recalculate: false, context: $context);
        } catch (\Throwable $e) {
            $storage->discard($stage, $context);
            throw $e;
        }

        return to_route('trips.show', $trip)->with('success', 'Documento privado guardado');
    }

    public function show(Request $request, Trip $trip, Document $document, ReadDocument $query)
    {
        return $query->read(AccessContext::forRequest($request), $trip, $document, $request->boolean('download'));
    }

    public function destroy(Request $request, Trip $trip, Document $document, MutateTrip $mutation)
    {
        $request->validate(['expected_revision' => 'required|integer|min:1']);
        $mutation->execute($trip, fn ($current) => $current->documents()->findOrFail($document->id)->delete(), 'document_trash', recalculate: false);

        return to_route('trips.show', $trip)->with('success', 'Documento movido a la papelera; puedes restaurarlo');
    }

    public function restore(Request $request, Trip $trip, Document $document, MutateTrip $mutation)
    {
        $request->validate(['expected_revision' => 'required|integer|min:1']);
        $mutation->execute($trip, fn ($current) => $current->documents()->onlyTrashed()->findOrFail($document->id)->restore(), 'document_restore', recalculate: false);

        return to_route('trips.show', $trip)->with('success', 'Documento restaurado');
    }
}
