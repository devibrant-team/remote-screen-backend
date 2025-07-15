<?php

namespace App\Models\Employee;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
class Employee extends Authenticatable
{
      use HasApiTokens, Notifiable;
    protected $table = 'employee';
    protected $fillable = ['email', 'password'];
    protected $hidden = ['password'];
}
