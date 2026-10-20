<?php

namespace App\Modules\Backups;

use App\Support\Installation;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/**
 * One ZIP the owner keeps outside the hosting: the SQLite database copied consistently, every private
 * document and plain instructions to restore it with the hosting's file manager. With MySQL the data is
 * backed up from the hosting panel, so the ZIP carries the documents and says so.
 */
final class CreateBackup
{
    public function __construct(private DatabaseCopy $copy) {}

    /** @return array{path: string, name: string, database: bool} */
    public function execute(): array
    {
        $name = 'costoreal-respaldo-'.now()->format('Y-m-d-His').'.zip';
        $folder = storage_path('app/private/respaldos');
        if (! is_dir($folder) && ! mkdir($folder, 0750, true)) {
            throw new RuntimeException('No se pudo preparar la carpeta de respaldos.');
        }
        $path = $folder.'/'.bin2hex(random_bytes(6)).'-'.$name;
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se pudo crear el archivo de respaldo.');
        }
        $database = null;
        if ($this->copy->isSqlite()) {
            $database = $folder.'/'.bin2hex(random_bytes(6)).'.sqlite';
            $this->copy->snapshot($database);
            $zip->addFile($database, 'base-de-datos.sqlite');
        }
        $disk = Storage::disk('local');
        foreach ($disk->allFiles('documents') as $file) {
            $zip->addFile($disk->path($file), $file);
        }
        $zip->addFromString('LEEME-RESPALDO.txt', $this->instructions($database !== null));
        $zip->close();
        if ($database !== null) {
            @unlink($database);
        }
        Installation::put('last_backup_at', now()->toIso8601String());

        return ['path' => $path, 'name' => $name, 'database' => $database !== null];
    }

    private function instructions(bool $database): string
    {
        $lines = ['Respaldo de CostoReal · '.now()->format('d/m/Y H:i'), '', 'Contiene:'];
        $lines[] = $database
            ? '- base-de-datos.sqlite: cuentas, empresas, importaciones, productos, gastos, precios e historial.'
            : '- Los datos están en MySQL: respáldalos desde hPanel (Archivos → Respaldos → Bases de datos).';
        array_push($lines, '- documents/: comprobantes y documentos adjuntos.', '', 'Para volver a este respaldo en tu hosting:',
            '1. En hPanel, abre el Administrador de archivos.');
        if ($database) {
            array_push($lines, '2. Cambia el nombre de costoreal/database/database.sqlite, por ejemplo a database-anterior.sqlite.',
                '3. Sube base-de-datos.sqlite a costoreal/database y cámbiale el nombre a database.sqlite.');
        }
        array_push($lines, ($database ? '4' : '2').'. Sube la carpeta documents a costoreal/storage/app/private y acepta reemplazar.',
            ($database ? '5' : '3').'. Abre CostoReal y entra con tu cuenta.', '');

        return implode("\r\n", $lines);
    }
}
