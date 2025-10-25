<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItemMedia extends Model
{
    protected $table = 'item_media';

    protected $fillable = [
        'playlist_item_id',
        'media_id',
        'scale',
        'index',
		'widget_id'
    ];

     public function media()
    {
        return $this->belongsTo(Media::class, 'media_id');
    }
  
   public function widget()
    {
        return $this->belongsTo(WidgetDetails::class, 'widget_id');
    }
}
