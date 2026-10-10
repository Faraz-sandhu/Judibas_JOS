<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tasks extends Model
{
    protected $table = 'tasks';

    protected $fillable = [
        'project_id',
        'department_id',
        'workflow_id',
        'workflow_column_id',
        'sprint_id',
        'title',
        'description',
        'due_date',
        'original_estimate_minutes',
        'developer_estimate_set_at',
        'status',
        'position',
        'images',
        'priority',
        'start_time',
        'end_time',
        'invest_time',
        'approval',
        'approval_note',
        'created_by',
        'attachment',
        'completed_by',
    ];

    protected $casts = ['developer_estimate_set_at' => 'datetime'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Projects::class, 'project_id', 'id');
    }

    public function sprint(): BelongsTo
    {
        return $this->belongsTo(Sprint::class);
    }

    public function department(): BelongsTo { return $this->belongsTo(Department::class); }
    public function workflow(): BelongsTo { return $this->belongsTo(Workflow::class); }
    public function workflowColumn(): BelongsTo { return $this->belongsTo(WorkflowColumn::class); }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_user', 'task_id', 'user_id')->withPivot('assigned_by')->withTimestamps();
    }

    public function subtasks(): HasMany
    {
        return $this->hasMany(Subtasks::class, 'task_id', 'id');
    }

    public function assignees()
    {
        return $this->belongsToMany(User::class, 'task_user', 'task_id', 'user_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function completedBy()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function timerLogs()
    {
        return $this->hasMany(TimerLog::class, 'task_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class, 'task_id')->latest();
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TaskAttachment::class, 'task_id')->latest();
    }

    public function outgoingHandoffs(): HasMany { return $this->hasMany(TaskHandoff::class, 'source_task_id'); }
    public function incomingHandoff() { return $this->hasOne(TaskHandoff::class, 'destination_task_id'); }

    public function estimateHistories(): HasMany
    {
        return $this->hasMany(TaskEstimateHistory::class, 'task_id')->latest();
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
}
