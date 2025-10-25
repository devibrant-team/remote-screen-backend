<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScheduleDetails extends Model
{
    protected $table = 'schedule_details';

    protected $fillable = [     
        'schedule_id',
        'screen_id',
    ];
  
  
  public function schedule() { return $this->belongsTo(Schedule::class, 'schedule_id'); }
public function screen()   { return $this->belongsTo(Screens::class, 'screen_id'); }
  
}
