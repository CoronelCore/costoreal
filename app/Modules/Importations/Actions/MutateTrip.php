<?php

namespace App\Modules\Importations\Actions;

use App\Models\AuditEvent;
use App\Models\Trip;
use App\Modules\Costing\Actions\RecordCostRun;
use App\Services\ProrrateoService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class MutateTrip
{
    public function __construct(private ProrrateoService $costing, private RecordCostRun $recorder) {}

    public function execute(Trip $trip, callable $mutation, string $action = 'update', ?int $expectedRevision = null, string $ability = 'update', ?callable $verify = null): mixed
    {
        return DB::transaction(function () use ($trip, $mutation, $action, $expectedRevision, $ability, $verify) {
            $current = Trip::whereKey($trip->id)->lockForUpdate()->firstOrFail();
            Gate::authorize($ability, $current);
            if (request()->has('expected_revision')) {
                request()->validate(['expected_revision' => 'required|integer|min:1']);
            }
            $expectedRevision ??= request()->has('expected_revision') ? (int) request('expected_revision') : null;
            if ($expectedRevision !== null && $expectedRevision !== $current->revision) {
                throw ValidationException::withMessages(['conflict' => 'Esta importación cambió en otra pestaña. Recarga para revisar la nueva versión; tus datos no se han guardado.']);
            }
            $before = $current->load(['products', 'expenses', 'documents'])->toArray();
            $result = $mutation($current);
            $cost = $this->costing->calcularProrrateo($current);
            if ($verify) {
                $verify($current, $cost);
            }
            $current->increment('revision');
            $this->recorder->execute($current, $cost, $action);
            AuditEvent::create(['trip_id' => $current->id, 'actor_id' => auth()->id(), 'action' => $action, 'revision' => $current->revision,
                'before' => $before, 'after' => $current->toArray(), 'created_at' => now()]);

            return $result;
        });
    }
}
