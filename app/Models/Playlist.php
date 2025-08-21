<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Playlist extends Model
{
    //
    protected $table = 'playlist';

    protected $fillable = [
      'name',  
      'user_id',  
      'style_id',  
      'duration',  
      'slide_number',
      'ratio_id'
    ];

      public function playListItems()
    {
        return $this->hasMany(PlaylistItem::class, 'playlist_id');
    }

    // Each playlist BELONGS TO one style
    public function planListStyle()
    {
        return $this->belongsTo(PlaylistStyle::class, 'style_id');
    }

    public function schedules(): HasMany
    {
        // FK is playlist_id by your schema
        return $this->hasMany(Schedule::class, 'playlist_id', 'id');
    }

}
