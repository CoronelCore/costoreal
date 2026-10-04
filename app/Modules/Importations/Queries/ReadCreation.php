<?php

namespace App\Modules\Importations\Queries;

use App\Models\ExpenseCategory;
use App\Models\ImportDraft;
use App\Modules\Access\AccessContext;
use App\Modules\Importations\Creation\DraftPayload;
use App\Modules\Importations\Data\ImportationData;
use App\Modules\Reporting\FinancialSummaryData;

final class ReadCreation
{
    public function __construct(private DraftPayload $payload) {}

    public function read(AccessContext $context, ?string $draftId): array
    {
        $actor = $context->actorId();
        $draft = $draftId ? ImportDraft::where('user_id', $actor)->findOrFail($draftId) : null;
        $trip = $draft?->trip;
        if ($draft?->trip_id) {
            abort_unless($trip, 404);
            $context->authorizeTrip($trip);
        }
        if ($draft?->completed_at) {
            abort_unless($trip, 404);

            return ['redirectTripId' => $trip?->id];
        }
        if ($trip) {
            $trip->load([...$context->financialRelations(), 'documents']);
        }
        $summary = $trip ? FinancialSummaryData::fromTrip($trip) : null;
        $drafts = ImportDraft::where('user_id', $actor)->whereNull('completed_at')->with(['trip' => fn ($query) => $query->where('user_id', $actor)->select('id')])->latest('updated_at')->paginate(10, ['id', 'trip_id', 'step', 'payload', 'updated_at'], 'draft_page')
            ->through(fn ($item) => ['id' => $item->id, 'trip_id' => $item->trip?->id, 'step' => $item->step, 'name' => $item->payload['context']['nombre'] ?? 'Sin nombre todavía', 'updated_at' => $item->updated_at->toISOString()]);

        return ['draft' => $draft ? $this->payload->data($draft, $context) : null, 'drafts' => $drafts,
            'companies' => $context->companies()->map->only(['id', 'nombre']), 'selectedCompanyId' => $context->activeCompany()?->id,
            'trip' => $trip ? ImportationData::detail($trip, $summary) : null, 'estadisticas' => $summary?->statistics, 'projection' => $summary?->projection,
            'latestCostRun' => $trip?->costRuns()->select('id', 'revision', 'reason', 'algorithm', 'created_at')->latest('id')->first(),
            'categories' => ExpenseCategory::visibleTo($actor)->orderBy('nombre')->get(['id', 'nombre'])];
    }
}
