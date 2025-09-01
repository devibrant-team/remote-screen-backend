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
        'ratio_id',
        'branch_id',
        'is_assigned',
      
    ];

         public function user()
{
    return $this->belongsTo(User::class);
}


         public function screen()
{
    return $this->belongsTo(Screens::class, 'screen_id');
}
  
        public function group()
{
    return $this->belongsTo(Groups::class, 'group_id');
}
  
   public function ratio()
    {
       return $this->belongsTo(Ratio::class, 'ratio_id');
    }
   public function branch()
    {
        return $this->belongsTo(Branches::class);
    }

}
