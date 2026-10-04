<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CostRun extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = ['input' => 'array', 'result' => 'array', 'snapshot' => 'array', 'created_at' => 'datetime'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Los snapshots son inmutables.'));
        static::deleting(fn () => throw new \LogicException('Los snapshots solo se eliminan con su expediente.'));
    }
}
