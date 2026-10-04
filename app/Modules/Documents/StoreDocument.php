<?php

namespace App\Modules\Documents;

use App\Models\Document;
use App\Models\Expense;
use App\Models\Product;
use App\Models\Trip;
use App\Modules\Access\AccessContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

final class StoreDocument
{
    public function stage(UploadedFile $file, ?AccessContext $context = null): array
    {
        $context ??= AccessContext::current();
        $actor = $context->actorId();
        $path = $file->store('documents', 'local');
        if (! $path) {
            throw new \RuntimeException('No se pudo guardar el documento.');
        }

        return ['staged_by' => $actor, 'storage_path' => $path, 'filename' => mb_substr(preg_replace('/[\x00-\x1f\x7f]/u', '', basename(str_replace('\\', '/', $file->getClientOriginalName()))) ?: 'documento', 0, 255),
            'mime' => $file->getMimeType(), 'size' => $file->getSize(), 'sha256' => hash_file('sha256', $file->getRealPath())];
    }

    public function attach(array $stage, Trip $trip, string $kind, Expense|Product|null $parent = null, ?AccessContext $context = null): Document
    {
        $context ??= AccessContext::current();
        $current = $trip->fresh();
        abort_unless($current, 404);
        $context->authorizeTrip($current, 'update');
        if (($stage['staged_by'] ?? null) !== $context->actorId()) {
            throw new AuthorizationException('El archivo preparado pertenece a otro actor.');
        }
        if ($parent) {
            $relation = $parent instanceof Expense ? $current->expenses() : $current->products();
            abort_unless($relation->whereKey($parent->id)->exists(), 404);
        }
        unset($stage['staged_by']);

        return Document::create([...$stage, 'trip_id' => $trip->id, 'kind' => $kind, 'uploaded_by' => $context->actorId(),
            'expense_id' => $parent instanceof Expense ? $parent->id : null, 'product_id' => $parent instanceof Product ? $parent->id : null]);
    }

    public function discard(?array $stage, ?AccessContext $context = null): void
    {
        if ($stage) {
            $context ??= AccessContext::current();
            if (($stage['staged_by'] ?? null) !== $context->actorId()) {
                throw new AuthorizationException('El archivo preparado pertenece a otro actor.');
            }
            Storage::disk('local')->delete($stage['storage_path']);
        }
    }
}
