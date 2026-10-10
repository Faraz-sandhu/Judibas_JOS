<?php

namespace App\Modules\Pms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Modules\Pms\Models\Role;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    protected $table = 'pms_permissions';
    use HasFactory;
    protected $fillable = ['permission_name', 'permission_key'];
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'pms_permission_roles');
    }
}
