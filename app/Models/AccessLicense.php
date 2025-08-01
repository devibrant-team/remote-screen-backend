<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccessLicense extends Model
{
     protected $table  = 'access_licence';

    protected $fillable = [
        'machine',
        'user_id',
    ];
}
