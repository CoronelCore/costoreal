<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CostReconciliation extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = ['comparison' => 'array', 'snapshot' => 'array', 'base_revision' => 'integer', 'applied_revision' => 'integer', 'created_at' => 'datetime', 'applied_at' => 'datetime'];

    protected static function booted(): void
    {
        static::updating(function (self $record) {
            if ($record->isDirty(['trip_id', 'actor_id', 'base_revision', 'fingerprint', 'comparison', 'snapshot', 'created_at']) || $record->getOriginal('applied_at')) {
                throw new \LogicException('La comparación conservada es inmutable.');
            }
        });
        static::deleting(fn () => throw new \LogicException('La comparación se conserva con su expediente.'));
    }
}
