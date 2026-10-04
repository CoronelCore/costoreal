<?php

namespace App\Modules\Shared;

use Illuminate\Database\ConcurrencyErrorDetector;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class TransactionRunner
{
    public function execute(callable $operation): mixed
    {
        $outermost = DB::transactionLevel() === 0;
        for ($attempt = 0; ; $attempt++) {
            try {
                return DB::transaction($operation);
            } catch (\Throwable $e) {
                if (! $outermost || ! (new ConcurrencyErrorDetector)->causedByConcurrencyError($e)) {
                    throw $e;
                }
                if ($attempt >= 4) {
                    throw ValidationException::withMessages(['conflict' => 'Otra operación está guardando esta importación. Espera un momento y recarga la revisión; no se guardaron tus cambios.']);
                }
                usleep(25000 * (2 ** $attempt));
            }
        }
    }
}
