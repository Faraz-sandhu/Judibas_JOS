<?php

namespace App\Modules\Pms\Http\Controllers;
use App\Http\Controllers\Controller;
use App\Modules\Pms\Services\PmsAccess;

use App\Modules\Pms\Events\TaskTimerUpdated;
use App\Modules\Pms\Models\Subtasks;
use App\Modules\Pms\Models\Tasks;
use App\Modules\Pms\Models\TimerLog;
use Carbon\Carbon;
use Carbon\CarbonInterval;
use Illuminate\Http\Request;
use App\Modules\Pms\Services\PmsAuth as Auth;
use Illuminate\Support\Facades\DB;

class TimerLogController extends Controller
{
    // public function startTimer(Request $request)
    // {
    //     $request->validate([
    //         'task_id' => 'required|exists:pms_tasks,id',
    //         'subtask_id' => 'nullable|exists:pms_subtasks,id',
    //     ]);

    //     $task = Tasks::findOrFail($request->task_id);
    //     $subtask = $request->subtask_id ? Subtasks::findOrFail($request->subtask_id) : null;
    //     $userId = auth()->id();
    //     //Check user assign to task or subtask
    //    if($subtask){
    //       $isAssign = $subtask->users()->where('user_id', $userId)->exists();
    //    }else{
    //       $isAssign = $task->users()->where('user_id', $userId)->exists();
    //    }
    //     if (!$isAssign) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'You are not assigned to this task',
    //         ], 400);
    //     }

    //     // Check if the user has another running timer
    //     $runningLog = TimerLog::where('user_id', $userId)
    //         ->whereNotNull('start_time')
    //         ->whereNull('end_time')
    //         ->first();

    //     if ($runningLog) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Another task or subtask is already running. Please pause or stop it first.',
    //         ], 400);
    //     }

    //     // Prevent starting timer on completed task/subtask
    //     $target = $subtask ?: $task;
    //     if ($target->status === 'completed') {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Cannot start timer on a completed ' . ($subtask ? 'subtask' : 'task'),
    //         ], 400);
    //     }

    //     // Create a new timer log
    //     $timerLog = TimerLog::create([
    //         'task_id' => $task->id,
    //         'subtask_id' => $request->subtask_id,
    //         'user_id' => $userId,
    //         'start_time' => now(),
    //         'end_time' => null,
    //     ]);

    //     // Update task/subtask status to in_progress
    //     $target->status = 'in_progress';
    //     $target->save();
    //     broadcast(new TaskTimerUpdated($timerLog, Auth::id()));
    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Timer started',
    //         'start_time' => $timerLog->start_time,
    //         'task_id' => $task->id,
    //         'subtask_id' => $request->subtask_id,
    //     ]);
    // }

    public function startTimer(Request $request)
    {
        $request->validate([
            'task_id' => 'required|exists:pms_tasks,id',
            'subtask_id' => 'nullable|exists:pms_subtasks,id',
        ]);

        $task = Tasks::findOrFail($request->task_id);
        $subtask = $request->subtask_id ? Subtasks::findOrFail($request->subtask_id) : null;
        abort_if($subtask && (int) $subtask->task_id !== (int) $task->id, 422, 'The subtask does not belong to the selected task.');
        $userId = auth()->id();

        // Check if user is assigned to task or subtask
        $isAssigned = $subtask
            ? $subtask->users()->where('user_id', $userId)->exists()
            : $task->users()->where('user_id', $userId)->exists();

        if (! $isAssigned) {
            return response()->json([
                'success' => false,
                'message' => 'You are not assigned to this task or subtask',
            ], 400);
        }

        // Check if user already has a running timer
        $runningLog = TimerLog::where('user_id', $userId)
            ->whereNotNull('start_time')
            ->whereNull('end_time')
            ->first();

        if ($runningLog) {
            return response()->json([
                'success' => false,
                'message' => 'Another task or subtask is already running. Please pause or stop it first.',
            ], 400);
        }

        // Prevent starting timer on completed task/subtask
        $target = $subtask ?: $task;
        if ($target->status === 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot start timer on a completed '.($subtask ? 'subtask' : 'task'),
            ], 400);
        }

        // Create the actual running timer log
        $timerLog = TimerLog::create([
            'task_id' => $task->id,
            'subtask_id' => $request->subtask_id,
            'user_id' => $userId,
            'start_time' => now(),
            'end_time' => null,
        ]);

        // Update status to in_progress
        $target->status = 'in_progress';
        $target->save();

        $this->broadcastTimerUpdate($timerLog);

        return response()->json([
            'success' => true,
            'message' => 'Timer started',
            'start_time' => $timerLog->start_time,
            'task_id' => $task->id,
            'subtask_id' => $request->subtask_id,
        ]);
    }

    /**
     * Pause an active timer.
     */
    public function pauseTimer(Request $request)
    {
        $request->validate([
            'task_id' => 'required|exists:pms_tasks,id',
            'subtask_id' => 'nullable|exists:pms_subtasks,id',
        ]);

        $userId = auth()->id();
        $timerLog = TimerLog::where('user_id', $userId)
            ->where('task_id', $request->task_id)
            ->where('subtask_id', $request->subtask_id ?: null)
            ->whereNotNull('start_time')
            ->whereNull('end_time')
            ->first();

        if (! $timerLog) {
            return response()->json([
                'success' => false,
                'message' => 'No active timer found for this '.($request->subtask_id ? 'subtask' : 'task'),
            ], 400);
        }

        // Pause the timer
        $timerLog->end_time = now();
        $timerLog->save();

        // Calculate invest_time for the task or subtask
        $target = $request->subtask_id ? Subtasks::find($request->subtask_id) : Tasks::find($request->task_id);
        $timerLogs = TimerLog::where('task_id', $request->task_id)
            ->where('subtask_id', $request->subtask_id ?: null)
            ->get();
        $target->invest_time = $this->calculateInvestTime($timerLogs);
        $target->save();
        $this->broadcastTimerUpdate($timerLog);

        return response()->json([
            'success' => true,
            'message' => 'Timer paused',
            'invest_time' => $target->invest_time,
            'task_id' => $request->task_id,
            'subtask_id' => $request->subtask_id,
        ]);
    }

    /**
     * Stop an active timer without changing the task's workflow status.
     */
    public function stopTimer(Request $request)
    {
        $request->validate([
            'task_id' => 'required|exists:pms_tasks,id',
            'subtask_id' => 'nullable|exists:pms_subtasks,id',
        ]);

        $userId = auth()->id();
        $taskId = $request->task_id;
        $subtaskId = $request->subtask_id ?: null;

        // Use a transaction to prevent race conditions
        return DB::transaction(function () use ($userId, $taskId, $subtaskId) {
            // Find the active timer (end_time is null)
            $timerLog = TimerLog::where('user_id', $userId)
                ->where('task_id', $taskId)
                ->where('subtask_id', $subtaskId)
                ->whereNotNull('start_time')
                ->whereNull('end_time') // Explicitly check for running timer
                ->lockForUpdate() // Lock the row to prevent concurrent updates
                ->first();

            if (! $timerLog) {
                return response()->json([
                    'success' => false,
                    'message' => 'No running timer found for this '.($subtaskId ? 'subtask' : 'task'),
                ], 400);
            }

            // Update end_time
            $timerLog->end_time = Carbon::now();
            $timerLog->save();

            // Save the tracked time only. Workflow movement is handled separately
            // through the board/status controls and their permissions.
            $target = $subtaskId ? Subtasks::find($subtaskId) : Tasks::find($taskId);
            $timerLogs = TimerLog::where('task_id', $taskId)
                ->where('subtask_id', $subtaskId)
                ->get();
            $target->invest_time = $this->calculateInvestTime($timerLogs);
            $target->save();
            $this->broadcastTimerUpdate($timerLog);

            return response()->json([
                'success' => true,
                'message' => 'Timer stopped and tracked time saved',
                'invest_time' => $target->invest_time,
                'status' => $target->status,
                'task_id' => $taskId,
                'subtask_id' => $subtaskId,
            ]);
        });
    }

    /**
     * Check if the user has a running timer.
     */
    public function checkRunningTimer(Request $request)
    {
        $request->validate([
            'project_id' => 'required|exists:pms_projects,id',
        ]);

        $userId = auth()->id();
        $runningTimer = TimerLog::where('user_id', $userId)
            ->whereNotNull('start_time')
            ->whereNull('end_time')
            ->first();

        return response()->json([
            'success' => true,
            'runningTimer' => $runningTimer ? [
                'task_id' => $runningTimer->task_id,
                'subtask_id' => $runningTimer->subtask_id,
                'user_id' => $runningTimer->user_id,
            ] : null,
        ]);
    }

    /**
     * Calculate total invested time from timer logs.
     */
    private function calculateInvestTime($timerLogs)
    {
        $totalSeconds = $timerLogs->whereNotNull('end_time')->sum(function ($log) {
            $startTime = is_string($log->start_time) ? Carbon::parse($log->start_time) : $log->start_time;
            $endTime = is_string($log->end_time) ? Carbon::parse($log->end_time) : $log->end_time;

            return $endTime->diffInSeconds($startTime);
        });

        return CarbonInterval::seconds($totalSeconds)->cascade()->forHumans();
    }

    private function broadcastTimerUpdate(TimerLog $timerLog): void
    {
        try {
            broadcast(new TaskTimerUpdated($timerLog, Auth::id()));
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}
