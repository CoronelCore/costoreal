<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Feedback extends Model
{
    protected $table = 'feedback';

    protected $fillable = ['user_id', 'author_name', 'message', 'screen', 'viewport_width'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }
}
