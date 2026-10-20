<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One value per key for the whole installation, such as whether registration is open. */
class AppSetting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];
}
