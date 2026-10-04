<?php

namespace App\Modules\Documents;

use App\Models\Document;
use App\Models\Trip;
use App\Modules\Access\AccessContext;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ReadDocument
{
    public function read(AccessContext $context, Trip $trip, Document $document, bool $download = false): StreamedResponse
    {
        $context->authorizeTrip($trip);
        $current = Trip::findOrFail($trip->id);
        $context->authorizeTrip($current);
        $record = $current->documents()->findOrFail($document->id);
        abort_unless(Storage::disk('local')->exists($record->storage_path), 404, 'El archivo no está disponible.');
        $headers = ['Content-Type' => $record->mime, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store', 'Content-Security-Policy' => "sandbox; default-src 'none'"];
        if (! $download && in_array($record->mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return Storage::disk('local')->response($record->storage_path, $record->filename, $headers, 'inline');
        }

        return Storage::disk('local')->download($record->storage_path, $record->filename, $headers);
    }
}
