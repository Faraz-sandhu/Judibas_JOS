<?php

namespace App\Http\Controllers;

use App\Models\Projects;
use App\Models\Sprint;
use App\Models\Tasks;
use App\Models\User;
use App\Models\Workflow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MyWorkController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('my-work-view');

        $validated = $request->validate([
            'project' => ['nullable', 'integer', 'exists:projects,id'],
            'sprint' => ['nullable', 'integer', 'exists:sprints,id'],
            'status' => ['nullable', 'in:pending,in_progress,in_review,completed'],
            'assignee' => ['nullable', 'integer', 'exists:users,id'],
            'workflow' => ['nullable', 'integer', 'exists:workflows,id'],
        ]);

        $user = $request->user();
        $canManageTeam = Gate::allows('my-work-manage-team');
        $canQuickAssign = $user->hasRole('admin') || $user->hasRole('project_manager') || $user->hasRole('team_leader');
        $query = Tasks::query()->with([
            'project:id,name',
            'sprint:id,project_id,name,status,start_date,end_date',
            'users:id,name,profile_img',
            'timerLogs:id,task_id,user_id,start_time,end_time',
        ])->withCount(['subtasks', 'comments', 'attachments']);

        if (! $canManageTeam) {
            $query->where(fn (Builder $tasks) => $tasks
                ->whereHas('users', fn (Builder $users) => $users->where('users.id', $user->id))
                ->orWhereHas('subtasks.users', fn (Builder $users) => $users->where('users.id', $user->id)));
        } elseif (! $user->hasRole('admin')) {
            $query->whereHas('project.users', fn (Builder $users) => $users->where('users.id', $user->id));
        }

        $accessibleProjectIds = (clone $query)->reorder()->distinct()->pluck('project_id');
        $workflows = Workflow::query()->where('is_active', true)
            ->whereHas('projects', fn (Builder $projects) => $projects->whereIn('projects.id', $accessibleProjectIds))
            ->with(['columns', 'department:id,dept_name'])->orderBy('name')->get();
        $selectedWorkflow = $workflows->firstWhere('id', (int) ($validated['workflow'] ?? 0)) ?? $workflows->first();
        if ($selectedWorkflow) {
            $query->where('workflow_id', $selectedWorkflow->id);
        }

        if ($canManageTeam && ! empty($validated['assignee'])) {
            $query->where(fn (Builder $tasks) => $tasks
                ->whereHas('users', fn (Builder $users) => $users->where('users.id', $validated['assignee']))
                ->orWhereHas('subtasks.users', fn (Builder $users) => $users->where('users.id', $validated['assignee'])));
        }

        $query
            ->when($validated['project'] ?? null, fn (Builder $q, $id) => $q->where('project_id', $id))
            ->when($validated['sprint'] ?? null, fn (Builder $q, $id) => $q->where('sprint_id', $id))
            ->when($validated['status'] ?? null, fn (Builder $q, $status) => $q->where('status', $status));

        $statusCounts = (clone $query)->reorder()->selectRaw('workflow_column_id, count(*) as aggregate')
            ->groupBy('workflow_column_id')->pluck('aggregate', 'workflow_column_id');
        $tasks = $query->orderBy('position')->orderByRaw('due_date IS NULL, due_date')->latest('id')->get();

        $projects = Projects::whereIn('id', $accessibleProjectIds)->orderBy('name')->get(['id', 'name']);
        $sprints = Sprint::whereIn('project_id', $accessibleProjectIds)->latest('id')->get(['id', 'project_id', 'name', 'status']);
        $assignees = $canQuickAssign
            ? User::query()
                ->where('status', 1)
                ->whereDoesntHave('roles', fn (Builder $roles) => $roles->where('role_key', 'admin'))
                ->when(
                    $user->hasRole('team_leader') && ! $user->hasRole('admin') && ! $user->hasRole('project_manager'),
                    fn (Builder $users) => $users->whereHas(
                        'departments',
                        fn (Builder $departments) => $departments->whereIn('departments.id', $user->departments->pluck('id'))
                    )
                )
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'profile_img'])
            : collect();

        return view('dashboard.my-work.index', compact(
            'tasks', 'projects', 'sprints', 'assignees', 'statusCounts', 'canManageTeam', 'canQuickAssign', 'workflows', 'selectedWorkflow'
        ));
    }
}
