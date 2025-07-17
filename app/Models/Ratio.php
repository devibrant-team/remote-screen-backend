<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ratio extends Model
{
    //
    protected $table = 'ratio';

    protected $fillable = [
        'width',
        'height',
        'ratio',
        'user_id',
    ];
}
