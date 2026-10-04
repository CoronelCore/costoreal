<?php

namespace App\Modules\Documents;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** Unreferenced bytes stay private, with a manifest and no invented owner/document association. */
final class QuarantineLegacyFile
{
    public function __construct(private MigrateLegacyDocument $migration) {}

    public function referenced(string $path): bool
    {
        return DB::table('products')->where('imagen', $path)->exists() || DB::table('expenses')->where('comprobante', $path)->exists()
            || DB::table('legacy_document_moves')->where('source_path', $path)->where('status', '<>', 'retired')->exists();
    }

    private function prefix(string $path): string
    {
        foreach (['productos/', 'comprobantes/'] as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return $prefix;
            }
        }
        throw new \RuntimeException('El archivo no pertenece a un directorio heredado reconocido.');
    }

    public function copy(string $path): int
    {
        if ($this->referenced($path)) {
            throw new \RuntimeException('El archivo tiene referencias; debe migrarse con su registro.');
        }
        $meta = $this->migration->inspectFile($path, $this->prefix($path));
        $existing = DB::table('legacy_document_quarantine')->where('source_path', $path)->first();
        if ($existing) {
            if ($existing->sha256 !== $meta['sha256']) {
                throw new \RuntimeException('El archivo público cambió respecto a su respaldo; requiere revisión.');
            }

            return $existing->id;
        }
        $local = Storage::disk('local');
        $target = 'documents/quarantine/'.hash('sha256', $path).'-'.$meta['sha256'];
        $created = ! $local->exists($target);
        $committed = false;
        try {
            if ($created) {
                $stream = Storage::disk('public')->readStream($path);
                if (! is_resource($stream)) {
                    throw new \RuntimeException('No se pudo leer el archivo sin referencia.');
                }
                try {
                    if (! $local->put($target, $stream)) {
                        throw new \RuntimeException('No se pudo guardar el archivo privado.');
                    }
                } finally {
                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                }
            }
            if (hash_file('sha256', $local->path($target)) !== $meta['sha256']) {
                throw new \RuntimeException('El respaldo privado no coincide.');
            }
            $id = DB::table('legacy_document_quarantine')->insertGetId(['source_path' => $path, 'storage_path' => $target, 'sha256' => $meta['sha256'], 'size' => $meta['size'], 'mime' => $meta['mime'], 'status' => 'copied', 'created_at' => now(), 'updated_at' => now()]);
            $committed = true;

            return $id;
        } finally {
            if ($created && ! $committed) {
                $local->delete($target);
            }
        }
    }

    public function retire(int $id): void
    {
        $record = DB::table('legacy_document_quarantine')->find($id);
        if (! $record) {
            throw new \RuntimeException('El archivo privado no tiene manifiesto.');
        }
        try {
            if ($this->referenced($record->source_path)) {
                throw new \RuntimeException('Aparecieron referencias al archivo; no se retira.');
            }
            $local = Storage::disk('local');
            $public = Storage::disk('public');
            if (! $local->exists($record->storage_path) || hash_file('sha256', $local->path($record->storage_path)) !== $record->sha256) {
                throw new \RuntimeException('El respaldo privado no está verificado.');
            }
            if ($public->exists($record->source_path)) {
                $meta = $this->migration->inspectFile($record->source_path, $this->prefix($record->source_path));
                if ($meta['sha256'] !== $record->sha256) {
                    throw new \RuntimeException('El archivo público cambió; no se retira.');
                }
                if (! $public->delete($record->source_path)) {
                    throw new \RuntimeException('No se pudo retirar el acceso público.');
                }
            }
            DB::table('legacy_document_quarantine')->where('id', $id)->update(['status' => 'retired', 'last_error' => null, 'updated_at' => now()]);
        } catch (\Throwable $e) {
            DB::table('legacy_document_quarantine')->where('id', $id)->update(['status' => 'cleanup_failed', 'last_error' => $e->getMessage(), 'updated_at' => now()]);
            throw $e;
        }
    }
}
