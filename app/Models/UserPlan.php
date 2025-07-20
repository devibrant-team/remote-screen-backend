<?php

namespace App\Models;

use App\Models\Employee\Plan;
use Illuminate\Database\Eloquent\Model;

class UserPlan extends Model
{
     protected $table = 'user_plan';

     protected $fillable = [
        'user_id',
        'plan_id',
        'extra_screens',
        'extra_space' ,
        'payment_date' ,
        'expire_date' ,
     ];

     public function user()
{
    return $this->belongsTo(User::class);
}


     public function plan()
{
    return $this->belongsTo(Plan::class);
}





}
