<?php

namespace App\Modules\Importations\Actions;

use App\Models\Trip;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final class CreateImportation
{
    /** Context validated by TripRequest, used by quick creation and the guide. */
    public function execute(User $user, array $context): Trip
    {
        $fields = array_intersect_key($context, array_flip(['nombre', 'descripcion', 'company_id', 'fecha_inicio', 'fecha_fin', 'moneda_trabajo', 'tipo_cambio']));

        if (! empty($fields['company_id']) && ! $user->companies()->whereKey($fields['company_id'])->exists()) {
            throw ValidationException::withMessages(['company_id' => 'La empresa no pertenece a tu espacio de trabajo.']);
        }

        return Trip::create([...$fields, 'user_id' => $user->id, 'moneda_base' => 'CLP', 'estado' => 'activo'])->fresh();
    }
}
