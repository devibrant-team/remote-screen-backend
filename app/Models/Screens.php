<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Screens extends Model
{
protected $table = 'screens';

    protected $fillable = [
        'name',
        'code',
        'platfrom',
        'is_active',
    ];

      public function ratio()
    {
       return $this->belongsTo(Ratio::class, 'ratio_id');
    }
   public function branch()
    {
        return $this->belongsTo(Branches::class);
    }
}
