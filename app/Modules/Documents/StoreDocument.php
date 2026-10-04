<?php

namespace App\Modules\Documents;

use App\Models\Document;
use App\Models\Expense;
use App\Models\Product;
use App\Models\Trip;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

final class StoreDocument
{
    public function stage(UploadedFile $file): array
    {
        $path = $file->store('documents', 'local');
        if (! $path) {
            throw new \RuntimeException('No se pudo guardar el documento.');
        }

        return ['storage_path' => $path, 'filename' => mb_substr(preg_replace('/[\x00-\x1f\x7f]/u', '', basename(str_replace('\\', '/', $file->getClientOriginalName()))) ?: 'documento', 0, 255),
            'mime' => $file->getMimeType(), 'size' => $file->getSize(), 'sha256' => hash_file('sha256', $file->getRealPath())];
    }

    public function attach(array $stage, Trip $trip, string $kind, Expense|Product|null $parent = null): Document
    {
        return Document::create([...$stage, 'trip_id' => $trip->id, 'kind' => $kind, 'uploaded_by' => auth()->id(),
            'expense_id' => $parent instanceof Expense ? $parent->id : null, 'product_id' => $parent instanceof Product ? $parent->id : null]);
    }

    public function discard(?array $stage): void
    {
        if ($stage) {
            Storage::disk('local')->delete($stage['storage_path']);
        }
    }
}
