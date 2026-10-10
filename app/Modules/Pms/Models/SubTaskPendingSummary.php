<?php

namespace App\Modules\Pms\Models;

use Illuminate\Database\Eloquent\Model;

class SubTaskPendingSummary extends Model
{
    protected $table='pms_sub_task_pending_summaries';
    protected $fillable = [
        'user_id', 'author_name', 'invest_time',
        'task_id',
        'sub_task_id',
        'sub_task_title',
        'date',
    ];

    // Relationships
    public function user() {
        return $this->belongsTo(User::class);
    }

    public function task() {
        return $this->belongsTo(Tasks::class);
    }

    public function subtask() {
        return $this->belongsTo(Subtasks::class);
    }
}
