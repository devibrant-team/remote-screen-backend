<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlaylistItem extends Model
{
    protected $table ='playlist_item';

    protected $fillable = [
        'playlist_id',
        'media_id',
        'transition',
        'index',
        'duration',
    ];
}
