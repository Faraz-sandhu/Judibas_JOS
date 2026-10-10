<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'surname',
        'designation',
        'email',
        'email_verified_at',
        'password',
        'status',
        'profile_img',
        'last_seen_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query;
    }

    public function hasPermission($permissionKey)
    {
        return $this->permissions->pluck('permission_key')->contains($permissionKey);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_users')->withTimestamps();
    }

    public function hasRole($roleKey)
    {
        return $this->roles->pluck('role_key')->contains($roleKey);
    }

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'permission_roles', 'role_id', 'permission_id');
    }

    public function hasPermissionTo($permissionKey)
    {
        return $this->permissions->pluck('permission_key')->contains($permissionKey);
    }

    public function hasAnyPermission(array $permissions)
    {
        return $this->permissions->pluck('permission_key')->intersect($permissions)->isNotEmpty();
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Projects::class, 'project_user', 'user_id', 'project_id');
    }

    public function tasks(): BelongsToMany
    {
        return $this->belongsToMany(Tasks::class, 'task_user', 'user_id', 'task_id');
    }

    // public function subtasks(): BelongsToMany
    // {
    //     return $this->belongsToMany(Subtasks::class, 'subtask_user');
    // }

    //     public function subtasks()
    // {
    //     return $this->belongsToMany(Subtasks::class, 'subtask_user', 'user_id', 'subtask_id');
    // }

    public function subtasks()
    {
        return $this->belongsToMany(Subtasks::class, 'subtask_user', 'user_id', 'subtask_id')
            ->withPivot('assigned_by')
            ->withTimestamps();
    }

    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class, 'user_departments', 'user_id', 'dept_id')->withTimestamps();
    }

    public function summaries()
    {
        return $this->hasMany(Summary::class, 'user_id', 'id');
    }

    public function sentInvitations()
    {
        return $this->hasMany(Invitation::class, 'sender_id');
    }

    public function receivedInvitations()
    {
        return $this->hasMany(Invitation::class, 'recipient_id');
    }

    public function timerLogs()
    {
        return $this->hasMany(TimerLog::class, 'user_id');
    }
}
