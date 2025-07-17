<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Branches extends Model
{
    //
    protected $table  = 'branches';

    protected $fillable = [
        'name',
        'user_id',
    ];
 }
