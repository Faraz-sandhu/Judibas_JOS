<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Projects;
use App\Models\TaskHandoff;
use App\Models\Tasks;
use App\Models\User;
use App\Services\IssueNotificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TaskHandoffController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private IssueNotificationService $notifications) {}

    public function options(Request $request, Tasks $task)
    {
        $this->authorizeTask($task);
        $sourceDepartment = $this->resolveSourceDepartment($request, $task);

        $departments = Department::query()->where('status', 1)
            ->when($sourceDepartment, fn (Builder $query) => $query->whereKeyNot($sourceDepartment->id))
            ->with(['projects' => fn ($query) => $query->where('approval', 'approved')->orderBy('name')
                ->with(['workflows' => fn ($workflows) => $workflows->where('is_active', true)->with('columns')])])
            ->orderBy('dept_name')->get();

        return response()->json(['departments' => $departments->map(fn (Department $department) => [
            'id' => $department->id,
            'name' => $department->dept_name,
            'projects' => $department->projects->map(function (Projects $project) {
                $workflow = $project->workflows->first();
                $initial = $workflow?->columns->firstWhere('is_initial', true) ?? $workflow?->columns->first();
                return ['id' => $project->id, 'name' => $project->name, 'workflow_id' => $workflow?->id, 'column_id' => $initial?->id];
            })->filter(fn ($project) => $project['workflow_id'] && $project['column_id'])->values(),
        ])->values()]);
    }

    public function store(Request $request, Tasks $task)
    {
        $this->authorizeTask($task);
        abort_if($task->outgoingHandoffs()->exists(), 422, 'This task has already been handed off. Open its linked destination task to continue the work chain.');
        $data = $request->validate([
            'source_department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'destination_department_id' => ['required', 'integer', 'exists:departments,id'],
            'destination_project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $sourceDepartment = $this->resolveSourceDepartment($request, $task);
        abort_if($sourceDepartment && (int) $sourceDepartment->id === (int) $data['destination_department_id'], 422, 'Choose a different department for the handoff.');

        $destinationProject = null;
        if (! empty($data['destination_project_id'])) {
            $destinationProject = Projects::query()->where('approval', 'approved')
                ->whereHas('departments', fn (Builder $query) => $query->where('departments.id', $data['destination_department_id']))
                ->with(['workflows' => fn ($query) => $query->where('is_active', true)->with('columns')])
                ->findOrFail($data['destination_project_id']);
        }
        $workflow = $destinationProject?->workflows->first()
            ?? \App\Models\Workflow::where('department_id', $data['destination_department_id'])->where('is_active', true)->with('columns')->first()
            ?? \App\Models\Workflow::where('is_default', true)->where('is_active', true)->with('columns')->first();
        $initialColumn = $workflow?->columns->firstWhere('is_initial', true) ?? $workflow?->columns->first();
        abort_unless($workflow && $initialColumn, 422, 'The destination department needs an active workflow before receiving a handoff.');

        [$destinationTask, $handoff] = DB::transaction(function () use ($task, $data, $destinationProject, $workflow, $initialColumn, $sourceDepartment) {
            $task->loadMissing(['workflow.columns', 'project', 'attachments']);
            $completedColumn = $task->workflow?->columns->firstWhere('is_completed', true);
            $task->update([
                'status' => 'completed',
                'workflow_column_id' => $completedColumn?->id ?? $task->workflow_column_id,
                'completed_by' => Auth::id(),
                'end_time' => now(),
            ]);

            $destinationTask = Tasks::create([
                'project_id' => $destinationProject?->id,
                'department_id' => $data['destination_department_id'],
                'workflow_id' => $workflow->id,
                'workflow_column_id' => $initialColumn->id,
                'sprint_id' => null,
                'title' => $task->title,
                'description' => $task->description,
                'status' => 'pending',
                'priority' => $task->priority ?? 1,
                'position' => (Tasks::where('department_id', $data['destination_department_id'])->where('project_id', $destinationProject?->id)->whereNull('sprint_id')->max('position') ?? 0) + 1,
                'created_by' => Auth::id(),
            ]);

            foreach ($task->attachments as $attachment) {
                $destinationTask->attachments()->create([
                    'user_id' => $attachment->user_id,
                    'original_name' => $attachment->original_name,
                    'path' => $attachment->path,
                    'mime_type' => $attachment->mime_type,
                    'size' => $attachment->size,
                ]);
            }

            $leadIds = User::query()->where('status', 1)
                ->whereHas('departments', fn (Builder $query) => $query->where('departments.id', $data['destination_department_id']))
                ->whereHas('roles', fn (Builder $query) => $query->where('role_key', 'team_leader'))
                ->pluck('id');
            if ($leadIds->isNotEmpty()) {
                $assignmentPayload = $leadIds->mapWithKeys(fn ($id) => [(int) $id => ['assigned_by' => Auth::id()]])->all();
                $destinationProject?->users()->syncWithoutDetaching($assignmentPayload);
                $destinationTask->users()->syncWithoutDetaching($assignmentPayload);
            }

            $handoff = TaskHandoff::create([
                'source_task_id' => $task->id,
                'destination_task_id' => $destinationTask->id,
                'source_department_id' => $sourceDepartment?->id,
                'destination_department_id' => $data['destination_department_id'],
                'source_project_id' => $task->project_id,
                'destination_project_id' => $destinationProject?->id,
                'handed_off_by' => Auth::id(),
                'notes' => $data['notes'] ?? null,
            ]);
            return [$destinationTask, $handoff];
        });

        // Notifications must describe the department board that initiated the
        // handoff, not a stale department_id retained on an older project task.
        if ($sourceDepartment) {
            $task->setRelation('department', $sourceDepartment);
        }

        $recipientIds = User::query()->where('status', 1)
            ->whereHas('departments', fn (Builder $query) => $query->where('departments.id', $data['destination_department_id']))
            ->pluck('id');
        $this->notifications->handoff($destinationTask, $task, $recipientIds, $handoff->notes);

        return response()->json([
            'message' => $destinationTask->project_id
                ? 'Task handed off successfully. The destination task was added to the project backlog.'
                : 'Task handed off successfully. The destination task was added to the department backlog.',
            'destination_task_id' => $destinationTask->id,
            'redirect_url' => $destinationTask->project_id
                ? route('projects.show', ['project' => $destinationTask->project_id, 'issue' => $destinationTask->id])
                : route('team-space.departments.board', ['department' => $destinationTask->department_id, 'project' => 'department-backlog', 'issue' => $destinationTask->id]),
        ], 201);
    }

    public function place(Request $request, Tasks $task)
    {
        $this->authorize('task-edit');
        abort_unless($task->project_id === null && $task->department_id, 422, 'Only department-backlog tasks can be placed into a project.');
        $this->authorizeTask($task);
        $data = $request->validate([
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'sprint_id' => ['nullable', 'integer', 'exists:sprints,id'],
        ]);
        $project = Projects::where('approval', 'approved')
            ->whereHas('departments', fn (Builder $query) => $query->where('departments.id', $task->department_id))
            ->with(['workflows' => fn ($query) => $query->where('is_active', true)->with('columns')])
            ->findOrFail($data['project_id']);
        if (! empty($data['sprint_id'])) {
            abort_unless($project->sprints()->whereKey($data['sprint_id'])->exists(), 422, 'The selected sprint does not belong to this project.');
        }
        $workflow = $project->workflows->first();
        $column = $workflow?->columns->firstWhere('is_initial', true) ?? $workflow?->columns->first();
        abort_unless($workflow && $column, 422, 'The selected project needs an active workflow.');

        DB::transaction(function () use ($task, $project, $workflow, $column, $data) {
            $task->update(['project_id' => $project->id, 'workflow_id' => $workflow->id, 'workflow_column_id' => $column->id, 'sprint_id' => $data['sprint_id'] ?? null, 'status' => 'pending']);
            if ($task->users()->exists()) {
                $payload = $task->users()->pluck('users.id')->mapWithKeys(fn ($id) => [(int) $id => ['assigned_by' => Auth::id()]])->all();
                $project->users()->syncWithoutDetaching($payload);
            }
        });
        $this->notifications->send($task->fresh(), 'placed in project', 'The receiving team placed this handoff in '.$project->name.'.');

        return response()->json(['message' => 'Task placed in the project successfully.', 'redirect_url' => route('projects.show', ['project' => $project->id, 'issue' => $task->id])]);
    }

    private function authorizeTask(Tasks $task): void
    {
        $this->authorize('task-handoff');
        $user = Auth::user();
        $allowed = $user->hasRole('admin')
            || ($task->project && $task->project->users()->where('users.id', $user->id)->exists())
            || $task->users()->where('users.id', $user->id)->exists();
        if (! $allowed && $task->department_id && $user->can('department-task-view-all')) {
            $allowed = $user->hasRole('project_manager') || $user->departments()->where('departments.id', $task->department_id)->exists();
        }
        abort_unless($allowed, 403, 'You do not have access to hand off this task.');
    }

    private function resolveSourceDepartment(Request $request, Tasks $task): ?Department
    {
        $sourceDepartmentId = $request->integer('source_department_id') ?: $task->department_id;
        if (! $sourceDepartmentId) {
            return null;
        }

        $sourceDepartment = Department::findOrFail($sourceDepartmentId);
        $belongsToSource = $task->project_id
            ? $task->project()->whereHas('departments', fn (Builder $query) => $query->where('departments.id', $sourceDepartmentId))->exists()
            : (int) $task->department_id === (int) $sourceDepartmentId;
        abort_unless($belongsToSource, 422, 'This task does not belong to the selected source department.');

        return $sourceDepartment;
    }
}
