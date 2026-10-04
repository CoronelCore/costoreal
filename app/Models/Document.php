<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $hidden = ['storage_path'];

    protected $appends = ['download_url'];

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }

    public function getDownloadUrlAttribute(): string
    {
        return route('trips.documents.show', [$this->trip_id, $this->id]);
    }
}
