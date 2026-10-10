<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Role;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    use HasFactory;
    protected $fillable = ['permission_name', 'permission_key'];
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'permission_roles');
    }
}
