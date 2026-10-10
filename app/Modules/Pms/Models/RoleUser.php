<?php

namespace App\Modules\Pms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class RoleUser extends Model
{
  use HasFactory;
  protected $table = 'pms_role_users';
  protected $fillable = ['user_id','role_id'];
}
