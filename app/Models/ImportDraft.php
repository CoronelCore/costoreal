<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportDraft extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = ['payload' => 'array', 'revision' => 'integer', 'completed_at' => 'datetime'];

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }
}
