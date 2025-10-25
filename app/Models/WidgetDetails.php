<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WidgetDetails extends Model
{
    protected $table = 'widget_details';

    protected $fillable = [
        'type',
        'city',
        'position'
    ];
}
