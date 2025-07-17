<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlaylistStyle extends Model
{
    protected $tbale = 'playlist_style';

    protected $fillable = [
        'type',
    ];
}
