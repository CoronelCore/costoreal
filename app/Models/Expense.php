<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    protected $hidden = ['comprobante'];

    protected $fillable = [
        'document_id',
        'rate_mode', 'applied_rate', 'rate_source', 'rate_date', 'allocation_method', 'target_product_id',
        'trip_id',
        'expense_category_id',
        'concepto',
        'moneda',
        'monto',
        'monto_clp',
        'comprobante',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'monto_clp' => 'decimal:2',
    ];

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }
}
