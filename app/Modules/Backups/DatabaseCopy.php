<?php

namespace App\Modules\Backups;

use Illuminate\Support\Facades\DB;

/** Consistent copies of the SQLite database, taken through SQLite itself instead of copying the open file. */
final class DatabaseCopy
{
    public function isSqlite(): bool
    {
        return DB::connection()->getDriverName() === 'sqlite';
    }

    /** Copy taken before applying an update; returns where it was saved, relative to the costoreal folder. */
    public function beforeUpdate(): ?string
    {
        if (! $this->isSqlite() || DB::connection()->getDatabaseName() === ':memory:') {
            return null;
        }
        $relative = 'storage/app/private/respaldos/antes-de-actualizar-'.now()->format('Y-m-d-His').'.sqlite';
        $this->snapshot(base_path($relative));

        return 'costoreal/'.$relative;
    }

    public function snapshot(string $target): void
    {
        if (! is_dir(dirname($target))) {
            mkdir(dirname($target), 0750, true);
        }
        DB::statement('VACUUM INTO '.DB::connection()->getPdo()->quote(str_replace('\\', '/', $target)));
    }
}
