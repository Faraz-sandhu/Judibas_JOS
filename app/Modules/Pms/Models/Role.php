<?php

namespace App\Modules\Pms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Modules\Pms\Models\User;
use App\Modules\Pms\Models\Permission;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    protected $table = 'pms_roles';
  use HasFactory;
  protected $fillable = ['role_name', 'role_key'];
  public function users()
  {
    return $this->belongsToMany(User::class, 'pms_role_users')->withTimestamps();
  }
  public function permissions(): BelongsToMany
  {
    return $this->belongsToMany(Permission::class, 'pms_permission_roles');
  }
}
