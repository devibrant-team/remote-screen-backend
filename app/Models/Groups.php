<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Groups extends Model
{
    protected $table ='groups';

    protected $fillable = [
        'name',
        'branch_id',
        'user_id',
        'ratio_id',
        'screen_number',
    ];
}
