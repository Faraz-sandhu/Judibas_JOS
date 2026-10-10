<?php

namespace App\Http\Controllers;

use App\Models\Projects;
use App\Models\SubTaskPendingSummary;
use App\Models\Subtasks;
use App\Models\Summary;
use App\Models\Tasks;
use App\Models\TimerLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class SummaryController extends Controller
{
    use AuthorizesRequests;

    // public function index(Request $request)
    // {
    //     $user = auth()->user();
    //     $isAdmin = $user->hasRole('admin');

    //     $selectedUserId = !$isAdmin ? $user->id : $request->user_id;
    //     $selectedProjectId = $request->project_id;
    //     $startDate = $request->start_date ? Carbon::parse($request->start_date)->startOfDay() : null;
    //     $endDate = $request->end_date ? Carbon::parse($request->end_date)->endOfDay() : null;

    //     $logs = TimerLog::with(['task.project', 'subtask.task.project', 'user'])
    //         ->when($selectedUserId, fn($q) => $q->where('user_id', $selectedUserId))
    //         ->where(function ($q) {
    //             $q->whereNotNull('task_id')
    //                 ->orWhereNotNull('subtask_id');
    //         })
    //         ->when($selectedProjectId, function ($q) use ($selectedProjectId) {
    //             $q->where(function ($q2) use ($selectedProjectId) {
    //                 $q2->whereHas('task.project', fn($q3) => $q3->where('id', $selectedProjectId))
    //                     ->orWhereHas('subtask.task.project', fn($q3) => $q3->where('id', $selectedProjectId));
    //             });
    //         })
    //         ->whereNotNull('start_time')
    //         ->get();

    //     // Filter by timerLog start_time instead of task/subtask created_at
    //     $logs = $logs->filter(function ($log) use ($startDate, $endDate) {
    //         $logStart = $log->start_time;
    //         if ($startDate && $logStart < $startDate) return false;
    //         if ($endDate && $logStart > $endDate) return false;
    //         return true;
    //     });

    //     $taskGroups = $logs->whereNotNull('task_id')
    //         ->whereNull('subtask_id') // only direct task logs, exclude subtasks
    //         ->groupBy(fn($log) => $log->user_id . '-' . $log->task_id . '-' . $log->start_time->toDateString());

    //     $subtaskGroups = $logs->whereNotNull('subtask_id')
    //         ->groupBy(fn($log) => $log->user_id . '-' . $log->subtask_id . '-' . $log->start_time->toDateString());

    //     $rows = [];

    //     foreach ($taskGroups as $groupLogs) {
    //         $task = $groupLogs->first()->task;
    //         $logUser = $groupLogs->first()->user;
    //         $totalSeconds = $groupLogs->sum(fn($log) => $log->start_time->diffInSeconds($log->end_time));

    //         // Safely fetch assigned_by
    //         $assignedByUser = $task->users()->wherePivot('task_id', $task->id)->first();
    //         $assignedById = $assignedByUser ? $assignedByUser->pivot->assigned_by : null;
    //         $assignedByName = $assignedById ? User::find($assignedById)?->name ?? '-' : '-';

    //         $rows[] = [
    //             'project' => $task->project->name ?? '-',
    //             'type' => 'Task',
    //             'title' => $task->title,
    //             'status' => $task->status,
    //             'user' => $logUser->name,
    //             'time_spent' => gmdate("H:i:s", $totalSeconds),
    //             'due_date' => $task->due_date ? Carbon::parse($task->due_date)->format('Y-m-d h:i A') : '-',
    //             'created_at' => $task->created_at->format('Y-m-d'),
    //             'work_date' => $groupLogs->first()->start_time->format('Y-m-d'),
    //             'assigned_by' => $assignedByName,
    //         ];
    //     }

    //     foreach ($subtaskGroups as $groupLogs) {
    //         $subtask = $groupLogs->first()->subtask;
    //         $logUser = $groupLogs->first()->user;
    //         $task = $subtask->task;
    //         $totalSeconds = $groupLogs->sum(fn($log) => $log->start_time->diffInSeconds($log->end_time));

    //         // Safely fetch assigned_by
    //         $assignedByUser = $subtask->users()->wherePivot('subtask_id', $subtask->id)->first();
    //         $assignedById = $assignedByUser ? $assignedByUser->pivot->assigned_by : null;
    //         $assignedByName = $assignedById ? User::find($assignedById)?->name ?? '-' : '-';

    //         $rows[] = [
    //             'project' => $task->project->name ?? '-',
    //             'type' => 'Subtask',
    //             'title' => $subtask->title,
    //             'status' => $subtask->status,
    //             'user' => $logUser->name,
    //             'time_spent' => gmdate("H:i:s", $totalSeconds),
    //             'due_date' => $subtask->due_date ? Carbon::parse($subtask->due_date)->format('Y-m-d h:i A') : '-',
    //             'created_at' => $subtask->created_at->format('Y-m-d'),
    //             'work_date' => $groupLogs->first()->start_time->format('Y-m-d'),
    //             'assigned_by' => $assignedByName,
    //         ];
    //     }

    //     if ($request->ajax()) {
    //         return response()->json(['data' => $rows]);
    //     }

    //     $users = $isAdmin ? User::select('id', 'name')->where('id', '!=', $user->id)->get() : collect();
    //     $projects = $isAdmin
    //         ? Projects::select('id', 'name')->get()
    //         : Projects::whereHas('tasks.users', fn($q) => $q->where('users.id', $user->id))
    //         ->orWhereHas('tasks.subtasks.users', fn($q) => $q->where('users.id', $user->id))
    //         ->select('projects.id', 'projects.name')
    //         ->distinct()
    //         ->get();

    //     return view('dashboard.summary.index', compact('users', 'projects'));
    // }

    public function index(Request $request)
    {
        Gate::authorize('summary-view');
        $user = auth()->user();
        $allUsersView = Gate::allows('view-all-users-summary');
        $selectedUserId = $allUsersView ? $request->user_id : $user->id;
        $selectedProjectId = $request->project_id;
        $startDate = $request->start_date ? Carbon::parse($request->start_date)->startOfDay() : null;
        $endDate = $request->end_date ? Carbon::parse($request->end_date)->endOfDay() : null;

        $logs = TimerLog::with(['task.project', 'task.users', 'subtask.task.project', 'subtask.users', 'user'])
            ->when($selectedUserId, fn ($q) => $q->where('user_id', $selectedUserId))
            ->where(fn ($q) => $q->whereNotNull('task_id')->orWhereNotNull('subtask_id'))
            ->when($selectedProjectId, fn ($q) => $q->whereHas('task.project', fn ($q2) => $q2->where('id', $selectedProjectId))
                ->orWhereHas('subtask.task.project', fn ($q2) => $q2->where('id', $selectedProjectId)))
            ->whereNotNull('start_time')
            ->whereNotNull('end_time')
            ->when($startDate, fn ($q) => $q->where('start_time', '>=', $startDate))
            ->when($endDate, fn ($q) => $q->where('start_time', '<=', $endDate))
            ->get();

        $taskGroups = $logs->whereNotNull('task_id')->whereNull('subtask_id')
            ->groupBy(fn ($l) => "{$l->user_id}-{$l->task_id}-{$l->start_time->toDateString()}");
        $subtaskGroups = $logs->whereNotNull('subtask_id')
            ->groupBy(fn ($l) => "{$l->user_id}-{$l->subtask_id}-{$l->start_time->toDateString()}");

        $rows = [];
        $userNames = [];
        $userName = function ($id) use (&$userNames) {
            if (! $id) {
                return '-';
            }

            return $userNames[$id] ??= User::whereKey($id)->value('name') ?? '-';
        };

        foreach ([$taskGroups, $subtaskGroups] as $groups) {
            foreach ($groups as $group) {
                $first = $group->first();
                $isSubtask = $first->subtask_id !== null;
                $model = $isSubtask ? $first->subtask : $first->task;
                $logUser = $first->user;
                $totalSecs = $group->sum(fn ($l) => $l->start_time->diffInSeconds($l->end_time));

                $assignedByName = '-';
                $pivot = $model->users->first();
                if ($pivot && ($assignedId = $pivot->pivot->assigned_by)) {
                    $assignedByName = $userName($assignedId);
                }

                $rows[] = [
                    'project_id' => $isSubtask ? $model->task->project_id : $model->project_id,
                    'project' => $isSubtask ? $model->task->project->name : $model->project->name,
                    'type' => $isSubtask ? 'Subtask' : 'Task',
                    'title' => $model->title,
                    'status' => $model->status,
                    'user' => $logUser?->name ?? '-',
                    'time_spent' => gmdate('H:i:s', $totalSecs),
                    'due_date' => $model->due_date ? Carbon::parse($model->due_date)->format('Y-m-d h:i A') : '-',
                    'created_at' => $model->created_at->format('Y-m-d'),
                    'work_date' => $first->start_time->format('Y-m-d'),
                    'assigned_by' => $assignedByName,
                ];
            }
        }

        // Completed tasks/subtasks without logs
        $loggedTaskIds = $logs->pluck('task_id')->filter()->unique();
        $loggedSubtaskIds = $logs->pluck('subtask_id')->filter()->unique();

        $completedTasks = Tasks::with('project', 'users', 'completedBy')
            ->when($selectedUserId, fn ($q) => $q->where('completed_by', $selectedUserId))
            ->when($selectedProjectId, fn ($q) => $q->where('project_id', $selectedProjectId))
            ->where('status', 'completed')
            ->whereNotNull('completed_by')
            ->whereNotIn('id', $loggedTaskIds)
            ->when($startDate, fn ($q) => $q->where('updated_at', '>=', $startDate))
            ->when($endDate, fn ($q) => $q->where('updated_at', '<=', $endDate))
            ->get();

        foreach ($completedTasks as $task) {
            $pivot = $task->users->first();
            $assignedByName = ($pivot && ($id = $pivot->pivot->assigned_by))
                ? $userName($id) : '-';
            $completedByName = $task->completedBy?->name ?? '-';

            $rows[] = [
                'project_id' => $task->project_id,
                'project' => $task->project->name ?? '-',
                'type' => 'Task',
                'title' => $task->title,
                'status' => $task->status,
                'user' => $completedByName,
                'time_spent' => '00:00:00',
                'due_date' => $task->due_date ? Carbon::parse($task->due_date)->format('Y-m-d h:i A') : '-',
                'created_at' => $task->created_at->format('Y-m-d'),
                'work_date' => $task->updated_at->format('Y-m-d'),
                'assigned_by' => $assignedByName,
            ];
        }

        $completedSubtasks = Subtasks::with('task.project', 'users', 'completedBy')
            ->when($selectedUserId, fn ($q) => $q->where('completed_by', $selectedUserId))
            ->where('status', 'completed')
            ->whereNotNull('completed_by')
            ->whereNotIn('id', $loggedSubtaskIds)
            ->when($selectedProjectId, fn ($q) => $q->whereHas('task', fn ($q2) => $q2->where('project_id', $selectedProjectId)))
            ->when($startDate, fn ($q) => $q->where('updated_at', '>=', $startDate))
            ->when($endDate, fn ($q) => $q->where('updated_at', '<=', $endDate))
            ->get();

        foreach ($completedSubtasks as $subtask) {
            $pivot = $subtask->users->first();
            $assignedByName = ($pivot && ($id = $pivot->pivot->assigned_by))
                ? $userName($id) : '-';

            $completedByName = $subtask->completedBy?->name ?? '-';

            $rows[] = [
                'project_id' => $subtask->task->project_id ?? null,
                'project' => $subtask->task->project->name ?? '-',
                'type' => 'Subtask',
                'title' => $subtask->title,
                'status' => $subtask->status,
                'user' => $completedByName,
                'time_spent' => '00:00:00',
                'due_date' => $subtask->due_date ? Carbon::parse($subtask->due_date)->format('Y-m-d h:i A') : '-',
                'created_at' => $subtask->created_at->format('Y-m-d'),
                'work_date' => $subtask->updated_at->format('Y-m-d'),
                'assigned_by' => $assignedByName,
            ];
        }

        if ($request->ajax()) {
            return response()->json(['data' => $rows]);
        }

        $users = $allUsersView
            ? User::select('id', 'name')->where('id', '!=', $user->id)->get()
            : collect();

        $projects = $allUsersView
            ? Projects::select('id', 'name')->get()
            : Projects::whereHas('tasks.users', fn ($q) => $q->where('users.id', $user->id))
                ->orWhereHas('tasks.subtasks.users', fn ($q) => $q->where('users.id', $user->id))
                ->select('projects.id', 'projects.name')->distinct()->get();

        return view('dashboard.summary.index', compact('users', 'projects'));
    }

    // public function index()
    // {
    //     $summary = [];
    //     $user = Auth::user();
    //     $users=User::where('status', 1)->where('id', '!=', 1)->get();
    //     if ($user->hasRole('admin')) {
    //         $summary = Summary::orderBy('id','desc')->get();
    //         $summaryPending = SubTaskPendingSummary::groupBy('user_id', 'task_id', 'date')
    //             ->selectRaw('user_id, task_id, date, GROUP_CONCAT(sub_task_id) as sub_task_ids')
    //             ->get();
    //     }
    //     else if($user->hasRole('team_leader'))
    //     {
    //         $departmentIds = $user->departments()->pluck('departments.id');
    //         $users = User::whereHas('departments', function ($query) use ($departmentIds) {
    //             $query->whereIn('departments.id', $departmentIds);
    //         })->whereHas('roles', function ($query) {
    //             $query->whereNotIn('role_key', ['team_leader', 'project_manager']);
    //         })->get();
    //         $usersIds=$users->pluck('id');
    //         $summary = Summary::where('user_id',$user->id)
    //         ->orWhereIn('user_id',$usersIds)->orderBy('id','desc')->get();
    //         $summaryPending = SubTaskPendingSummary::groupBy('user_id', 'task_id', 'date')
    //             ->selectRaw('user_id, task_id, date, GROUP_CONCAT(sub_task_id) as sub_task_ids')
    //             ->get();
    //     }
    //     else {
    //         $summary = Summary::where('user_id', Auth::user()->id)->orderBy('id','desc')->get();
    //         $summaryPending = SubTaskPendingSummary::where('user_id', Auth::user()->id)
    //             ->groupBy('user_id', 'task_id', 'date')
    //             ->selectRaw('user_id, task_id, date, GROUP_CONCAT(sub_task_id) as sub_task_ids')
    //             ->get();
    //     }

    //     return view('dashboard.summary.index', compact('summary', 'summaryPending','users'));
    // }
    public function UserFilter(Request $request)
    {
        Gate::authorize('summary-view');
        $user_id = $request->user_id;
        $summary = Summary::where('user_id', $user_id)->get();
        $summaryPending = SubTaskPendingSummary::where('user_id', $user_id)
            ->groupBy('user_id', 'task_id', 'date')
            ->selectRaw('user_id, task_id, date, GROUP_CONCAT(sub_task_id) as sub_task_ids')
            ->get();
        if ($summary->isEmpty() && $summaryPending->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No summary found for the selected user.',
            ], 404);
        }
        foreach ($summary as $item) {
            $item->user_name = User::find($item->user_id)->name;
            $item->project_name = Projects::find($item->project_id)->name;
            $item->tasks = json_decode($item->task, true);
        }

        $html = view('dashboard.summary.summaryCard', compact('summary', 'summaryPending'))->render();

        return response()->json([
            'html' => $html,
            'success' => true,
            'message' => 'Summary Filtered successfully',
        ], 200);
    }

    public function DateFilter(Request $request)
    {
        Gate::authorize('summary-view');
        $date = $request->date;
        $user = Auth::user();
        if ($user->hasRole('admin')) {
            $summary = Summary::where('date', $date)->get();
            $summaryPending = SubTaskPendingSummary::where('date', $date)
                ->groupBy('user_id', 'task_id', 'date')
                ->selectRaw('user_id, task_id, date, GROUP_CONCAT(sub_task_id) as sub_task_ids')
                ->get();
        } elseif ($user->hasRole('team_leader')) {
            $summary = Summary::where('date', $date)->get();
            $summaryPending = SubTaskPendingSummary::groupBy('user_id', 'task_id', 'date')
                ->selectRaw('user_id, task_id, date, GROUP_CONCAT(sub_task_id) as sub_task_ids')
                ->get();
        } else {
            $summary = Summary::where('user_id', Auth::user()->id)->where('date', $date)->get();
            $summaryPending = SubTaskPendingSummary::where('user_id', $user->id)
                ->where('date', $date)
                ->groupBy('user_id', 'task_id', 'date')
                ->selectRaw('user_id, task_id, date, GROUP_CONCAT(sub_task_id) as sub_task_ids')
                ->get();
        }
        if ($summary->isEmpty() && $summaryPending->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No summary found for the selected date.',
            ], 404);
        }
        foreach ($summary as $item) {
            $item->user_name = User::find($item->user_id)->name;
            $item->project_name = Projects::find($item->project_id)->name;
            $item->tasks = json_decode($item->task, true);
        }
        $html = view('dashboard.summary.summaryCard', compact('summary', 'summaryPending'))->render();

        return response()->json([
            'html' => $html,
            'success' => true,
            'message' => 'Summary Filtered successfully',
        ]);
    }
}
