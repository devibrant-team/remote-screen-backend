<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserScreens extends Model
{
    protected $table = 'user_screens';

    protected $fillable = [
        'user_id',
        'screen_id',
        'group_id',
        'is_extra',
    ];
}
