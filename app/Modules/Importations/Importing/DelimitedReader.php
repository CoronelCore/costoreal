<?php

namespace App\Modules\Importations\Importing;

use DomainException;

final class DelimitedReader
{
    public function read(string $source, string $delimiter): array
    {
        if (strlen($source) > 2 * 1024 * 1024 || ! mb_check_encoding($source, 'UTF-8') || str_contains($source, "\0")) {
            throw new DomainException('Usa texto UTF-8 de hasta 2 MB, sin contenido binario.');
        }
        $delimiter = $delimiter === 'tab' ? "\t" : $delimiter;
        if (! in_array($delimiter, [',', ';', "\t"], true)) {
            throw new DomainException('Selecciona coma, punto y coma o tabulador.');
        }
        $source = preg_replace('/^\xEF\xBB\xBF/', '', $source);
        if (substr_count($source, '"') % 2 !== 0) {
            throw new DomainException('Hay una comilla sin cerrar. Revisa el archivo antes de importarlo.');
        }
        $stream = fopen('php://temp', 'w+');
        try {
            fwrite($stream, $source);
            rewind($stream);
            $rows = [];
            while (($cells = fgetcsv($stream, null, $delimiter, '"', '')) !== false) {
                if (count($cells) > 32) {
                    throw new DomainException('Cada fila puede tener hasta 32 columnas.');
                }
                if (! array_filter($cells, fn ($cell) => trim((string) $cell) !== '')) {
                    continue;
                }
                $rows[] = array_map(fn ($cell) => trim((string) $cell), $cells);
                if (count($rows) > 1001) {
                    throw new DomainException('Importa hasta 1.000 productos por lote.');
                }
            }

            return $rows;
        } finally {
            fclose($stream);
        }
    }
}
