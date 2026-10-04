<?php

namespace App\Modules\Documents;

use App\Models\Document;
use App\Models\Trip;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** Verified private copy -> atomic metadata -> resumable retirement of the public copy. */
final class MigrateLegacyDocument
{
    public const SOURCES = ['products' => ['imagen', 'productos/', 'product_image'], 'expenses' => ['comprobante', 'comprobantes/', 'receipt']];

    public function inventory(string $table, int $after, int $limit)
    {
        [$column] = self::SOURCES[$table];

        return DB::table($table)->where('id', '>', $after)->whereNotNull($column)->where($column, '<>', '')
            ->where($column, 'not like', 'documents/%')->orderBy('id')->limit($limit)->get();
    }

    public function inspect(string $table, object $row): array
    {
        [$column, $prefix] = self::SOURCES[$table];
        $source = $row->{$column};

        return $this->inspectFile($source, $prefix);
    }

    public function inspectFile(string $source, string $prefix): array
    {
        $path = $this->publicFile($source, $prefix);

        return ['path' => $source, 'sha256' => hash_file('sha256', $path), 'size' => filesize($path),
            'mime' => (new \finfo(FILEINFO_MIME_TYPE))->file($path)];
    }

    private function publicFile(string $source, string $prefix): string
    {
        if (! str_starts_with($source, $prefix) || ! preg_match('/^[A-Za-z0-9_.\/-]+$/D', $source) || array_intersect(explode('/', $source), ['', '.', '..'])) {
            throw new \RuntimeException('Ruta heredada no reconocida; no se lee ni modifica.');
        }
        $public = Storage::disk('public');
        $private = Storage::disk('local');
        if (config('filesystems.disks.public.driver') !== 'local' || config('filesystems.disks.local.driver') !== 'local') {
            throw new \RuntimeException('La migración está certificada para discos locales.');
        }
        $root = realpath($public->path(''));
        $privateRoot = realpath($private->path(''));
        if (! $root || ! $privateRoot || $privateRoot === $root || $privateRoot === realpath(public_path()) || str_starts_with($privateRoot, $root.DIRECTORY_SEPARATOR) || str_starts_with($privateRoot, public_path().DIRECTORY_SEPARATOR)) {
            throw new \RuntimeException('El destino privado debe existir fuera del directorio público.');
        }
        $walk = $root;
        foreach (explode('/', $source) as $part) {
            $walk .= DIRECTORY_SEPARATOR.$part;
            if (is_link($walk)) {
                throw new \RuntimeException('No se migran enlaces simbólicos.');
            }
        }
        $resolved = realpath($public->path($source));
        if (! $resolved || ! str_starts_with($resolved, $root.DIRECTORY_SEPARATOR) || ! is_file($resolved) || ! is_readable($resolved)) {
            throw new \RuntimeException('El archivo heredado no está disponible.');
        }

        return $resolved;
    }

    public function migrate(string $table, object $row): int
    {
        [$column, , $kind] = self::SOURCES[$table];
        $meta = $this->inspect($table, $row);
        $target = 'documents/legacy/'.$table.'-'.$row->id.'-'.$meta['sha256'];
        $local = Storage::disk('local');
        $created = ! $local->exists($target);
        $committed = false;
        try {
            if ($created) {
                $stream = Storage::disk('public')->readStream($meta['path']);
                if (! is_resource($stream)) {
                    throw new \RuntimeException('No se pudo leer el archivo heredado.');
                }
                try {
                    if (! $local->put($target, $stream)) {
                        throw new \RuntimeException('No se pudo guardar la copia privada.');
                    }
                } finally {
                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                }
            }
            if (hash_file('sha256', $local->path($target)) !== $meta['sha256']) {
                throw new \RuntimeException('La copia privada no coincide con el original.');
            }
            $id = DB::transaction(function () use ($table, $column, $kind, $row, $meta, $target) {
                Trip::withTrashed()->whereKey($row->trip_id)->lockForUpdate()->firstOrFail();
                $current = DB::table($table)->where('id', $row->id)->where('trip_id', $row->trip_id)->lockForUpdate()->first();
                $existing = DB::table('legacy_document_moves')->where('source_table', $table)->where('source_id', $row->id)->first();
                if ($existing) {
                    if ($existing->source_path !== $meta['path'] || $existing->sha256 !== $meta['sha256']) {
                        throw new \RuntimeException('El origen cambió desde la copia anterior; requiere revisión.');
                    }

                    return $existing->id;
                }
                if (! $current || $current->{$column} !== $meta['path']) {
                    throw new \RuntimeException('El registro cambió o fue retirado durante la migración.');
                }
                $mime = in_array($meta['mime'], ['application/pdf', 'image/jpeg', 'image/png', 'image/webp', 'image/gif'], true) ? $meta['mime'] : 'application/octet-stream';
                $document = Document::create(['trip_id' => $row->trip_id, 'product_id' => $table === 'products' ? $row->id : null, 'expense_id' => $table === 'expenses' ? $row->id : null,
                    'uploaded_by' => null, 'storage_path' => $target, 'filename' => basename($meta['path']), 'mime' => $mime, 'size' => $meta['size'], 'sha256' => $meta['sha256'], 'kind' => $kind]);
                DB::table($table)->where('id', $row->id)->update([$column => $target, 'document_id' => $current->document_id ?? $document->id]);

                return DB::table('legacy_document_moves')->insertGetId(['source_table' => $table, 'source_id' => $row->id, 'trip_id' => $row->trip_id, 'document_id' => $document->id,
                    'source_path' => $meta['path'], 'storage_path' => $target, 'sha256' => $meta['sha256'], 'size' => $meta['size'], 'status' => 'copied', 'created_at' => now(), 'updated_at' => now()]);
            });
            $committed = true;

            return $id;
        } finally {
            if ($created && ! $committed && ! Document::withTrashed()->where('storage_path', $target)->exists()) {
                $local->delete($target);
            }
        }
    }

    public function retire(int $moveId): string
    {
        $move = DB::table('legacy_document_moves')->find($moveId);
        if (! $move) {
            throw new \RuntimeException('La copia no está registrada.');
        }
        try {
            $document = Document::withTrashed()->whereKey($move->document_id)->where('trip_id', $move->trip_id)->where('storage_path', $move->storage_path)->first();
            if (! $document || ! Storage::disk('local')->exists($move->storage_path) || hash_file('sha256', Storage::disk('local')->path($move->storage_path)) !== $move->sha256) {
                throw new \RuntimeException('La copia privada conservada no está verificada.');
            }
            foreach (self::SOURCES as $table => [$column]) {
                if (DB::table($table)->where($column, $move->source_path)->exists()) {
                    DB::table('legacy_document_moves')->where('id', $moveId)->update(['status' => 'awaiting_references', 'last_error' => null, 'updated_at' => now()]);

                    return 'awaiting_references';
                }
            }
            $public = Storage::disk('public');
            if ($public->exists($move->source_path)) {
                [, $prefix] = self::SOURCES[$move->source_table];
                $path = $this->publicFile($move->source_path, $prefix);
                if (hash_file('sha256', $path) !== $move->sha256) {
                    throw new \RuntimeException('El archivo público cambió; se conserva para revisión.');
                }
                if (! $public->delete($move->source_path)) {
                    throw new \RuntimeException('No se pudo retirar el acceso público.');
                }
            }
            DB::table('legacy_document_moves')->where('id', $moveId)->update(['status' => 'retired', 'last_error' => null, 'updated_at' => now()]);

            return 'retired';
        } catch (\Throwable $e) {
            DB::table('legacy_document_moves')->where('id', $moveId)->update(['status' => 'cleanup_failed', 'last_error' => $e->getMessage(), 'updated_at' => now()]);
            throw $e;
        }
    }
}
