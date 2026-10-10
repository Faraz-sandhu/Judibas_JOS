<?php

namespace App\Modules\Pms\Console\Commands;

use Illuminate\Console\Command;
use App\Modules\Pms\Models\Tasks;
use App\Modules\Pms\Notifications\TaskReminderNotification;
use Carbon\Carbon;

class SendTaskReminders extends Command
{
    protected $signature = 'pms:send-reminders';
    protected $description = 'Send reminders for tasks with 50% or less time remaining';

    public function handle()
    {
        $now = Carbon::now();
        $tasks = Tasks::where('status', '!=', 'completed')
            ->where('due_date', '>', $now)
            ->where(function ($query) {
                $query->whereHas('assignees')
                    ->orWhereHas('subtasks', function ($subQuery) {
                        $subQuery->whereHas('assignees');
                    });
            })
            ->with(['assignees' => function ($query) {
                $query->select('users.id', 'name', 'email');
            }])
            ->get()
            ->filter(function ($task) use ($now) {
                $createdAt = Carbon::parse($task->created_at);
                $dueDate = Carbon::parse($task->due_date);
                $totalDuration = $createdAt->diffInSeconds($dueDate);
                $remainingDuration = $now->diffInSeconds($dueDate);
                return $remainingDuration <= ($totalDuration * 0.9);
            });

        $notificationCount = 0;
        foreach ($tasks as $task) {
            $users = collect($task->assignees);
            $subtaskUsers = $task->subtasks()->with('assignees')->get()->flatMap->assignees->unique('id');
            $allUsers = $users->merge($subtaskUsers)->unique('id');

            foreach ($allUsers as $user) {
                $alreadyNotified = $user->notifications()
                    ->where('type', TaskReminderNotification::class)
                    ->where('data->task->task_id', $task->id)
                    ->whereDate('created_at', $now->toDateString())
                    ->exists();

                if (!$alreadyNotified) {
                    $user->notify(new TaskReminderNotification($task, $user->id));
                    $notificationCount++;
                    $this->info("Sent reminder for task {$task->id} to user {$user->id}");
                }
            }
        }

        $this->info("Task reminders sent successfully. Processed {$tasks->count()} tasks, sent {$notificationCount} notifications.");
    }
}