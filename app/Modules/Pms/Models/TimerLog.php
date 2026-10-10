<?php

namespace App\Modules\Pms\Models;

use Illuminate\Database\Eloquent\Model;

class TimerLog extends Model
{
    protected $table='pms_timer_logs';
    protected $fillable = ['task_id', 'subtask_id', 'user_id', 'start_time', 'end_time'];
    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function task()
    {
        return $this->belongsTo(Tasks::class);
    }

    public function subtask()
    {
        return $this->belongsTo(Subtasks::class, 'subtask_id');
    }
}
