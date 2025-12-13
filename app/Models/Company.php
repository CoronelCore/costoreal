<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    protected $fillable = [
        'user_id',
        'nombre',
        'rut',
        'color',
        'icono',
        'descripcion',
        'activa',
    ];

    protected $casts = [
        'activa' => 'boolean',
    ];

    /**
     * Relación con el usuario propietario
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relación con las importaciones (trips)
     */
    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class);
    }

    /**
     * Obtener total gastado en todas las importaciones de esta empresa
     */
    public function getTotalGastadoAttribute()
    {
        return $this->trips->sum('costo_total');
    }

    /**
     * Obtener cantidad de importaciones
     */
    public function getCantidadImportacionesAttribute()
    {
        return $this->trips()->count();
    }

    /**
     * Obtener importaciones sin asignar de un usuario
     */
    public static function getTripsUnassigned($userId)
    {
        return Trip::where('user_id', $userId)
            ->whereNull('company_id')
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
