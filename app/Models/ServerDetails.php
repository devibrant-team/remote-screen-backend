<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServerDetails extends Model
{
    //
    protected $table = 'server_details';

    protected $fillable = [
        'name',
        'cpu',
        'ram',
        'storage',
    ];
}
