<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    protected $hidden = ['imagen'];

    protected $fillable = [
        'document_id',
        'rate_mode', 'applied_rate', 'rate_source', 'rate_date', 'weight', 'volume',
        'trip_id',
        'nombre',
        'moneda',
        'cantidad',
        'costo_unitario',
        'subtotal_clp',
        'gastos_prorrateados_clp',
        'costo_final_unitario_clp',
        'costo_final_total_clp',
        'imagen',
        'precio_venta',
    ];

    protected $casts = [
        'cantidad' => 'integer',
        'costo_unitario' => 'decimal:2',
        'subtotal_clp' => 'decimal:2',
        'gastos_prorrateados_clp' => 'decimal:2',
        'costo_final_unitario_clp' => 'decimal:8',
        'costo_final_total_clp' => 'decimal:2',
        'precio_venta' => 'decimal:2',
    ];

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }
}
