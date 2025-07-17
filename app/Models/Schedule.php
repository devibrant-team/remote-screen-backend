<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    //
    protected $table = 'schedule';

    protected $fillable = [
        'user_id',
        'playlist_id',
        'screen_id',
        'group_id',
        'start_time',
        'end_time',
    ];
}
