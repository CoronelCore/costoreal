<?php

namespace App\Console\Commands;

use App\Models\CostRun;
use App\Modules\Costing\Actions\ProjectCostRun;
use Illuminate\Console\Command;

class BackfillCostReadModels extends Command
{
    protected $signature = 'costoreal:cost-read-backfill {--limit= : Máximo de ejecuciones; vacío procesa todas}';

    protected $description = 'Prepara lecturas de revisiones antiguas sin recalcular ni reescribir su historia; reentrante';

    public function handle(ProjectCostRun $project): int
    {
        $limit = $this->option('limit');
        if ($limit !== null && (filter_var($limit, FILTER_VALIDATE_INT) === false || (int) $limit < 1)) {
            $this->error('El límite debe ser un entero positivo.');

            return self::FAILURE;
        }
        $count = 0;
        // One full historic result in memory at a time, independent of total history length.
        foreach (CostRun::whereNotIn('id', fn ($query) => $query->select('cost_run_id')->from('cost_run_views'))->lazyById(1) as $run) {
            $count += (int) $project->execute($run);
            if ($limit !== null && $count >= (int) $limit) {
                break;
            }
        }
        $this->info("Lecturas preparadas: {$count}. Las revisiones y sus importes originales se conservaron.");

        return self::SUCCESS;
    }
}
