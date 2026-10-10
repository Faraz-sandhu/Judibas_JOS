<?php

namespace App\Modules\Pms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UserDepartment extends Model
{
    use HasFactory;
    protected $table = 'pms_user_departments';
    protected $fillable = ['user_id','dept_id'];
}
