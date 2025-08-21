<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ads extends Model
{
        protected $table ='ads';

    protected $fillable = [
        'media',
        'media_type',
        'description'
    ];
}
