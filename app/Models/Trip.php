<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Trip extends Model
{
    protected $fillable = [
        'user_id',
        'company_id',
        'nombre',
        'descripcion',
        'fecha_inicio',
        'fecha_fin',
        'moneda_base',
        'moneda_trabajo',
        'tipo_cambio',
        'estado',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
        'tipo_cambio' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function getTotalGastosAttribute()
    {
        return $this->expenses()->sum('monto_clp');
    }

    public function getTotalProductosAttribute()
    {
        return $this->products()->sum('subtotal_clp');
    }

    public function getCostoTotalAttribute()
    {
        return $this->total_gastos + $this->total_productos;
    }
}
