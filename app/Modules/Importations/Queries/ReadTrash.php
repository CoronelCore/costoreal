<?php

namespace App\Modules\Importations\Queries;

use App\Modules\Access\AccessContext;
use App\Modules\Importations\Data\ImportationData;

final class ReadTrash
{
    public function read(AccessContext $context, array $filters): array
    {
        $context->actorId();
        $trips = $context->trips()->onlyTrashed()
            ->when($filters['q'] ?? null, fn ($query, $search) => $query->where('nombre', 'like', '%'.$search.'%'))
            ->withFinancialTotals()->withCount(['products', 'expenses', 'documents'])->with($context->companyRelation())
            ->orderByDesc('deleted_at')->orderByDesc('id')->paginate(20)->withQueryString()
            ->through(fn ($trip) => [...ImportationData::listing($trip), 'documents_count' => (int) $trip->documents_count, 'deleted_at' => $trip->deleted_at->toISOString()]);

        return ['trips' => $trips, 'filters' => (object) array_diff_key($filters, ['page' => true])];
    }
}
