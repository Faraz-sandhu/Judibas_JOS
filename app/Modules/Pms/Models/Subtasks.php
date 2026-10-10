<?php

namespace App\Modules\Pms\Models;

use Illuminate\Database\Eloquent\Model;

class Subtasks extends Model
{
    protected $table = 'pms_subtasks';
    protected $fillable = [
        'task_id',
        'title',
        'description',
        'due_date',
        'status',
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


    public function users()
    {
        return $this->belongsToMany(User::class, 'pms_subtask_user', 'subtask_id', 'user_id')->withPivot('assigned_by')->withTimestamps();
    }

    public function task()
    {
        return $this->belongsTo(Tasks::class);
    }

    public function timerLogs()
    {
        return $this->hasMany(TimerLog::class, 'subtask_id');
    }

    public function assignees()
    {
        return $this->belongsToMany(User::class, 'pms_subtask_user', 'subtask_id', 'user_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function completedBy()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
    public function submissions()
    {
        return $this->morphMany(Submission::class, 'submittable');
    }
}
