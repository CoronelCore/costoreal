<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CostScenario extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = ['input' => 'array', 'result' => 'array', 'base_revision' => 'integer', 'applied_revision' => 'integer', 'created_at' => 'datetime', 'applied_at' => 'datetime'];

    protected static function booted(): void
    {
        static::updating(function (self $scenario) {
            if ($scenario->isDirty(['trip_id', 'actor_id', 'name', 'idempotency_key', 'base_revision', 'baseline_hash', 'input_hash', 'input', 'result', 'created_at'])) {
                throw new \LogicException('La simulación conservada es inmutable. Guarda un nuevo escenario.');
            }
        });
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
