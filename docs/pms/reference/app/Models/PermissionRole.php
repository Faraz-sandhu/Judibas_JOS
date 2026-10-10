<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PermissionRole extends Model
{
    use HasFactory;
    protected $table = 'permission_roles';
    protected $fillable = ['role_id','permission_id'];
}
