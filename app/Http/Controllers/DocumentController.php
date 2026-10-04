<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Trip;
use App\Modules\Documents\StoreDocument;
use App\Modules\Importations\Actions\MutateTrip;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    public function store(Request $request, Trip $trip, StoreDocument $storage, MutateTrip $mutation)
    {
        $this->authorize('update', $trip);
        $request->validate(['file' => 'required|file|mimes:jpeg,png,webp,pdf|max:10240', 'expected_revision' => 'required|integer|min:1']);
        $stage = $storage->stage($request->file('file'));
        try {
            $mutation->execute($trip, fn ($current) => $storage->attach($stage, $current, 'other'), 'document_upload');
        } catch (\Throwable $e) {
            $storage->discard($stage);
            throw $e;
        }

        return to_route('trips.show', $trip)->with('success', 'Documento privado guardado');
    }

    public function show(Request $request, Trip $trip, Document $document)
    {
        $this->authorize('view', $trip);
        abort_unless(Storage::disk('local')->exists($document->storage_path), 404, 'El archivo no está disponible.');
        $headers = ['Content-Type' => $document->mime, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store', 'Content-Security-Policy' => "sandbox; default-src 'none'"];
        if (! $request->boolean('download') && in_array($document->mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return Storage::disk('local')->response($document->storage_path, $document->filename, $headers, 'inline');
        }

        return Storage::disk('local')->download($document->storage_path, $document->filename, $headers);
    }

    public function destroy(Request $request, Trip $trip, Document $document, MutateTrip $mutation)
    {
        $request->validate(['expected_revision' => 'required|integer|min:1']);
        $mutation->execute($trip, fn () => $document->delete(), 'document_trash');

        return to_route('trips.show', $trip)->with('success', 'Documento movido a la papelera; puedes restaurarlo');
    }

    public function restore(Request $request, Trip $trip, Document $document, MutateTrip $mutation)
    {
        $request->validate(['expected_revision' => 'required|integer|min:1']);
        $mutation->execute($trip, fn () => $document->restore(), 'document_restore');

        return to_route('trips.show', $trip)->with('success', 'Documento restaurado');
    }
}
