<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
    ];
}
