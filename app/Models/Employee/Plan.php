<?php

namespace App\Models\Employee;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
     protected $table = 'plans';

    protected $fillable = [
        'name',
        'screen_number',
        'storage',
        'price',
        'offer',
        'plan_time',
        'is_recommended',
        'access_num',
    ];
    public function userPlans()
{
    return $this->hasMany(\App\Models\UserPlan::class, 'plan_id');
}

}
