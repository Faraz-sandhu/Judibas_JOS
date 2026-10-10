<?php

namespace App\Modules\Pms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class Projects extends Model
{
    public const ACTIVE_STATUSES = ['pending', 'progress'];
    public const INACTIVE_STATUSES = ['completed', 'delivered', 'cancelled'];

    protected $table = 'pms_projects';

    protected $appends = ['is_overdue'];

    protected static function booted(): void
    {
        static::saving(function (Projects $project): void {
            $project->name = preg_replace('/\s+/u', ' ', trim((string) $project->name));
            $project->name_key = self::normalizeName($project->name);
        });
    }

    public static function normalizeName(string $name): string
    {
        return mb_strtolower(preg_replace('/\s+/u', ' ', trim($name)));
    }

    protected $fillable = [
        'company_id',
        'name',
        'description',
        'image',
        'start_date',
        'end_date',
        'url',
        'attachment',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    protected $casts = [
        'is_general' => 'boolean',
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
    ];

    public function getIsOverdueAttribute(): bool
    {
        if (! $this->end_date || in_array($this->status, ['completed', 'delivered', 'cancelled'], true)) {
            return false;
        }

        return Carbon::parse($this->end_date)->startOfDay()->lt(today());
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', self::ACTIVE_STATUSES);
    }

    public function scopeInactive(Builder $query): Builder
    {
        return $query->whereIn('status', self::INACTIVE_STATUSES);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'pms_project_user', 'project_id', 'user_id')->withPivot('assigned_by')->withTimestamps();
    }

    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class, 'pms_department_project', 'project_id', 'department_id')->withTimestamps();
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Tasks::class, 'project_id', 'id');
    }

    public function subtasks(): HasManyThrough
    {
        return $this->hasManyThrough(Subtasks::class, Tasks::class, 'project_id', 'task_id', 'id', 'id');
    }

    public function sprints(): HasMany
    {
        return $this->hasMany(Sprint::class, 'project_id')->latest('id');
    }

    public function workflows(): BelongsToMany
    {
        return $this->belongsToMany(
            Workflow::class,
            'pms_project_workflow',
            'project_id',
            'workflow_id'
        );
    }

    public function summaries(): HasMany
    {
        return $this->hasMany(Summary::class, 'project_id', 'id');
    }

    public function assignUser($userId, $senderId)
    {
        return $this->users()->attach($userId, [
            'assigned_by' => $senderId,
        ]);
    }

    public function submissions()
    {
        return $this->morphMany(Submission::class, 'submittable');
    }

    public function chatMessages()
    {
        return $this->hasMany(ProjectChat::class, 'project_id');
    }
}
