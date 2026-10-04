<?php

namespace App\Modules\Importations\Actions;

use App\Models\Trip;
use Illuminate\Validation\ValidationException;

final class CloseImportation
{
    public function __construct(private MutateTrip $mutation) {}

    public function execute(Trip $trip, int $revision): void
    {
        $this->mutation->execute($trip, fn ($current) => $current->update(['estado' => 'finalizado']), 'close', expectedRevision: $revision, ability: 'close', verify: function ($current, $result) {
            if (! $result->lines || $result->unassigned !== '0') {
                throw ValidationException::withMessages(['cierre' => 'Agrega productos y distribuye todos los gastos antes de cerrar.']);
            }
        });
    }
}
