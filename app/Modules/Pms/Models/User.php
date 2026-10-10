<?php

namespace App\Modules\Pms\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends \App\Models\User
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

    protected static function booted():void {static::addGlobalScope('pms_product',fn($query)=>$query->whereIn('users.id',\Illuminate\Support\Facades\DB::table('user_product_access')->where('product_slug','projects')->select('user_id')));}
    public function notifications(){return $this->morphMany(DatabaseNotification::class,'notifiable')->latest();}
    public function can($abilities,$arguments=[]){foreach((array)$abilities as $ability){if(!\App\Modules\Pms\Services\PmsAccess::allows(request(),$ability))return false;}return true;}
    public function scopeActive(Builder $query): Builder
    {
        return $query;
    }

    public function hasPermission($permissionKey)
    {
        return \App\Modules\Pms\Services\PmsAccess::allows(request(),$permissionKey);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'pms_role_users')->withTimestamps();
    }

    public function hasRole($roleKey)
    {
        if($this->getAttribute('pms_super')===true)return $roleKey==='admin';
        return $this->roles->pluck('role_key')->contains($roleKey);
    }

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'pms_permission_roles', 'role_id', 'permission_id');
    }

    public function hasPermissionTo($permissionKey)
    {
        return $this->permissions->pluck('permission_key')->contains($permissionKey);
    }

    public function hasAnyPermission(array $permissions)
    {
        foreach($permissions as $permission){if(\App\Modules\Pms\Services\PmsAccess::allows(request(),$permission))return true;}return false;
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Projects::class, 'pms_project_user', 'user_id', 'project_id');
    }

    public function tasks(): BelongsToMany
    {
        return $this->belongsToMany(Tasks::class, 'pms_task_user', 'user_id', 'task_id');
    }

    // public function subtasks(): BelongsToMany
    // {
    //     return $this->belongsToMany(Subtasks::class, 'pms_subtask_user');
    // }

    //     public function subtasks()
    // {
    //     return $this->belongsToMany(Subtasks::class, 'pms_subtask_user', 'user_id', 'subtask_id');
    // }

    public function subtasks()
    {
        return $this->belongsToMany(Subtasks::class, 'pms_subtask_user', 'user_id', 'subtask_id')
            ->withPivot('assigned_by')
            ->withTimestamps();
    }

    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class, 'pms_user_departments', 'user_id', 'dept_id')->withTimestamps();
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
    public function notify($instance){\App\Modules\Pms\Services\PmsNotificationDelivery::send([$this],$instance);}
    public function receivesBroadcastNotificationsOn():string {return 'pms.user.'.$this->id;}
}
