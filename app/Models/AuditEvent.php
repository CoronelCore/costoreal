<?php

namespace App\Models;

use App\Modules\Audit\AuditSummary;
use Illuminate\Database\Eloquent\Model;

class AuditEvent extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = ['before' => 'array', 'after' => 'array', 'summary' => 'array', 'created_at' => 'datetime'];

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    protected static function booted(): void
    {
        static::creating(function ($event) {
            $event->summary = app(AuditSummary::class)->build($event->before ?? [], $event->after ?? []);
        });
        static::updating(fn () => throw new \LogicException('El historial es inmutable.'));
        static::deleting(fn () => throw new \LogicException('El historial es inmutable.'));
    }
}
