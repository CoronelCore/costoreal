<?php

namespace App\Http\Controllers;

use App\Models\Trip;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CostRunHistoryController extends Controller
{
    public function index(Request $request, Trip $trip)
    {
        $this->authorize('view', $trip);
        $filters = $request->validate(['page' => 'nullable|integer|min:1|max:100000', 'anchor' => 'nullable|integer|min:1',
            'reason' => 'nullable|in:close', 'revision' => 'nullable|integer|min:1|max:2147483647'], [
                'revision.integer' => 'Ingresa un número entero de revisión.', 'revision.min' => 'La revisión debe ser mayor que cero.',
                'reason.in' => 'Selecciona Todos los cálculos o Solo cierres.',
            ]);

        return response()->json($this->listing($trip, $filters));
    }

    public function listing(Trip $trip, array $filters = []): array
    {
        return DB::transaction(function () use ($trip, $filters) {
            $anchor = $filters['anchor'] ?? $trip->costRuns()->max('id');
            if (! empty($filters['anchor'])) {
                abort_unless($trip->costRuns()->whereKey($anchor)->exists(), 404);
            }
            $query = $trip->costRuns()->where('id', '<=', $anchor ?? 0)->select('id', 'revision', 'reason', 'algorithm', 'created_at');
            if (! empty($filters['reason'])) {
                $query->where('reason', $filters['reason']);
            }
            if (! empty($filters['revision'])) {
                $query->where('revision', $filters['revision']);
            }
            $page = $query->latest('id')->paginate(20, page: (int) ($filters['page'] ?? 1))->through(fn ($run) => [
                'id' => (int) $run->id, 'revision' => (int) $run->revision, 'reason' => $run->reason, 'algorithm' => $run->algorithm, 'created_at' => $run->created_at->toISOString(),
            ])->withPath(route('trips.cost-runs.index', $trip))->appends(array_filter([...$filters, 'anchor' => $anchor], fn ($value) => $value !== null && $value !== ''))->toArray();

            return [...$page, 'anchor' => $anchor ? (int) $anchor : null, 'reason' => $filters['reason'] ?? null,
                'revision' => isset($filters['revision']) ? (int) $filters['revision'] : null];
        });
    }
}
