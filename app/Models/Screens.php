<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Screens extends Model
{
protected $table = 'screens';

    protected $fillable = [
        'name',
        'code',
        'platform',
        'is_active',
    ];

     
}
