<?php

namespace App\Models\Employee;

use Illuminate\Database\Eloquent\Model;

class Custom extends Model
{
 protected $table = 'custom';

    protected $fillable = [
        'type',
        'quantity',
        'price',
    ];}
