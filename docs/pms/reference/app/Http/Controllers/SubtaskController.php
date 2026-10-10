<?php

namespace App\Http\Controllers;

use App\Events\GlobalUserEvent;
use App\Models\SubTaskPendingSummary;
use App\Models\Subtasks;
use App\Models\Summary;
use App\Models\Tasks;
use App\Models\User;
use App\Services\GlobalUserEventService;
use App\Services\IssueNotificationService;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class SubtaskController extends Controller
{
    use AuthorizesRequests;

    protected $globalUserEventService;

    public function __construct(GlobalUserEventService $globalUserEventService, protected IssueNotificationService $issueNotifications)
    {

        $this->globalUserEventService = $globalUserEventService;
    }

    public function index()
    {
        //
    }

    public function create()
    {
        //
    }

    public function store(Request $request)
    {
        $this->authorize('sub-task-add');
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'task_id' => ['required', 'exists:tasks,id'],
            'due_date' => ['nullable', 'date'],
            'priority' => ['nullable', 'in:0,1,2,3'],
            'status' => ['nullable', 'in:pending,in_progress,completed'],
        ]);

        $subtask = Subtasks::create([
            'title' => $validated['title'],
            'task_id' => $validated['task_id'],
            'due_date' => $validated['due_date'] ?? null,
            'priority' => $validated['priority'] ?? 1, // Default to Normal
            'status' => $validated['status'] ?? 'pending',
            'created_by' => Auth::id(),
        ]);
        $user = Auth::user();
        // Assign the task to the user if they are not an admin, project manager, or team leader
        if (! $user->hasRole('admin') && ! $user->hasRole('project_manager') && ! $user->hasRole('team_leader')) {
            $subtask->users()->attach($user->id, [
                'assigned_by' => $user->id,
            ]);
        }

        $mainTask = Tasks::findOrFail($subtask->task_id);
        $this->issueNotifications->send($mainTask, 'updated', "Subtask \"{$subtask->title}\" was created.");
        $project_id = $mainTask->project_id;
        $users = User::whereHas('projects', function ($query) use ($project_id) {
            $query->where('project_id', $project_id);
        })->get();

        if ($subtask) {
            $this->globalUserEventService->broadcastToUsers($users, $project_id, $mainTask->id, $subtask->id, $subtask->status);

            return response()->json([
                'success' => true,
                'message' => 'Subtask created successfully',
                'subtask' => $subtask,
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to create subtask',
        ]);
    }

    public function show(string $id)
    {
        $subtask = Subtasks::with('users')->findOrFail($id);

        return response()->json([
            'subtask' => $subtask,
        ]);
    }

    public function edit(string $id)
    {
        //
    }

    public function update(Request $request, string $id)
    {
        $this->authorize('sub-task-edit');
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
        ]);

        $subtask = Subtasks::findorFail($id);
        $currentUser = Auth::user();
        $roleKeys = ['admin', 'project_manager', 'team_leader'];
        $currentUserRole = $currentUser->roles()->whereIn('role_key', $roleKeys)->exists();

        if ($currentUser->id != $subtask->created_by && ! $currentUserRole) {
            return response()->json(
                [
                    'success' => false,
                    'message' => 'You are not allowed to update this subtask',
                ],
                Response::HTTP_FORBIDDEN,
            );
        }
        $subtask->update([
            'title' => $validated['title'],
        ]);
        $users = User::where('status', 1)->get();
        $mainTask = Tasks::findOrFail($subtask->task_id);
        $this->issueNotifications->send($mainTask, 'updated', "Subtask \"{$subtask->title}\" was updated.");
        $project_id = $mainTask->project_id;
        $users = User::whereHas('projects', function ($query) use ($project_id) {
            $query->where('project_id', $project_id);
        })->get();
        if ($subtask) {
            $this->globalUserEventService->broadcastToUsers($users, $project_id, $mainTask->id, $subtask->id, $subtask->status);

            return response()->json([
                'success' => true,
                'message' => 'Subtask update successfully',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to update subtask',
        ]);
    }

    public function destroy(string $id)
    {
        $this->authorize('sub-task-trash');
        $subtask = Subtasks::findOrFail($id);
        $mainTask = Tasks::findOrFail($subtask->task_id);
        $subtaskTitle = $subtask->title;
        $subtask->delete();
        $this->issueNotifications->send($mainTask, 'updated', "Subtask \"{$subtaskTitle}\" was deleted.");
        if ($subtask) {
            return response()->json(['status' => true, 'message' => 'Subtask deleted Successfully!'], Response::HTTP_OK);
        } else {
            return response()->json(['status' => false, 'message' => 'Subtask Failed to Delete!'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function StatusUpdate(Request $request)
    {
        $this->authorize('sub-task-status');

        $validated = $request->validate([
            'sub_task_id' => ['required', 'exists:subtasks,id'],
            'status' => ['required', 'in:pending,in_progress,completed'],
        ]);

        $subtask = Subtasks::findOrFail($validated['sub_task_id']);

        // validate user to assigend task
        if (auth()->user()->hasRole('admin') || auth()->user()->hasRole('project_manager') || auth()->user()->hasRole('team_leader')) {
            $isAssigned = true;
        } else {
            if (! $subtask->users()->where('user_id', auth()->user()->id)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not Assigned to this task',
                ]);
            }
        }

        // Define allowed transitions
        $validTransitions = [
            'pending' => ['in_progress'],
            'in_progress' => ['completed'],
            'completed' => [],
        ];

        // Prevent invalid status changes
        if (! isset($validTransitions[$subtask->status]) || ! in_array($validated['status'], $validTransitions[$subtask->status])) {
            return response()->json([
                'success' => false,
                'message' => "Invalid status transition from '{$subtask->status}' to '{$validated['status']}'.",
            ], 400);
        }

        // Handle time tracking
        $start_time = $subtask->start_time;
        $end_time = null;
        $invest_time = null;

        if ($subtask->status == 'pending' && $validated['status'] == 'in_progress') {
            $start_time = Carbon::now();
        }

        if ($subtask->status == 'in_progress' && $validated['status'] == 'completed') {
            $end_time = Carbon::now();
            if ($subtask->start_time) {
                $duration = $end_time->diff($subtask->start_time);
                $invest_time = sprintf('%d days, %d hours, %d minutes', $duration->d, $duration->h, $duration->i);
            }
        }

        $mainTask = Tasks::findOrFail($subtask->task_id);
        $project_id = $mainTask->project_id;
        $user = Auth::user();
        $currentDate = Carbon::now()->toDateString();

        if ($validated['status'] == 'completed') {
            if ($mainTask->status == 'completed') {
                $summary = Summary::where('user_id', $user->id)
                    ->where('project_id', $mainTask->project_id)
                    ->whereDate('date', $currentDate)
                    ->first();

                $existingSummary = $summary ? json_decode($summary->task, true) : [];

                // Find the task entry and add subtask under it with invest_time
                foreach ($existingSummary as &$entry) {
                    if ($entry['task'] === $mainTask->title) {
                        $entry['subtasks'][] = [
                            'title' => $subtask->title,
                            'invest_time' => $invest_time,
                        ];
                        break;
                    }
                }

                if ($summary) {
                    $summary->update(['task' => json_encode($existingSummary)]);
                } else {
                    Summary::create([
                        'user_id' => $user->id,
                        'project_id' => $mainTask->project_id,
                        'task' => json_encode($existingSummary),
                        'date' => $currentDate,
                    ]);
                }
            } else {
                SubTaskPendingSummary::create([
                    'user_id' => $user->id,
                    'task_id' => $subtask->task_id,
                    'sub_task_id' => $subtask->id,
                    'sub_task_title' => $subtask->title,
                    'invest_time' => $invest_time,
                    'date' => Carbon::now(),
                ]);
            }
        }

        // Update subtask status
        $subtask->update([
            'status' => $validated['status'],
            'start_time' => $start_time,
            'end_time' => $end_time,
            'invest_time' => $invest_time,
            'completed_by' => $user->id,
        ]);
        $this->issueNotifications->send($mainTask, 'updated', "Subtask \"{$subtask->title}\" status changed to {$validated['status']}.");

        // Broadcast Status Update
        $users = User::whereHas('projects', function ($query) use ($project_id) {
            $query->where('project_id', $project_id);
        })->get();
        $this->globalUserEventService->broadcastToUsers($users, $project_id, $mainTask->id, $subtask->id, $subtask->status);

        return response()->json([
            'success' => true,
            'message' => 'SubTask status updated successfully',
            'invest_time' => $invest_time,
        ]);
    }

    public function priorityUpdate(Request $request)
    {
        $this->authorize('sub-task-priority');
        $validated = $request->validate([
            'sub_task_id' => ['required', 'exists:subtasks,id'],
            'priority' => ['required'],
        ]);

        $task = Subtasks::findOrFail($validated['sub_task_id']);
        $task->update([
            'priority' => $validated['priority'],
        ]);

        $getTask = Tasks::findorFail($task->task_id);
        $this->issueNotifications->send($getTask, 'updated', "Subtask \"{$task->title}\" priority was updated.");
        $project_id = $getTask->project_id;
        $users = User::whereHas('projects', function ($query) use ($project_id) {
            $query->where('project_id', $project_id);
        })->get();
        $this->globalUserEventService->broadcastToUsers($users, $project_id, $getTask->id, $task->id, $task->status);

        if ($task) {
            return response()->json([
                'success' => true,
                'message' => 'SubTask priority updated successfully',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to update subtask priority',
        ]);
    }

    public function subTaskDueDateUpdate(Request $request)
    {
        $this->authorize('sub-task-due-date');
        $validated = $request->validate([
            'sub_task_id' => ['required', 'exists:subtasks,id'],
            'due_date' => ['required'],
        ]);

        $task = Subtasks::findOrFail($validated['sub_task_id']);
        $task->update([
            'due_date' => $validated['due_date'],
        ]);
        $mainTask = Tasks::findOrFail($task->task_id);
        $this->issueNotifications->send($mainTask, 'updated', "Subtask \"{$task->title}\" due date was updated.");
        $project_id = $mainTask->project_id;
        $users = User::whereHas('projects', function ($query) use ($project_id) {
            $query->where('project_id', $project_id);
        })->get();
        if ($task) {
            $this->globalUserEventService->broadcastToUsers($users, $project_id, $mainTask->id, $task->id, $task->status);

            return response()->json([
                'success' => true,
                'message' => 'Task Due Date Updated Succefully',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to update task Due Date',
        ]);
    }

    public function subTaskApprovalUpdate(Request $request)
    {
        // dd($request->all());
        // $this->authorize('task-approval');
        $validated = $request->validate([
            'task_id' => ['required', 'exists:tasks,id'],
            'subtask_id' => ['required', 'exists:subtasks,id'],
            'approval' => ['required'],
        ]);
        $roleKeys = ['admin', 'project_manager', 'team_leader'];
        $users = User::whereHas('roles', function ($query) use ($roleKeys) {
            $query->whereIn('role_key', $roleKeys);
        })->get();
        match ($validated['approval']) {
            'approved' => $this->approveTask($request, $validated, $users),
            'rejected' => $this->rejectTask($request, $validated, $users),
        };
    }

    private function rejectTask(Request $request, $validated, $users)
    {
        $subtask = Subtasks::with('task.users')->findOrFail($validated['subtask_id']);
        $mainTask = Tasks::findOrFail($subtask->task_id);
        $subtask->update([
            'approval' => $validated['approval'],
            'status' => 'pending',
            'approval_note' => 'Task rejected by '.Auth::user()->name,
        ]);
        if ($subtask) {
            foreach ($users as $user) {
                broadcast(
                    new GlobalUserEvent([
                        'project_id' => $mainTask->project_id,
                        'task_id' => $subtask->task_id,
                        'status' => $validated['approval'],
                        'user_id' => $user->id,
                    ])
                );
            }
            foreach ($subtask->task->users as $user) {
                $userNotifier = User::findOrFail($user->id);
                $userNotifier->notify(new \App\Notifications\TaskApprovalNotification([
                    'task' => $subtask,
                    'user_id' => $userNotifier->id,
                    'approval' => $validated['approval'],
                    'message' => 'Task rejected by '.Auth::user()->name,
                    'project_id' => $mainTask->project_id,
                    'status' => $validated['approval'],
                ]));
            }

            return response()->json([
                'success' => true,
                'message' => 'Task rejected successfully',
            ], Response::HTTP_CREATED);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to reject task',
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    private function approveTask(Request $request, $validated, $users)
    {
        $subtask = Subtasks::with('task.users')->findOrFail($validated['subtask_id']);
        $mainTask = Tasks::findOrFail($subtask->task_id);
        $subtask->update([
            'approval' => $validated['approval'],
            'approval_note' => 'Task approved by '.Auth::user()->name,
        ]);
        $project_id = $mainTask->project_id;
        if ($subtask) {
            $this->globalUserEventService->broadcastToUsers($users, $project_id, $mainTask->id, $subtask->id, $subtask->status);
            foreach ($subtask->task->users as $user) {
                $userNotifier = User::findOrFail($user->id);
                $userNotifier->notify(new \App\Notifications\TaskApprovalNotification([
                    'task' => $subtask,
                    'user_id' => $userNotifier->id,
                    'approval' => $validated['approval'],
                    'message' => 'Task rejected by '.Auth::user()->name,
                    'project_id' => $mainTask->project_id,
                    'status' => $validated['approval'],
                ]));
            }

            return response()->json([
                'success' => true,
                'message' => 'Task approved successfully',
            ], Response::HTTP_CREATED);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to approve task',
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    public function SubTaskDescription(Request $request)
    {
        $this->authorize('description-update');

        $validated = $request->validate([
            'task_id' => ['required', 'exists:subtasks,id'],
            'description' => ['nullable', 'string'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['image', 'mimes:jpg,jpeg,png,webp,gif', 'max:10240'],
        ]);
        $subtask = Subtasks::findorFail($request->task_id);
        $subtask->description = $validated['description'] ?? $subtask->description;

        $attachmentPaths = [];

        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $fileName = time().'_'.uniqid().'.'.$file->getClientOriginalExtension();
                $filePath = $file->storeAs('task_attachment', $fileName, 'public');

                // Generate the full URL
                $fullUrl = asset('storage/'.$filePath);

                $attachmentPaths[] = $fullUrl;
            }
        }

        // Merge existing attachments if needed
        if (! empty($attachmentPaths)) {
            $existingAttachments = json_decode($subtask->attachment, true) ?? [];
            $subtask->images = json_encode(array_merge($existingAttachments, $attachmentPaths), JSON_UNESCAPED_SLASHES);
        }
        $subtask->save();
        $task = Tasks::findOrFail($subtask->task_id);
        $this->issueNotifications->send($task, 'updated', "Subtask \"{$subtask->title}\" details were updated.");
        $project_id = $task->project_id;
        $users = User::whereHas('projects', function ($query) use ($project_id) {
            $query->where('project_id', $project_id);
        })->get();
        $this->globalUserEventService->broadcastToUsers($users, $project_id, $task->id, $subtask->id, $subtask->status);

        return response()->json([
            'subtask' => $subtask,
            'success' => true,
            'message' => 'Subtask updated successfully',
        ]);
    }

    public function startTimer(Request $request)
    {
        $request->validate(['sub_task_id' => 'required|exists:subtasks,id']);
        $subtask = Subtasks::findOrFail($request->sub_task_id);
        $userId = auth()->id();

        // Check if the user has another running task or subtask
        $runningTask = Tasks::whereHas('users', fn ($q) => $q->where('user_id', $userId))
            ->whereNotNull('start_time')
            ->whereNull('end_time')
            ->first();

        $runningSubtask = Subtasks::where('id', '!=', $subtask->id)
            ->whereHas('task.users', fn ($q) => $q->where('user_id', $userId))
            ->whereNotNull('start_time')
            ->whereNull('end_time')
            ->first();

        if ($runningTask || $runningSubtask) {
            return response()->json([
                'success' => false,
                'message' => 'Another task or subtask is already running. Please pause or stop it first.',
            ], 400);
        }

        if (! $subtask->start_time) {
            $subtask->start_time = now();
            $subtask->end_time = null;
            $subtask->invest_time = '0 days, 0 hours, 0 minutes';
            $subtask->status = 'in_progress';
        } elseif ($subtask->end_time && $subtask->status !== 'completed') {
            $subtask->end_time = null;
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Timer already running or subtask completed',
            ], 400);
        }

        $subtask->save();

        return response()->json([
            'success' => true,
            'message' => 'Timer started',
            'start_time' => $subtask->start_time,
            'invest_time' => $subtask->invest_time,
        ]);
    }

    public function pauseTimer(Request $request)
    {
        $subtask = Subtasks::findOrFail($request->sub_task_id);
        if ($subtask->start_time && ! $subtask->end_time) {
            $subtask->end_time = now();
            $subtask->invest_time += $subtask->end_time->diffInSeconds($subtask->start_time);
            $subtask->save();

            return response()->json(['success' => true, 'message' => 'Subtask timer paused']);
        }

        return response()->json(['success' => false, 'message' => 'Subtask timer not running']);
    }

    public function stopTimer(Request $request)
    {
        $request->validate(['sub_task_id' => 'required|exists:subtasks,id']);
        $subtask = Subtasks::findOrFail($request->sub_task_id);

        if ($subtask->start_time && $subtask->status !== 'completed') {
            if (! $subtask->end_time) {
                // Timer is running, calculate invest_time
                $subtask->end_time = now();
                $start = Carbon::parse($subtask->start_time);
                $end = Carbon::parse($subtask->end_time);
                $diff = $end->diff($start);

                $invest_time = sprintf(
                    '%d days, %d hours, %d minutes',
                    $diff->days,
                    $diff->h,
                    $diff->i
                );
                $subtask->invest_time = $invest_time;
            }
            // If end_time is set (paused), use existing invest_time
            $subtask->status = 'completed';
            $subtask->save();

            return response()->json([
                'success' => true,
                'message' => 'Timer stopped and subtask completed',
                'invest_time' => $subtask->invest_time,
                'status' => $subtask->status,
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Timer not running or subtask already completed',
        ], 400);
    }
}
