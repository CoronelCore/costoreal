<?php

namespace App\Console\Commands;

use App\Models\CostReconciliation;
use App\Models\Trip;
use App\Modules\Costing\Reconciliation\ReviewStoredCosts;
use Illuminate\Console\Command;

class ReviewLegacyCosts extends Command
{
    protected $signature = 'costoreal:legacy-cost-review {--after-id=0 : Checkpoint de importación} {--limit=100 : Tamaño del lote}';

    protected $description = 'Conserva costos heredados y comparaciones; reentrante, nunca aplica correcciones financieras';

    public function handle(ReviewStoredCosts $review): int
    {
        $after = filter_var($this->option('after-id'), FILTER_VALIDATE_INT);
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT);
        if ($after === false || $after < 0 || $limit === false || $limit < 1) {
            $this->error('Checkpoint no negativo y límite entero positivo son obligatorios.');

            return self::FAILURE;
        }
        $processed = 0;
        $created = 0;
        $checkpoint = $after;
        foreach (Trip::where('id', '>', $after)->whereDoesntHave('costRuns')->orderBy('id')->lazyById(1) as $trip) {
            $prior = CostReconciliation::where('trip_id', $trip->id)->count();
            $record = $review->executeForMaintenance($trip, $trip->revision);
            $created += (int) (CostReconciliation::where('trip_id', $trip->id)->count() > $prior);
            $data = $record->comparison;
            $this->line("Importación {$trip->id} · revisión {$trip->revision} · diferencias ".count($data['differences']).' · errores '.count($data['errors']));
            $checkpoint = $trip->id;
            if (++$processed >= $limit) {
                break;
            }
        }
        $this->info("Procesadas: {$processed}; comparaciones nuevas: {$created}; checkpoint: {$checkpoint}. Importes y revisiones sin cambios.");

        return self::SUCCESS;
    }
}
