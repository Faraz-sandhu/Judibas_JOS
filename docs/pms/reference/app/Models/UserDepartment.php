<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UserDepartment extends Model
{
    use HasFactory;
    protected $table = 'user_departments';
    protected $fillable = ['user_id','dept_id'];
}
