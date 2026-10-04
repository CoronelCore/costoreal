<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LegacyCostBaseline extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = ['snapshot' => 'array', 'revision' => 'integer', 'created_at' => 'datetime'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('La copia heredada es inmutable.'));
        static::deleting(fn () => throw new \LogicException('La copia heredada se conserva con su expediente.'));
    }
}
