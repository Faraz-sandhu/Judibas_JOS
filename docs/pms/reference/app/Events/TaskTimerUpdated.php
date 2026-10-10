<?php

namespace App\Events;

use App\Models\TimerLog;
use Illuminate\Broadcasting\InteractsWithSockets;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Bus\Queueable;


class TaskTimerUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels, Queueable;

    public $timerLog;
    public $userId;

    public function __construct(TimerLog $timerLog, $userId)
    {
        \Log::info('TaskTimerUpdated constructed', ['user_id' => $userId, 'timer_log_id' => $timerLog->id]);
        $this->timerLog = $timerLog;
        $this->userId = $userId;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('user-task-timer.' . $this->userId);
    }

    public function broadcastAs()
    {
        return 'task-timer-updated';
    }

    public function broadcastWith()
    {
        $data = [
            'timer_log_id' => $this->timerLog->id,
            'task_id' => $this->timerLog->task_id,
            'subtask_id' => $this->timerLog->subtask_id,
            'project_id' => optional($this->timerLog->task->project)->id,
            'project_name' => optional($this->timerLog->task->project)->name ?? '',
            'task_title' => optional($this->timerLog->subtask)->title ?? ($this->timerLog->task->title ?? ''),
            'is_running' => is_null($this->timerLog->end_time),
        ];
        \Log::info('TaskTimerUpdated broadcastWith', $data);
        return $data;
    }
}
