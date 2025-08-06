<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlaylistStyle extends Model
{
    protected $table  = 'playlist_style';

    protected $fillable = [
        'type',
        'description'
    ];
}
