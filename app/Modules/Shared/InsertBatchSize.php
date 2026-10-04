<?php

namespace App\Modules\Shared;

use Illuminate\Support\Facades\DB;

/** Respect the active driver's bind limit and cap the transient batch payload. */
final class InsertBatchSize
{
    public function forColumns(int $columns, int $cap = 5000): int
    {
        if ($columns < 1 || $cap < 1) {
            throw new \InvalidArgumentException('Las columnas y el límite del lote deben ser positivos.');
        }
        $limit = match (DB::getDriverName()) {
            'mysql', 'pgsql' => 65535, 'sqlsrv' => 2100, default => 999,
        };
        if (DB::getDriverName() === 'sqlite') {
            foreach (DB::select('PRAGMA compile_options') as $option) {
                if (preg_match('/^MAX_VARIABLE_NUMBER=(\d+)$/D', $option->compile_options, $match)) {
                    $limit = (int) $match[1];
                    break;
                }
            }
        }

        return max(1, min($cap, intdiv($limit - 64, $columns)));
    }
}
