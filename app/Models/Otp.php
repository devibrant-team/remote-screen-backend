<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Otp extends Model
{
    //
    protected $table = 'otp' ;

    protected $fillable = [
        'user_id',
        'code',
    ];
}
