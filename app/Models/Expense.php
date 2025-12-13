<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    protected $fillable = [
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
