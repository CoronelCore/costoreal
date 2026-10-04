<?php

namespace App\Models;

use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Trip extends Model
{
    use SoftDeletes;

    protected $appends = ['costo_total'];

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
        'fecha_inicio' => 'date:Y-m-d',
        'fecha_fin' => 'date:Y-m-d',
        'tipo_cambio' => 'decimal:8',
        'revision' => 'integer',
    ];

    public function scenarios(): HasMany
    {
        return $this->hasMany(CostScenario::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function costRuns(): HasMany
    {
        return $this->hasMany(CostRun::class);
    }

    public function auditEvents(): HasMany
    {
        return $this->hasMany(AuditEvent::class);
    }

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
        if (array_key_exists('total_gastos_clp', $this->attributes)) {
            return (string) ($this->attributes['total_gastos_clp'] ?? '0');
        }
        if ($this->relationLoaded('expenses')) {
            return (string) $this->expenses->reduce(fn ($s, $e) => $s->plus($e->monto_clp), BigDecimal::zero());
        }

        return $this->expenses()->sum('monto_clp');
    }

    public function getTotalProductosAttribute()
    {
        if (array_key_exists('total_productos_clp', $this->attributes)) {
            return (string) ($this->attributes['total_productos_clp'] ?? '0');
        }
        if ($this->relationLoaded('products')) {
            return (string) $this->products->reduce(fn ($s, $p) => $s->plus($p->subtotal_clp), BigDecimal::zero());
        }

        return $this->products()->sum('subtotal_clp');
    }

    public function getCostoTotalAttribute()
    {
        return (string) BigDecimal::of((string) $this->total_gastos)->plus((string) $this->total_productos);
    }

    public function scopeWithFinancialTotals(Builder $query): Builder
    {
        return $query->withSum('expenses as total_gastos_clp', 'monto_clp')
            ->withSum('products as total_productos_clp', 'subtotal_clp')
            ->selectRaw('(SELECT COALESCE(SUM(monto_clp),0) FROM expenses WHERE expenses.trip_id=trips.id) + (SELECT COALESCE(SUM(subtotal_clp),0) FROM products WHERE products.trip_id=trips.id) as costo_calculado');
    }
}
