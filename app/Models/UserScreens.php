<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserScreens extends Model
{
    protected $table = 'user_screens';

    protected $fillable = [
        'user_id',
        'screen_id',
        'group_id',
        'is_extra',
    ];

         public function user()
{
    return $this->belongsTo(User::class);
}


         public function screen()
{
    return $this->belongsTo(Screens::class);
}
         public function ratio()
{
    return $this->belongsTo(Ratio::class);
}
}
