<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;
use App\Models\Permission;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
  use HasFactory;
  protected $fillable = ['role_name', 'role_key'];
  public function users()
  {
    return $this->belongsToMany(User::class, 'role_users')->withTimestamps();
  }
  public function permissions(): BelongsToMany
  {
    return $this->belongsToMany(Permission::class, 'permission_roles');
  }
}
