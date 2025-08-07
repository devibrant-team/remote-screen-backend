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
        'grid_id',
    ];

    public function playListItemStyle()
    {
        return $this->belongsTo(ListItemStyle::class, 'grid_id');
    }

    public function itemMedia()
    {
        return $this->hasMany(ItemMedia::class, 'playlist_item_id');
    }

}
