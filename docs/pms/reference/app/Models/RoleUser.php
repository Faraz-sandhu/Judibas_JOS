<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class RoleUser extends Model
{
  use HasFactory;
  protected $table = 'role_users';
  protected $fillable = ['user_id','role_id'];
}
