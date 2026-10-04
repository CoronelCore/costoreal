<?php

namespace App\Console\Commands;

use App\Modules\Documents\MigrateLegacyDocument;
use App\Modules\Documents\QuarantineLegacyFile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class MigrateLegacyDocuments extends Command
{
    protected $signature = 'costoreal:legacy-documents {--apply : Copiar a privado y retirar acceso público después de comprobarlo} {--quarantine-orphans : Conservar archivos sin referencia en privado sin asignar propietario} {--after-product=0} {--after-expense=0} {--limit=100 : Límite por tipo y por lote de retiros pendientes}';

    protected $description = 'Inventario por defecto; migración privada recuperable, sin recalcular importes ni revisiones';

    public function handle(MigrateLegacyDocument $migration): int
    {
        if (! $this->option('apply')) {
            return $this->runBatch($migration);
        }
        $file = fopen(Storage::disk('local')->path('.legacy-document-migration.lock'), 'c+');
        if (! $file || ! flock($file, LOCK_EX | LOCK_NB)) {
            if (is_resource($file)) {
                fclose($file);
            }
            $this->error('Otra migración utiliza este almacenamiento. Reintenta cuando termine.');

            return self::FAILURE;
        }
        try {
            return $this->runBatch($migration);
        } finally {
            flock($file, LOCK_UN);
            fclose($file);
        }
    }

    private function runBatch(MigrateLegacyDocument $migration): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT);
        $checkpoints = ['products' => filter_var($this->option('after-product'), FILTER_VALIDATE_INT), 'expenses' => filter_var($this->option('after-expense'), FILTER_VALIDATE_INT)];
        if ($limit === false || $limit < 1 || $limit > 1000 || in_array(false, $checkpoints, true) || min($checkpoints) < 0) {
            $this->error('Límite de 1–1.000 y checkpoints enteros no negativos.');

            return self::FAILURE;
        }
        $errors = 0;
        $copied = 0;
        $moves = [];
        foreach ($checkpoints as $table => $after) {
            foreach ($migration->inventory($table, $after, $limit) as $row) {
                $checkpoints[$table] = $row->id;
                try {
                    $meta = $migration->inspect($table, $row);
                    $this->line("$table #{$row->id} · {$meta['size']} bytes · sha256 {$meta['sha256']}");
                    if ($this->option('apply')) {
                        $moves[] = $migration->migrate($table, $row);
                        $copied++;
                    }
                } catch (\Throwable $e) {
                    $errors++;
                    $this->error("$table #{$row->id}: {$e->getMessage()}");
                }
            }
        }
        if ($this->option('apply')) {
            $pending = DB::table('legacy_document_moves')->where('status', '<>', 'retired')->orderBy('id')->limit($limit)->pluck('id')->all();
            foreach (array_unique([...$moves, ...$pending]) as $id) {
                try {
                    $status = $migration->retire($id);
                    $this->line("Copia #$id: $status");
                } catch (\Throwable $e) {
                    $errors++;
                    $this->error("Copia #$id: {$e->getMessage()}");
                }
            }
        }
        $remaining = array_sum(array_map(fn ($table) => $migration->inventory($table, 0, 1000)->count(), array_keys($checkpoints)));
        $pending = DB::table('legacy_document_moves')->where('status', '<>', 'retired')->count();
        $orphans = 0;
        $quarantine = app(QuarantineLegacyFile::class);
        $orphanMoves = [];
        $orphanLimit = 0;
        foreach (['productos', 'comprobantes'] as $directory) {
            try {
                $paths = Storage::disk('public')->allFiles($directory);
            } catch (\Throwable $e) {
                $errors++;
                $this->error("No se pudo inventariar $directory; revisa enlaces y permisos.");

                continue;
            }
            foreach ($paths as $path) {
                if (! DB::table('products')->where('imagen', $path)->exists() && ! DB::table('expenses')->where('comprobante', $path)->exists() && ! DB::table('legacy_document_moves')->where('source_path', $path)->where('status', '<>', 'retired')->exists()) {
                    if ($this->option('apply') && $this->option('quarantine-orphans') && $orphanLimit++ < $limit) {
                        try {
                            $orphanMoves[] = $quarantine->copy($path);
                        } catch (\Throwable $e) {
                            $errors++;
                            $this->error('Archivo sin referencia: '.$e->getMessage());
                            $orphans++;
                        }
                    } else {
                        $orphans++;
                    }
                }
            }
        }
        if ($this->option('apply') && $this->option('quarantine-orphans')) {
            $orphanPending = DB::table('legacy_document_quarantine')->where('status', '<>', 'retired')->orderBy('id')->limit($limit)->pluck('id')->all();
            foreach (array_unique([...$orphanMoves, ...$orphanPending]) as $id) {
                try {
                    $quarantine->retire($id);
                    $this->line("Archivo privado sin asociación #$id: retired");
                } catch (\Throwable $e) {
                    $errors++;
                    $this->error("Archivo privado #$id: {$e->getMessage()}");
                }
            }
        }
        $pending += DB::table('legacy_document_quarantine')->where('status', '<>', 'retired')->count();
        $this->info(($this->option('apply') ? 'Aplicación' : 'Solo inventario')." · copias procesadas: $copied · pendientes (hasta 2.000): $remaining · retiros pendientes: $pending · archivos sin referencia: $orphans · errores: $errors");
        $this->line("Checkpoint producto: {$checkpoints['products']}; gasto: {$checkpoints['expenses']}. Los errores requieren revisión o reintento desde un checkpoint anterior. Para conservar archivos sin referencia fuera del acceso público usa --apply --quarantine-orphans; no se asigna un propietario ficticio.");

        return $errors || ($this->option('apply') && ($remaining || $pending || $orphans)) ? self::FAILURE : self::SUCCESS;
    }
}
