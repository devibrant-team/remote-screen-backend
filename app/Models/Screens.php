<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Screens extends Model
{
protected $table = 'screens';

    protected $fillable = [
        'name',
        'code',
        'ratio_id',
        'branch_id',
        'is_active',
    ];

      public function ratio()
    {
        return $this->belongsTo(Ratio::class);
    }
}
