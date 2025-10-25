<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
      'start_day',
        'end_time',
      'end_day',
    ];

    public function playlist(): BelongsTo
    {
        return $this->belongsTo(Playlist::class, 'playlist_id', 'id');
    }
  
  public function details() { return $this->hasMany(ScheduleDetails::class, 'schedule_id'); }
    
}
