<?php

namespace App\Models;

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

}
