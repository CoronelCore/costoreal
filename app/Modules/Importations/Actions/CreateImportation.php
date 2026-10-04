<?php

namespace App\Modules\Importations\Actions;

use App\Models\Trip;
use App\Models\User;

final class CreateImportation
{
    /** Context validated by TripRequest, used by quick creation and the guide. */
    public function execute(User $user, array $context): Trip
    {
        $fields = array_intersect_key($context, array_flip(['nombre', 'descripcion', 'company_id', 'fecha_inicio', 'fecha_fin', 'moneda_trabajo', 'tipo_cambio']));

        return Trip::create([...$fields, 'user_id' => $user->id, 'moneda_base' => 'CLP', 'estado' => 'activo'])->fresh();
    }
}
