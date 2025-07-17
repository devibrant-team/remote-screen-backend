<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WedgetDetails extends Model
{
    protected $table = 'widget_details';

    protected $fillable = [
        'type',
        'city',
        'x',
        'y',
        'width',
        'height',
    ];
}
