<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MediaItem extends Model
{
    protected $table = 'item_media';

    protected $fillable = [
        'playlist_item_id',
        'media_id',
        'scale',
        'index',
       'widget_id'
    ];

}
