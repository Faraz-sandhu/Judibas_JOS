<?php

namespace App\Modules\Daymark\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;

class User extends Model
{
    use Notifiable;

    protected $table = 'daymark_members';

    public $incrementing = false;

    protected $with = ['account'];

    protected $attributes = ['active' => true, 'role' => 'employee'];

    protected $guarded = ['id'];

    protected $appends = ['role_name', 'name', 'email'];

    public function getRoleNameAttribute(): string
    {
        return ($this->custom_role_id ? CustomRole::where('company_id', $this->company_id)->find($this->custom_role_id)?->name : null) ?? str($this->role)->replace('_', ' ')->title()->toString();
    }

    protected $hidden = ['account'];

    public function account()
    {
        return $this->belongsTo(\App\Models\User::class, 'id');
    }

    public function getNameAttribute(): string
    {
        return $this->account?->name ?? ($this->attributes['name'] ?? 'Super Admin');
    }

    public function getEmailAttribute(): string
    {
        return $this->account?->email ?? '';
    }

    public function getActiveAttribute($value): bool
    {
        return $this->role === 'super_admin' || ((bool) $value && (bool) $this->account?->is_active && DB::table('user_product_access')->where('user_id', $this->id)->where('product_slug', 'daymark')->exists());
    }

    protected function casts(): array
    {
        return ['password' => 'hashed', 'active' => 'boolean', 'company_id' => 'integer'];
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function reports()
    {
        return $this->hasMany(Report::class);
    }

    public function employees()
    {
        return $this->belongsToMany(self::class, 'daymark_assignments', 'manager_id', 'employee_id');
    }

    public function managers()
    {
        return $this->belongsToMany(self::class, 'daymark_assignments', 'employee_id', 'manager_id');
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, ['super_admin', 'company_admin']);
    }

    public function scopeVisibleTo($query, User $viewer)
    {
        return match ($viewer->role) {
            'super_admin' => $query,
            'company_admin', 'ceo', 'md', 'hr' => $query->where('company_id', $viewer->company_id),
            'pm', 'om' => $query->where('company_id', $viewer->company_id)->where(fn ($q) => $q->where('id', $viewer->id)->orWhereIn('id', $viewer->employees()->select('daymark_members.id'))),
            default => $query->where('id', $viewer->id),
        };
    }
}
