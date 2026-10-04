<?php

namespace App\Console\Commands;

use App\Modules\Audit\AuditSummary;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillAuditSummaries extends Command
{
    protected $signature = 'costoreal:audit-backfill {--chunk=100 : Eventos por lote, entre 1 y 500}';

    protected $description = 'Completa resúmenes de auditoría sin alterar snapshots ni costos; se puede reanudar';

    public function handle(AuditSummary $summary): int
    {
        $chunk = filter_var($this->option('chunk'), FILTER_VALIDATE_INT);
        if ($chunk === false || $chunk < 1 || $chunk > 500) {
            $this->error('El tamaño de lote debe ser un entero entre 1 y 500.');

            return self::FAILURE;
        }
        $count = 0;
        DB::table('audit_events')->whereNull('summary')->select('id', 'before', 'after')->chunkById($chunk, function ($events) use ($summary, &$count) {
            foreach ($events as $event) {
                $value = $summary->build(json_decode($event->before, true, flags: JSON_THROW_ON_ERROR), json_decode($event->after, true, flags: JSON_THROW_ON_ERROR));
                $count += DB::table('audit_events')->where('id', $event->id)->whereNull('summary')->update(['summary' => json_encode($value, JSON_THROW_ON_ERROR)]);
            }
        });
        $this->info("Resúmenes completados: {$count}. Los snapshots y costos originales se conservaron.");

        return self::SUCCESS;
    }
}
