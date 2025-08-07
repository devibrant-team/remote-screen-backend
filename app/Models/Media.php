<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Media extends Model
{
    protected $table = 'media';

    protected $fillable = [
        'type',
        'widget_id',
        'storage',
        'user_id',
        'media',
    ];
}
