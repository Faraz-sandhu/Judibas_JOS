<?php

namespace App\Http\Controllers;

use App\Models\Projects;
use App\Models\SubTaskPendingSummary;
use App\Models\Subtasks;
use App\Models\Summary;
use App\Models\TaskEstimateHistory;
use App\Models\Tasks;
use App\Models\TimerLog;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowColumn;
use App\Services\GlobalUserEventService;
use App\Services\IssueNotificationService;
use Carbon\Carbon;
use Carbon\CarbonInterval;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TaskController extends Controller
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
        $this->authorize('task-add');

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:20000'],
            'project_id' => ['required', 'exists:projects,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'workflow_id' => ['nullable', 'exists:workflows,id'],
            'workflow_column_id' => ['nullable', 'exists:workflow_columns,id'],
            'sprint_id' => [
                'nullable',
                Rule::exists('sprints', 'id')->where(fn ($query) => $query->where('project_id', $request->project_id)),
            ],
            'status' => ['required', 'in:pending,in_progress,in_review,completed'],
            'priority' => ['nullable', 'in:0,1,2,3'],
            'due_date' => ['nullable', 'date'],
            'assignee_id' => ['nullable', 'exists:users,id'],
            'assignee_ids' => ['nullable', 'array'],
            'assignee_ids.*' => ['integer', 'distinct', 'exists:users,id'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', 'max:10240', 'mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,csv,txt,jpg,jpeg,png,webp,gif,zip'],
        ]);

        $project = Projects::findOrFail($validated['project_id']);
        $user = Auth::user();
        abort_unless(
            $user->hasRole('admin') || $project->users()->where('users.id', $user->id)->exists(),
            403,
            'You do not have access to this project.'
        );

        $assigneeIds = collect($validated['assignee_ids'] ?? (($validated['assignee_id'] ?? null) ? [$validated['assignee_id']] : []))->map(fn ($id) => (int) $id)->unique()->values();
        if ($assigneeIds->isNotEmpty()) {
            abort_unless($this->eligibleAssignees($user)->whereIn('id', $assigneeIds)->count() === $assigneeIds->count(), 422, 'One or more selected employees cannot be assigned by you.');
        }

        $start_time = null;
        [$workflow, $workflowColumn] = $this->resolveWorkflowSelection(
            $project,
            $validated['workflow_id'] ?? null,
            $validated['workflow_column_id'] ?? null
        );
        if ($workflowColumn) {
            $validated['status'] = $this->legacyStatusForColumn($workflowColumn);
        }
        $currentDate = Carbon::now()->toDateString();

        if ($validated['status'] == 'in_progress') {
            $start_time = Carbon::now();
        } elseif ($validated['status'] == 'completed') {
            $summary = Summary::where('user_id', $user->id)
                ->where('project_id', $validated['project_id'])
                ->whereDate('date', $currentDate)
                ->first();

            if ($summary) {
                $existingTasks = json_decode($summary->task, true);

                // Ensure existingTasks is always an array of objects
                if (! is_array($existingTasks)) {
                    $existingTasks = [];
                }

                // Check if title is a plain string or already an object
                if (is_string($validated['title'])) {
                    $existingTasks[] = [
                        'task' => $validated['title'],
                        'subtasks' => [],
                    ];
                }

                $summary->update(['task' => json_encode($existingTasks)]);
            } else {
                Summary::create([
                    'user_id' => $user->id,
                    'project_id' => $validated['project_id'],
                    'task' => json_encode([
                        [
                            'task' => $validated['title'],
                            'subtasks' => [],
                        ],
                    ]),
                    'date' => $currentDate,
                ]);
            }
        }

        // Create the task record
        $task = Tasks::create([
            'title' => $validated['title'],
            'description' => $this->sanitizeDescription($validated['description'] ?? null),
            'project_id' => $validated['project_id'],
            'department_id' => $validated['department_id'] ?? $workflow?->department_id,
            'workflow_id' => $workflow?->id,
            'workflow_column_id' => $workflowColumn?->id,
            'sprint_id' => $validated['sprint_id'] ?? null,
            'status' => $validated['status'],
            'due_date' => $validated['due_date'] ?? null,
            'priority' => $validated['priority'] ?? 1,
            'start_time' => $start_time,
            'created_by' => $user->id,
        ]);

        if ($assigneeIds->isNotEmpty()) {
            $assignmentPayload = $assigneeIds->mapWithKeys(fn ($id) => [$id => ['assigned_by' => $user->id]])->all();
            $project->users()->syncWithoutDetaching($assignmentPayload);
            $task->users()->sync($assignmentPayload);
        }

        foreach ($request->file('attachments', []) as $file) {
            $path = $file->store("tasks/{$task->id}", 'public');
            $task->attachments()->create([
                'user_id' => $user->id,
                'original_name' => $file->getClientOriginalName(),
                'path' => $path,
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
            ]);
        }

        if ($assigneeIds->isNotEmpty()) {
            User::whereIn('id', $assigneeIds)->get()->each(fn ($assignee) => $this->issueNotifications->assigned($task, $assignee));
        } else {
            $this->issueNotifications->send($task, 'created');
        }

        // Assign the task to the user if they are not an admin, project manager, or team leader
        if (! $user->hasRole('admin') && ! $user->hasRole('project_manager') && ! $user->hasRole('team_leader')) {
            $task->users()->syncWithoutDetaching([$user->id => [
                'assigned_by' => $user->id,
            ]]);
        }

        // Broadcast event to notify users
        $project_id = $task->project_id;
        $users = User::whereHas('projects', function ($query) use ($project_id) {
            $query->where('project_id', $project_id);
        })->get();
        if ($task) {
            $this->globalUserEventService->broadcastToUsers($users, $task->project_id, $task->id, '', $task->status);

            return response()->json([
                'success' => true,
                'message' => 'Task created successfully',
                'task' => [
                    'id' => $task->id,
                    'title' => $task->title,
                    'project_id' => $task->project_id,
                    'status' => $task->status,
                    'due_date' => $task->due_date,
                    'priority' => $task->priority,
                    'invest_time' => $task->invest_time,
                ],
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to create task',
        ]);
    }

    public function show(string $id)
    {
        $task = Tasks::with([
            'project:id,name',
            'sprint:id,name,project_id,status',
            'workflow:id,name,department_id',
            'workflowColumn:id,workflow_id,name,color,is_initial,is_completed',
            'users:id,name,profile_img',
            'subtasks.users:id,name,profile_img',
            'subtasks.timerLogs:id,task_id,subtask_id,user_id,start_time,end_time',
            'timerLogs.user:id,name,profile_img',
            'comments.user:id,name,profile_img',
            'attachments.user:id,name',
            'estimateHistories.user:id,name',
            'incomingHandoff.sourceTask:id,title,project_id,department_id',
            'incomingHandoff.sourceDepartment:id,dept_name',
            'incomingHandoff.destinationDepartment:id,dept_name',
            'incomingHandoff.sourceProject:id,name',
            'incomingHandoff.actor:id,name',
            'outgoingHandoffs.destinationTask:id,title,project_id,department_id',
            'outgoingHandoffs.sourceDepartment:id,dept_name',
            'outgoingHandoffs.destinationDepartment:id,dept_name',
            'outgoingHandoffs.destinationProject:id,name',
            'outgoingHandoffs.actor:id,name',
        ])->findOrFail($id);
        abort_unless($this->canAccessIssue(Auth::user(), $task), 403, 'You do not have access to this issue.');
        if ($task) {
            // Get timer logs for the task
            $timerLogs = $task->timerLogs;
            // Calculate invest_time
            $invest_time = $this->calculateInvestTime($timerLogs);
            // Optionally, update the task's invest_time
            $task->invest_time = $invest_time;
            $timeByUser = $timerLogs->groupBy('user_id')->map(function ($logs) {
                $seconds = $logs->sum(fn ($log) => $log->start_time
                    ? $log->start_time->diffInSeconds($log->end_time ?? now())
                    : 0);

                return [
                    'user_id' => $logs->first()->user_id,
                    'name' => $logs->first()->user?->name ?? 'Deleted user',
                    'profile_img' => $logs->first()->user?->profile_img,
                    'seconds' => $seconds,
                    'formatted' => $this->formatTrackedTime($seconds),
                ];
            })->values();
            $totalSeconds = $timeByUser->sum('seconds');
            $task->setAttribute('time_tracking', [
                'total_seconds' => $totalSeconds,
                'formatted' => $this->formatTrackedTime($totalSeconds),
                'by_user' => $timeByUser,
            ]);
            $task->setAttribute('directly_assigned_to_me', $task->users->contains('id', Auth::id()));
            $task->setAttribute('own_running_timer', $timerLogs->contains(fn ($log) =>
                (int) $log->user_id === (int) Auth::id()
                && is_null($log->subtask_id)
                && is_null($log->end_time)
            ));
        }

        return response()->json([
            'task' => $task,
        ]);
    }

    public function boardUpdate(Request $request, Tasks $task)
    {
        $user = Auth::user();
        abort_unless($this->canAccessIssue($user, $task), 403, 'You do not have access to this issue.');

        $editingFields = collect($request->keys())->intersect(['title', 'description', 'priority', 'due_date', 'sprint_id']);
        if ($editingFields->isNotEmpty()) {
            $this->authorize('task-edit');
        }
        if ($request->hasAny(['assignee_id', 'assignee_ids'])) {
            $this->authorize('task-assign');
        }
        if ($request->has('status') || $request->has('workflow_column_id')) {
            $this->authorize('task-status');
        }
        if ($request->has('original_estimate_minutes')) {
            $manager = $user->hasRole('admin') || $user->hasRole('project_manager');
            $developer = $user->hasRole('developer')
                && $task->users()->where('users.id', $user->id)->exists()
                && ! $task->developer_estimate_set_at;
            abort_unless($manager || $developer, 403, 'Only management or an assigned developer making their one estimate revision may update this estimate.');
        }

        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:20000'],
            'status' => ['sometimes', Rule::in(['pending', 'in_progress', 'in_review', 'completed'])],
            'priority' => ['sometimes', 'nullable', 'integer', 'between:0,3'],
            'due_date' => ['sometimes', 'nullable', 'date'],
            'sprint_id' => [
                'sometimes',
                'nullable',
                Rule::exists('sprints', 'id')->where(fn ($query) => $query->where('project_id', $task->project_id)),
            ],
            'position' => ['sometimes', 'integer', 'min:0'],
            'assignee_id' => ['sometimes', 'nullable', 'exists:users,id'],
            'assignee_ids' => ['sometimes', 'array'],
            'assignee_ids.*' => ['integer', 'distinct', 'exists:users,id'],
            'assignee_action' => ['sometimes', Rule::in(['add', 'remove', 'clear', 'replace'])],
            'department_id' => ['sometimes', 'nullable', 'exists:departments,id'],
            'workflow_id' => ['sometimes', 'required', 'exists:workflows,id'],
            'workflow_column_id' => ['sometimes', 'required', 'exists:workflow_columns,id'],
            'original_estimate_minutes' => ['sometimes', 'required', 'integer', 'min:1', 'max:525600'],
        ]);

        $assigneeId = $validated['assignee_id'] ?? null;
        $assigneeIds = $request->has('assignee_ids')
            ? collect($validated['assignee_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->values()
            : null;
        $assigneeAction = $validated['assignee_action'] ?? 'replace';
        unset($validated['assignee_id'], $validated['assignee_ids'], $validated['assignee_action']);
        if (array_key_exists('description', $validated)) {
            $validated['description'] = $this->sanitizeDescription($validated['description']);
        }

        if ($assigneeId) {
            abort_unless($this->eligibleAssignees($user)->whereKey($assigneeId)->exists(), 422, 'The selected employee cannot be assigned by you.');
        }
        if ($assigneeIds && $assigneeIds->isNotEmpty()) {
            abort_unless($this->eligibleAssignees($user)->whereIn('id', $assigneeIds)->count() === $assigneeIds->count(), 422, 'One or more selected employees cannot be assigned by you.');
        }

        if (($validated['status'] ?? null) === 'completed') {
            $validated['completed_by'] = $user->id;
        } elseif (isset($validated['status'])) {
            $validated['completed_by'] = null;
        }

        $oldStatus = $task->status;
        $oldAssigneeIds = $task->users()->pluck('users.id')->map(fn ($id) => (int) $id);
        $removedAssigneeIds = $assigneeIds !== null ? $oldAssigneeIds->diff($assigneeIds)->values()->all() : match ($assigneeAction) {
            'remove' => $assigneeId ? $oldAssigneeIds->intersect([(int) $assigneeId])->values()->all() : [],
            'clear' => $oldAssigneeIds->values()->all(),
            'replace' => $request->has('assignee_id') ? $oldAssigneeIds->diff($assigneeId ? [(int) $assigneeId] : [])->values()->all() : [],
            default => [],
        };
        $oldEstimate = $task->original_estimate_minutes;
        if ($request->has('original_estimate_minutes') && $user->hasRole('developer') && ! $user->hasRole('admin')) {
            $validated['developer_estimate_set_at'] = now();
        }
        if ($request->has('workflow_column_id')) {
            $column = WorkflowColumn::whereKey($validated['workflow_column_id'])
                ->where('workflow_id', $validated['workflow_id'] ?? $task->workflow_id)
                ->with('workflow')
                ->firstOrFail();
            abort_unless(
                $task->project->workflows()->whereKey($column->workflow_id)->exists(),
                422,
                'This workflow is not enabled for the project.'
            );
            abort_if(
                $task->workflow_id && (int) $task->workflow_id !== (int) $column->workflow_id,
                422,
                'Move tasks between workflows by creating a related department task.'
            );
            if ($column->workflow->transition_mode === 'adjacent' && $task->workflowColumn) {
                abort_if(
                    abs((int) $column->position - (int) $task->workflowColumn->position) > 1,
                    422,
                    'This workflow only allows movement to the previous or next column.'
                );
            }
            $validated['workflow_id'] = $column->workflow_id;
            $validated['department_id'] = $column->workflow->department_id;
            $validated['status'] = $this->legacyStatusForColumn($column);
            $validated['completed_by'] = $column->is_completed ? $user->id : null;
        }
        DB::transaction(function () use ($task, $validated, $assigneeId, $assigneeIds, $assigneeAction, $request, $user, $oldEstimate, $removedAssigneeIds) {
            $task->update($validated);

            if ($request->has('original_estimate_minutes')) {
                TaskEstimateHistory::create([
                    'task_id' => $task->id,
                    'user_id' => $user->id,
                    'old_minutes' => $oldEstimate,
                    'new_minutes' => $validated['original_estimate_minutes'],
                ]);
            }

            if ($request->has('assignee_id') || $request->has('assignee_ids')) {
                $this->closeRemovedAssigneeTimers($task, $removedAssigneeIds);

                if ($assigneeIds !== null) {
                    $payload = $assigneeIds->mapWithKeys(fn ($id) => [$id => ['assigned_by' => $user->id]])->all();
                    $task->project->users()->syncWithoutDetaching($payload);
                    $task->users()->sync($payload);
                } elseif ($assigneeAction === 'clear' || ! $assigneeId) {
                    $task->users()->detach();
                } elseif ($assigneeAction === 'remove') {
                    $task->users()->detach($assigneeId);
                } elseif ($assigneeAction === 'add') {
                    $task->project->users()->syncWithoutDetaching([$assigneeId => ['assigned_by' => $user->id]]);
                    $task->users()->syncWithoutDetaching([$assigneeId => ['assigned_by' => $user->id]]);
                } else {
                    $task->project->users()->syncWithoutDetaching([$assigneeId => ['assigned_by' => $user->id]]);
                    $task->users()->sync([$assigneeId => ['assigned_by' => $user->id]]);
                }
            }
        });

        $detail = isset($validated['status']) && $validated['status'] !== $oldStatus
            ? 'Status changed from '.str_replace('_', ' ', $oldStatus).' to '.str_replace('_', ' ', $validated['status']).'.'
            : null;
        if ($request->has('original_estimate_minutes') && $oldEstimate !== $validated['original_estimate_minutes']) {
            $detail = 'Original estimate updated to '.$this->formatTrackedTime($validated['original_estimate_minutes'] * 60).'.';
        }
        $freshTask = $task->fresh();
        if ($removedAssigneeIds) {
            $this->issueNotifications->unassigned($freshTask, $removedAssigneeIds);
        }
        if ($assigneeIds !== null) {
            User::whereIn('id', $assigneeIds->diff($oldAssigneeIds))->get()->each(fn ($assignee) => $this->issueNotifications->assigned($freshTask, $assignee));
        } elseif ($request->has('assignee_id') && $assigneeId && $assigneeAction !== 'remove' && ! $oldAssigneeIds->contains((int) $assigneeId)) {
            $this->issueNotifications->assigned($freshTask, User::findOrFail($assigneeId));
        } elseif (! $request->has('assignee_id') || $detail) {
            $this->issueNotifications->send($freshTask, 'updated', $detail);
        }

        return response()->json([
            'message' => 'Task updated successfully.',
            'task' => $task->fresh()->load(['users:id,name,profile_img', 'subtasks:id,task_id,status']),
        ]);
    }

    private function eligibleAssignees(User $actor): Builder
    {
        return User::query()
            ->where('status', 1)
            ->whereDoesntHave('roles', fn (Builder $query) => $query->where('role_key', 'admin'))
            ->when(
                $actor->hasRole('team_leader') && ! $actor->hasRole('admin') && ! $actor->hasRole('project_manager'),
                fn (Builder $query) => $query->whereHas(
                    'departments',
                    fn (Builder $departments) => $departments->whereIn('departments.id', $actor->departments->pluck('id'))
                )
            );
    }

    private function resolveWorkflowSelection(Projects $project, ?int $workflowId, ?int $columnId): array
    {
        $workflow = $workflowId
            ? $project->workflows()->whereKey($workflowId)->where('is_active', true)->firstOrFail()
            : $project->workflows()->where('is_active', true)->with('columns')->first()
                ?? Workflow::where('is_default', true)->where('is_active', true)->with('columns')->first();
        if (! $workflow) return [null, null];
        $project->workflows()->syncWithoutDetaching([$workflow->id]);
        $column = $columnId
            ? $workflow->columns()->whereKey($columnId)->firstOrFail()
            : $workflow->columns()->where('is_initial', true)->first() ?? $workflow->columns()->first();

        return [$workflow, $column];
    }

    private function legacyStatusForColumn(WorkflowColumn $column): string
    {
        if ($column->is_completed) return 'completed';
        if ($column->is_initial) return 'pending';
        return str_contains(strtolower($column->name), 'review') ? 'in_review' : 'in_progress';
    }

    private function calculateInvestTime($timerLogs)
    {
        $totalSeconds = $timerLogs->whereNotNull('end_time')->sum(function ($log) {
            $startTime = is_string($log->start_time) ? Carbon::parse($log->start_time) : $log->start_time;
            $endTime = is_string($log->end_time) ? Carbon::parse($log->end_time) : $log->end_time;

            return $endTime->diffInSeconds($startTime);
        });

        return CarbonInterval::seconds($totalSeconds)->cascade()->forHumans();
    }

    private function closeRemovedAssigneeTimers(Tasks $task, array $userIds, ?Subtasks $subtask = null): void
    {
        $userIds = collect($userIds)->filter()->unique()->values()->all();
        if ($userIds === []) {
            return;
        }

        $endedAt = now();
        TimerLog::query()
            ->where('task_id', $task->id)
            ->where('subtask_id', $subtask?->id)
            ->whereIn('user_id', $userIds)
            ->whereNull('end_time')
            ->update([
                'end_time' => $endedAt,
                'updated_at' => $endedAt,
            ]);

        $target = $subtask ?? $task;
        $timerLogs = TimerLog::query()
            ->where('task_id', $task->id)
            ->where('subtask_id', $subtask?->id)
            ->get();

        $target->invest_time = $this->calculateInvestTime($timerLogs);
        $target->save();
    }

    private function formatTrackedTime(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds.'s';
        }

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        return trim(($hours ? $hours.'h ' : '').($minutes ? $minutes.'m' : '')) ?: '< 1m';
    }

    private function canAccessIssue(User $user, Tasks $task): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        if ($task->department_id && $user->can('department-task-view-all')) {
            $isDepartmentMember = $user->departments()->where('departments.id', $task->department_id)->exists();
            if ($user->hasRole('project_manager') || $isDepartmentMember) return true;
        }

        if ($user->hasRole('project_manager') || $user->hasRole('team_leader')) {
            return $task->project->users()->where('users.id', $user->id)->exists();
        }

        return $task->users()->where('users.id', $user->id)->exists()
            || $task->subtasks()->whereHas('users', fn ($query) => $query->where('users.id', $user->id))->exists();
    }

    public function edit(string $id)
    {
        //
    }

    public function update(Request $request, string $id)
    {
        $this->authorize('task-edit');

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'project_id' => ['required', 'exists:projects,id'],
            'status' => ['required', 'in:pending,in_progress,in_review,completed'],
        ]);

        $task = Tasks::findOrFail($id);
        if ($task->status !== $validated['status']) {
            $this->authorize('task-status');
        }
        $roleKeys = ['admin', 'project_manager', 'team_leader'];
        $assignedUserPivot = $task->users()->first();
        $assignedBy = $assignedUserPivot?->pivot?->assigned_by;
        $currentUser = Auth::user();
        $currentUserRole = $currentUser->roles()->whereIn('role_key', $roleKeys)->exists();
        $assignedUser = User::find($assignedBy);
        $assignedUserRole = $assignedUser && $assignedUser->roles()->whereIn('role_key', $roleKeys)->exists();
        if ($assignedUserRole && ! $currentUserRole && $assignedBy !== $currentUser->id) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to update this task.',
            ], 403);
        }
        // Update the task
        $task->update([
            'title' => $validated['title'],
            'project_id' => $validated['project_id'],
            'status' => $validated['status'],
        ]);
        // Broadcast to all users
        $users = User::whereHas('projects', function ($query) use ($validated) {
            $query->where('project_id', $validated['project_id']);
        })->get();
        $this->globalUserEventService->broadcastToUsers($users, $validated['project_id'], $task->id, '', $task->status);

        return response()->json([
            'success' => true,
            'message' => 'Task updated successfully',
        ]);
    }

    public function destroy(string $id)
    {
        $this->authorize('task-trash');
        $task = Tasks::findOrFail($id);
        if ($task) {
            $this->issueNotifications->send($task, 'deleted');
            $task->load('attachments');
            foreach ($task->attachments as $attachment) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($attachment->path);
            }
            $subtasks = Subtasks::where('task_id', $task->id)->get();
            foreach ($subtasks as $subtask) {
                $subtask->delete();
            }
            DB::table('notifications')
                ->whereJsonContains('data->task->id', (int) $task->id)
                ->delete();
            $task->delete();

            return response()->json(['status' => true, 'message' => 'Task deleted Successfully!'], Response::HTTP_OK);
        }

        return response()->json(['status' => false, 'message' => 'Task Failed to Delete!'], Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    public function assignTaskToUser(Request $request)
    {
        $this->authorize('task-assign');

        $validated = $request->validate([
            'task_id' => ['required', 'exists:tasks,id'],
            'sub_task_id' => ['nullable', 'exists:subtasks,id'],
            'user_id' => ['required', 'exists:users,id'],
        ]);

        $user = Auth::user();
        $assignee = User::findOrFail($validated['user_id']);
        $task = Tasks::findOrFail($validated['task_id']);
        $project = $task->project;

        // Authorization check
        if (! $user->hasRole('admin') && ! $user->hasRole('project_manager') && ! $user->hasRole('team_leader')) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to assign tasks',
            ], Response::HTTP_FORBIDDEN);
        }

        // Check if assignee is in same department (for team leaders)
        $isSameDepartment = true;
        if ($user->hasRole('team_leader')) {
            $userDepartments = $user->departments->pluck('id');
            $assigneeDepartments = $assignee->departments->pluck('id');
            $isSameDepartment = $userDepartments->intersect($assigneeDepartments)->isNotEmpty();
        }

        // Handle invitation for team leaders assigning outside department
        if ($user->hasRole('team_leader') && ! $isSameDepartment) {
            try {
                $invitationService = app(\App\Services\InvitationService::class);
                $invitable = $validated['sub_task_id'] ? Subtasks::find($validated['sub_task_id']) : $task;

                $invitation = $invitationService->sendInvitation(
                    $user,
                    $assignee,
                    $invitable,
                    'task_assignee' // Role for task assignment
                );

                // Notify user
                // $assignee->notify(new \App\Notifications\InvitationNotification($invitation));

                return response()->json([
                    'success' => true,
                    'message' => 'Invitation sent to user in different department',
                    'data' => [
                        'invitation_id' => $invitation->id,
                        'requires_acceptance' => true,
                        'project_id' => $project->id ?? null,
                        'task_id' => $validated['task_id'] ?? null,
                        'sub_task_id' => $validated['sub_task_id'] ?? null,
                    ],
                ], Response::HTTP_CREATED);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        // Proceed with direct assignment if same department or admin/PM
        // Assign project to user if not already assigned
        if (! $project->users()->where('user_id', $validated['user_id'])->exists()) {
            $project->users()->attach($validated['user_id'], [
                'assigned_by' => $user->id,
            ]);
        }

        if ($validated['sub_task_id']) {
            // Assign to subtask
            $subtask = Subtasks::where('id', $validated['sub_task_id'])
                ->where('task_id', $validated['task_id'])
                ->firstOrFail();

            if ($subtask->users()->where('user_id', $validated['user_id'])->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'This subtask is already assigned to the selected user.',
                ], Response::HTTP_CONFLICT);
            }

            $subtask->users()->attach($validated['user_id'], [
                'assigned_by' => $user->id,
            ]);

            $this->issueNotifications->send(
                $task,
                'updated',
                "{$assignee->name} was assigned to subtask \"{$subtask->title}\".",
                [$assignee->id],
            );
            $users = User::whereHas('projects', function ($query) use ($project) {
                $query->where('project_id', $project->id);
            })->get();

            $this->globalUserEventService->broadcastToUsers($users, $project->id, $task->id, $subtask->id, $subtask->status);

            return response()->json([
                'success' => true,
                'data' => [
                    'project_id' => $project->id,
                    'status' => $subtask->status,
                    'task_id' => $task->id,
                    'sub_task_id' => $subtask->id,
                ],
                'message' => 'Subtask assigned successfully',
            ], Response::HTTP_CREATED);
        } else {
            // Assign to main task
            if ($task->users()->where('user_id', $validated['user_id'])->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'This task is already assigned to the selected user.',
                ], Response::HTTP_CONFLICT);
            }

            $task->users()->attach($validated['user_id'], [
                'assigned_by' => $user->id,
            ]);

            $this->issueNotifications->assigned($task, $assignee);
            $users = User::whereHas('projects', function ($query) use ($project) {
                $query->where('project_id', $project->id);
            })->get();

            $this->globalUserEventService->broadcastToUsers($users, $project->id, $task->id, '', $task->status);

            return response()->json([
                'success' => true,
                'data' => [
                    'project_id' => $project->id,
                    'status' => $task->status,
                    'task_id' => $task->id,
                ],
                'message' => 'Task assigned successfully',
            ], Response::HTTP_CREATED);
        }
    }

    public function StatusUpdate(Request $request)
    {
        $this->authorize('task-status');

        $validated = $request->validate([
            'task_id' => ['required', 'exists:tasks,id'],
            'status' => ['required', Rule::in(['pending', 'in_progress', 'in_review', 'completed'])],
        ]);

        $task = Tasks::findOrFail($validated['task_id']);

        // Validate user assing this task
        if (auth()->user()->hasRole('admin') || auth()->user()->hasRole('project_manager') || auth()->user()->hasRole('team_leader')) {
            $isAssigned = true;
        } else {
            if ($task->users()->where('user_id', auth()->user()->id)->doesntExist()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not Assigned to this task',
                ]);
            }
        }

        $validTransitions = [
            'pending' => ['in_progress'],
            'in_progress' => ['pending', 'in_review', 'completed'],
            'in_review' => ['in_progress', 'completed'],
            'completed' => ['in_review'],
        ];

        if (! isset($validTransitions[$task->status]) || ! in_array($validated['status'], $validTransitions[$task->status])) {
            return response()->json([
                'success' => false,
                'message' => "Invalid status transition from '{$task->status}' to '{$validated['status']}'.",
            ], 400);
        }

        $start_time = $task->start_time;
        $end_time = null;
        $invest_time = null;

        if ($task->status == 'pending' && $validated['status'] == 'in_progress') {
            // $start_time = Carbon::now();
            $start_time = null;
        }

        if ($task->status == 'in_progress' && $validated['status'] == 'completed') {
            // $end_time = Carbon::now();
            // if ($task->start_time) {
            //     $duration = $end_time->diff($task->start_time);
            //     $invest_time = sprintf("%d days, %d hours, %d minutes", $duration->d, $duration->h, $duration->i);
            // }
        }

        if ($validated['status'] == 'completed') {
            $user = Auth::user();
            $currentDate = Carbon::now()->toDateString();

            $pendingSummaries = SubTaskPendingSummary::where('user_id', $user->id)
                ->where('task_id', $task->id)
                ->get();

            $completedSubtasks = [];

            if ($pendingSummaries->isNotEmpty()) {
                foreach ($pendingSummaries as $pendingSubtask) {
                    $completedSubtasks[] = [
                        'title' => $pendingSubtask->sub_task_title,
                        'invest_time' => $pendingSubtask->invest_time,
                    ];
                }
            }

            $summary = Summary::where('user_id', $user->id)
                ->where('project_id', $task->project_id)
                ->whereDate('date', $currentDate)
                ->first();

            $existingSummary = $summary ? json_decode($summary->task, true) : [];

            $taskExists = false;
            foreach ($existingSummary as &$entry) {
                if ($entry['task'] === $task->title) {
                    $entry['subtasks'] = array_merge($entry['subtasks'], $completedSubtasks);
                    $taskExists = true;
                    break;
                }
            }

            if (! $taskExists) {
                $existingSummary[] = [
                    'task' => $task->title,
                    'invest_time' => $invest_time,
                    'subtasks' => $completedSubtasks,
                ];
            }

            if ($summary) {
                $summary->update(['task' => json_encode($existingSummary)]);
            } else {
                Summary::create([
                    'user_id' => $user->id,
                    'project_id' => $task->project_id,
                    'task' => json_encode($existingSummary),
                    'date' => $currentDate,
                ]);
            }

            SubTaskPendingSummary::where('user_id', $user->id)
                ->where('task_id', $task->id)
                ->delete();
        }

        $workflowColumnId = null;
        if ($task->workflow_id) {
            $columns = WorkflowColumn::where('workflow_id', $task->workflow_id)->orderBy('position')->get();
            $workflowColumnId = match ($validated['status']) {
                'pending' => $columns->firstWhere('is_initial', true)?->id,
                'completed' => $columns->firstWhere('is_completed', true)?->id,
                'in_review' => $columns->first(fn ($column) => str_contains(strtolower($column->name), 'review'))?->id,
                default => $columns->first(fn ($column) => ! $column->is_initial && ! $column->is_completed)?->id,
            };
        }
        $task->update([
            'status' => $validated['status'],
            'workflow_column_id' => $workflowColumnId ?? $task->workflow_column_id,
            'start_time' => $start_time,
            'end_time' => $end_time,
            'completed_by' => $validated['status'] === 'completed' ? Auth::id() : null,
            // 'invest_time' => $invest_time,
        ]);
        $users = User::whereHas('projects', function ($query) use ($task) {
            $query->where('project_id', $task->project_id);
        })->get();
        $this->globalUserEventService->broadcastToUsers($users, $task->project_id, $task->id, '', $task->status);
        $this->issueNotifications->send($task, 'updated', "Status changed to {$validated['status']}.");

        return response()->json([
            'success' => true,
            'message' => 'Task status updated successfully',
            'invest_time' => $invest_time,
        ]);
    }

    public function priorityUpdate(Request $request)
    {
        $this->authorize('task-priority');
        $validated = $request->validate([
            'task_id' => ['required', 'exists:tasks,id'],
            'priority' => ['required'],
        ]);

        $task = Tasks::findOrFail($validated['task_id']);
        $project_id = $task->project_id;
        $users = User::whereHas('projects', function ($query) use ($project_id) {
            $query->where('project_id', $project_id);
        })->get();
        $this->globalUserEventService->broadcastToUsers($users, $project_id, $task->id, '', $task->status);
        $task->update([
            'priority' => $validated['priority'],
        ]);
        $this->issueNotifications->send($task, 'updated', 'Priority was updated.');

        if ($task) {
            return response()->json([
                'success' => true,
                'message' => 'Task priority updated successfully',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to update task priority',
        ]);
    }

    public function taskDueDateUpdate(Request $request)
    {
        $this->authorize('task-due-date');
        $validated = $request->validate([
            'task_id' => ['required', 'exists:tasks,id'],
            'due_date' => ['required'],
        ]);

        $task = Tasks::findOrFail($validated['task_id']);
        $task->update([
            'due_date' => $validated['due_date'],
        ]);
        $this->issueNotifications->send($task, 'updated', 'Due date was updated.');

        $project_id = $task->project_id;

        $users = User::whereHas('projects', function ($query) use ($project_id) {
            $query->where('project_id', $project_id);
        })->get();
        $this->globalUserEventService->broadcastToUsers($users, $project_id, $task->id, '', $task->status);

        if ($task) {
            return response()->json([
                'success' => true,
                'message' => 'Task due date updated successfully',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to update task due date',
        ]);
    }

    public function taskApprovalUpdate(Request $request)
    {
        // $this->authorize('task-approval');
        $validated = $request->validate([
            'task_id' => ['required', 'exists:tasks,id'],
            'approval' => ['required', 'in:pending,approved,rejected'], // Ensure valid approval status
        ]);

        $roleKeys = ['admin', 'project_manager', 'team_leader'];
        $users = User::whereHas('roles', function ($query) use ($roleKeys) {
            $query->whereIn('role_key', $roleKeys);
        })->get();

        $task = Tasks::with('users')->findOrFail($validated['task_id']);

        // Update task based on approval status
        $task->update([
            'approval' => $validated['approval'],
            'approval_note' => "Task set to {$validated['approval']} by ".Auth::user()->name,
            'status' => $validated['approval'] === 'rejected' ? 'pending' : $task->status, // Only reset status for rejected
        ]);

        if ($task) {
            $project_id = $task->project_id;
            $projectUsers = User::whereHas('projects', function ($query) use ($project_id) {
                $query->where('project_id', $project_id);
            })->get();

            // Broadcast event
            $this->globalUserEventService->broadcastToUsers($projectUsers, $project_id, $task->id, '', $task->status);

            // Notify users
            foreach ($task->users as $user) {
                $userNotifier = User::findOrFail($user->id);
                $userNotifier->notify(new \App\Notifications\TaskApprovalNotification([
                    'task' => $task,
                    'user_id' => $userNotifier->id,
                    'approval' => $validated['approval'],
                    'message' => "Task set to {$validated['approval']} by ".Auth::user()->name,
                    'project_id' => $task->project_id,
                    'status' => $validated['approval'],
                ]));
            }

            return response()->json([
                'success' => true,
                'message' => "Task {$validated['approval']} successfully",
            ], Response::HTTP_OK);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to update task approval',
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    private function rejectTask(Request $request, $validated, $users)
    {
        $task = Tasks::with('users')->findOrFail($validated['task_id']);

        $task->update([
            'approval' => $validated['approval'],
            'status' => 'pending',
            'approval_note' => 'Task rejected by '.Auth::user()->name,
        ]);
        if ($task) {
            $project_id = $task->project_id;
            $users = User::whereHas('projects', function ($query) use ($project_id) {
                $query->where('project_id', $project_id);
            })->get();
            $this->globalUserEventService->broadcastToUsers($users, $project_id, $task->id, '', $task->status);
            foreach ($task->users as $user) {
                $userNotifier = User::findOrFail($user->id);
                $userNotifier->notify(new \App\Notifications\TaskApprovalNotification([
                    'task' => $task,
                    'user_id' => $userNotifier->id,
                    'approval' => $validated['approval'],
                    'message' => 'Task rejected by '.Auth::user()->name,
                    'project_id' => $task->project_id,
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
        $task = Tasks::findOrFail($validated['task_id']);

        $task->update([
            'approval' => $validated['approval'],
            'approval_note' => 'Task approved by '.Auth::user()->name,
        ]);
        if ($task) {
            $project_id = $task->project_id;
            $users = User::whereHas('projects', function ($query) use ($project_id) {
                $query->where('project_id', $project_id);
            })->get();
            $this->globalUserEventService->broadcastToUsers($users, $project_id, $task->id, '', $task->status);
            foreach ($task->users as $user) {
                $userNotifier = User::findOrFail($user->id);
                $userNotifier->notify(new \App\Notifications\TaskApprovalNotification([
                    'task' => $task,
                    'user_id' => $userNotifier->id,
                    'approval' => $validated['approval'],
                    'message' => 'Task rejected by '.Auth::user()->name,
                    'project_id' => $task->project_id,
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

    public function RemoveAssignUser(Request $request)
    {
        $this->authorize('task-assign');
        $request->validate([
            'task_id' => ['required', 'exists:tasks,id'],
            'subtask_id' => ['nullable', 'exists:subtasks,id'],
            'user_id' => ['required', 'exists:users,id'],
        ]);
        $task = Tasks::findOrFail($request->task_id);
        $removedUser = User::findOrFail($request->user_id);
        $detail = DB::transaction(function () use ($request, $task, $removedUser) {
            if ($request->subtask_id) {
                $subtask = Subtasks::where('task_id', $task->id)->findOrFail($request->subtask_id);
                $this->closeRemovedAssigneeTimers($task, [$removedUser->id], $subtask);
                $subtask->users()->detach($removedUser->id);

                return "{$removedUser->name} was removed from subtask \"{$subtask->title}\".";
            }

            $this->closeRemovedAssigneeTimers($task, [$removedUser->id]);
            $task->users()->detach($removedUser->id);

            return "{$removedUser->name} was removed from this issue.";
        });
        $task->unsetRelation('users');
        if ($request->subtask_id) {
            $this->issueNotifications->send($task, 'updated', $detail, [$removedUser->id]);
        } else {
            $this->issueNotifications->unassigned($task, [$removedUser->id]);
        }
        $task = Tasks::findOrFail($request->task_id);
        $project_id = $task->project_id;
        $users = User::whereHas('projects', function ($query) use ($project_id) {
            $query->where('project_id', $project_id);
        })->get();
        $this->globalUserEventService->broadcastToUsers($users, $project_id, $task->id, '', $task->status);

        return response()->json(['success' => true, 'message' => 'User removed from Task Successfully!'], Response::HTTP_OK);
    }

    public function TaskDescription(Request $request)
    {
        $this->authorize('description-update');

        $validated = $request->validate([
            'task_id' => ['required', 'exists:tasks,id'],
            'description' => ['nullable', 'string'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['image', 'mimes:jpg,jpeg,png,webp,gif', 'max:10240'],
        ]);

        $task = Tasks::findOrFail($request->task_id);
        $task->description = array_key_exists('description', $validated)
            ? $this->sanitizeDescription($validated['description'])
            : $task->description;

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

        // for local development storage path set

        // if ($request->hasFile('attachments')) {
        //     $attachmentPaths = [];
        //     foreach ($request->file('attachments') as $file) {
        //         $fileName = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
        //         $file->move(public_path('uploads/task_attachment'), $fileName);
        //         $fullUrl = asset('uploads/task_attachment/' . $fileName);
        //         $attachmentPaths[] = $fullUrl;
        //     }
        // }

        // Merge existing attachments if needed
        if (! empty($attachmentPaths)) {
            $existingAttachments = json_decode($task->attachment, true) ?? [];
            $task->images = json_encode(array_merge($existingAttachments, $attachmentPaths), JSON_UNESCAPED_SLASHES);
        }

        $task->save();
        $this->issueNotifications->send($task, 'updated', 'Issue details were updated.');

        $project_id = $task->project_id;
        $users = User::whereHas('projects', function ($query) use ($project_id) {
            $query->where('project_id', $project_id);
        })->get();
        $this->globalUserEventService->broadcastToUsers($users, $project_id, $task->id, '', $task->status);

        return response()->json([
            'task' => $task,
            'success' => true,
            'message' => 'Description updated successfully',
        ]);
    }

    private function sanitizeDescription(?string $description): ?string
    {
        if ($description === null || trim($description) === '') {
            return null;
        }

        $description = strip_tags(
            $description,
            '<p><br><strong><b><em><i><u><ul><ol><li><blockquote><h1><h2><h3><h4>'
        );
        // Editor formatting does not require HTML attributes. Removing them also
        // prevents event-handler and javascript URL injection.
        $description = preg_replace('/<([a-z][a-z0-9]*)\b[^>]*>/i', '<$1>', $description);

        return trim($description) ?: null;
    }
}
