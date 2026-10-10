<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    use HasFactory;
    protected $fillable = ['dept_name', 'description', 'status'];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_departments', 'dept_id', 'user_id')->withTimestamps();
    }

    public function workflows(): HasMany
    {
        return $this->hasMany(Workflow::class);
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Projects::class, 'department_project', 'department_id', 'project_id')->withTimestamps();
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Tasks::class);
    }
}
