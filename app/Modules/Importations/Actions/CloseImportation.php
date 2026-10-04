<?php

namespace App\Modules\Importations\Actions;

use App\Models\Trip;
use App\Modules\Access\AccessContext;
use App\Modules\Importations\Review\ReviewImportation;
use Illuminate\Validation\ValidationException;

final class CloseImportation
{
    public function __construct(private MutateTrip $mutation, private ReviewImportation $review) {}

    public function execute(Trip $trip, int $revision, ?AccessContext $context = null): void
    {
        $this->mutation->execute($trip, function ($current) {
            $this->review->assertReady($current);
            $current->update(['estado' => 'finalizado']);
        }, 'close', expectedRevision: $revision, ability: 'close', verify: function ($current, $result) {
            if (! $result->lines || $result->unassigned !== '0') {
                throw ValidationException::withMessages(['cierre' => 'Agrega productos y distribuye todos los gastos antes de cerrar.']);
            }
        }, context: $context);
    }
}
