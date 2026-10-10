<?php

namespace App\Modules\Pms\Http\Controllers;
use App\Http\Controllers\Controller;
use App\Modules\Pms\Services\PmsAccess;

use App\Modules\Pms\Models\Projects;
use App\Modules\Pms\Models\Tasks;
use App\Modules\Pms\Models\User;
use Illuminate\Http\Request;
use App\Modules\Pms\Services\PmsAuth as Auth;
use Illuminate\Support\Facades\DB;
use App\Modules\Pms\Services\PmsGate as Gate;

class DashboardController extends Controller
{
    public function web()
    {
        return view('welcome');
    }

    public function dashboard()
    {
        Gate::authorize('dashboard-view');
        $user = Auth::user();
        $user->loadMissing('roles:id,role_key');
        $param = [];
        $projectStatusCounts = collect();
        $taskStatusCounts = null;
        switch (true) {
            case $user->hasRole('admin'):
                $roleCounts = DB::table('pms_role_users')
                    ->join('pms_roles', 'pms_roles.id', '=', 'pms_role_users.role_id')
                    ->selectRaw("COUNT(DISTINCT CASE WHEN pms_roles.role_key = 'admin' THEN pms_role_users.user_id END) as admins")
                    ->selectRaw("COUNT(DISTINCT CASE WHEN pms_roles.role_key = 'project_manager' THEN pms_role_users.user_id END) as managers")
                    ->selectRaw("COUNT(DISTINCT CASE WHEN pms_roles.role_key = 'team_leader' THEN pms_role_users.user_id END) as team_leads")
                    ->selectRaw("COUNT(DISTINCT CASE WHEN pms_roles.role_key NOT IN ('team_leader','admin','project_manager') THEN pms_role_users.user_id END) as employees")
                    ->first();
                $projectIds = Projects::where('is_general', false)->pluck('id');
                $projectStatusCounts = $this->projectStatusCounts($projectIds);
                $ongoingProjects = $this->sumStatuses($projectStatusCounts, ['pending', 'progress']);
                $completedProjects = $this->sumStatuses($projectStatusCounts, ['completed', 'delivered']);
                $cancelledProjects = $this->sumStatuses($projectStatusCounts, ['cancelled']);
                $param = [
                    'adminCount' => (int) $roleCounts->admins,
                    'managerCount' => (int) $roleCounts->managers,
                    'teamLeadCount' => (int) $roleCounts->team_leads,
                    'employeeCount' => (int) $roleCounts->employees,
                    'ongoingProjectCount' => $ongoingProjects,
                    'completedProjectCount' => $completedProjects,
                    'cancelledProjectCount' => $cancelledProjects,
                    // Temporary aliases keep cached dashboard views safe during rolling deployments.
                    'activeProjectCount' => $ongoingProjects,
                    'InActiveProjectCount' => $completedProjects + $cancelledProjects,
                ];
                $view = 'dashboard.analytics.team-reporting-dashboard';
                break;

            case $user->hasRole('project_manager'):
                $view = 'dashboard.analytics.project-manager-dashboard';
                $departmentIds = $user->departments()->pluck('pms_departments.id');
                $projectIds = $user->projects()->pluck('pms_projects.id');
                $departmentRoleCounts = $this->departmentRoleCounts($departmentIds);
                $teamMembersCount = (int) $departmentRoleCounts->team_members;
                $teamLeadersCount = (int) $departmentRoleCounts->team_leaders;
                $projectCount = $projectIds->count();
                $projectStatusCounts = $this->projectStatusCounts($projectIds);
                $ongoingProjectCount = $this->sumStatuses($projectStatusCounts, ['pending', 'progress']);
                $completedProjectCount = $this->sumStatuses($projectStatusCounts, ['completed', 'delivered']);
                $cancelledProjectCount = $this->sumStatuses($projectStatusCounts, ['cancelled']);
                $param = [
                    'teamLeadersCount' => $teamLeadersCount,
                    'teamMembersCount' => $teamMembersCount,
                    'projectCount' => $projectCount,
                    'ongoingProjectCount' => $ongoingProjectCount,
                    'completedProjectCount' => $completedProjectCount,
                    'cancelledProjectCount' => $cancelledProjectCount,
                    'activeProjectCount' => $ongoingProjectCount,
                    'inactiveProjectCount' => $completedProjectCount + $cancelledProjectCount,
                ];
                break;

            case $user->hasRole('team_leader'):
                $view = 'dashboard.analytics.teamleader-dashboard';
                $departmentIds = $user->departments()->pluck('pms_departments.id');
                $teamMembersCount = (int) $this->departmentRoleCounts($departmentIds)->team_members;
                $projectIds = $user->projects()->pluck('pms_projects.id');
                $projectCount = $projectIds->count();
                $projectStatusCounts = $this->projectStatusCounts($projectIds);
                $ongoingProjectCount = $this->sumStatuses($projectStatusCounts, ['pending', 'progress']);
                $completedProjectCount = $this->sumStatuses($projectStatusCounts, ['completed', 'delivered']);
                $cancelledProjectCount = $this->sumStatuses($projectStatusCounts, ['cancelled']);
                $taskStatusCounts = $this->taskStatusCounts(Tasks::query()->whereIn('project_id', $projectIds));
                $totalTasksCount = $taskStatusCounts->sum();
                $pendingTasksCount = (int) ($taskStatusCounts['pending'] ?? 0);
                $inProgressTasksCount = (int) ($taskStatusCounts['in_progress'] ?? 0);
                $unassignedTasksCount = Tasks::whereIn('project_id', $projectIds)
                    ->whereDoesntHave('users')
                    ->count();
                $param = [
                    'teamMembersCount' => $teamMembersCount,
                    'projectCount' => $projectCount,
                    'ongoingProjectCount' => $ongoingProjectCount,
                    'completedProjectCount' => $completedProjectCount,
                    'cancelledProjectCount' => $cancelledProjectCount,
                    'activeProjectCount' => $ongoingProjectCount,
                    'inactiveProjectCount' => $completedProjectCount + $cancelledProjectCount,
                    'totalTasksCount' => $totalTasksCount,
                    'pendingTasksCount' => $pendingTasksCount,
                    'inProgressTasksCount' => $inProgressTasksCount,
                    'unassignedTasksCount' => $unassignedTasksCount,
                ];
                break;
            default:
                $view = 'dashboard.analytics.team-member-dashboard';
                $assignedTasks = Tasks::query()->where(fn ($query) => $query
                    ->whereHas('users', fn ($users) => $users->where('users.id', $user->id))
                    ->orWhereHas('subtasks.users', fn ($users) => $users->where('users.id', $user->id)));
                $assignedTaskBreakdown = (clone $assignedTasks)
                    ->select('project_id', 'status', DB::raw('COUNT(*) as total'))
                    ->groupBy('project_id', 'status')
                    ->get();
                $projectIds = $assignedTaskBreakdown->pluck('project_id')->unique()->values();
                $taskStatusCounts = $assignedTaskBreakdown->groupBy('status')
                    ->map(fn ($rows) => (int) $rows->sum('total'));
                $projectStatusCounts = $this->projectStatusCounts($projectIds);
                $ongoingProjectCount = $this->sumStatuses($projectStatusCounts, ['pending', 'progress']);
                $completedProjectCount = $this->sumStatuses($projectStatusCounts, ['completed', 'delivered']);
                $cancelledProjectCount = $this->sumStatuses($projectStatusCounts, ['cancelled']);
                $totalTasksCount = $taskStatusCounts->sum();
                $pendingTasksCount = (int) ($taskStatusCounts['pending'] ?? 0);
                $inProgressTasksCount = (int) ($taskStatusCounts['in_progress'] ?? 0);
                $completedTasksCount = (int) ($taskStatusCounts['completed'] ?? 0);
                $param = [
                    'totalTasksCount' => $totalTasksCount,
                    'pendingTasksCount' => $pendingTasksCount,
                    'inProgressTasksCount' => $inProgressTasksCount,
                    'ongoingProjectCount' => $ongoingProjectCount,
                    'completedTasksCount' => $completedTasksCount,
                    'completedProjectCount' => $completedProjectCount,
                    'cancelledProjectCount' => $cancelledProjectCount,
                    'activeProjectCount' => $ongoingProjectCount,
                    'inactiveProjectCount' => $completedProjectCount + $cancelledProjectCount,
                ];

                break;
        }

        $chartProjectIds = $projectIds;

        $canViewTeamWorkload = $user->hasRole('admin') || $user->hasRole('project_manager') || $user->hasRole('team_leader');

        if ($taskStatusCounts === null) {
            $taskStatusCounts = $this->taskStatusCounts(Tasks::query()->whereIn('project_id', $chartProjectIds));
        }

        $param['dashboardCharts'] = [
            'projects' => [
                'labels' => ['Pending', 'In Progress', 'Completed', 'Delivered', 'Cancelled'],
                'series' => [
                    (int) ($projectStatusCounts['pending'] ?? 0),
                    (int) ($projectStatusCounts['progress'] ?? 0),
                    (int) ($projectStatusCounts['completed'] ?? 0),
                    (int) ($projectStatusCounts['delivered'] ?? 0),
                    (int) ($projectStatusCounts['cancelled'] ?? 0),
                ],
            ],
            'tasks' => [
                'labels' => ['To do', 'In Progress', 'In Review', 'Completed'],
                'series' => [
                    (int) ($taskStatusCounts['pending'] ?? 0),
                    (int) ($taskStatusCounts['in_progress'] ?? 0),
                    (int) ($taskStatusCounts['in_review'] ?? 0),
                    (int) ($taskStatusCounts['completed'] ?? 0),
                ],
            ],
        ];

        $workload = collect();
        if ($canViewTeamWorkload) {
            $workload = DB::table('pms_task_user')
                ->join('pms_tasks', 'pms_tasks.id', '=', 'pms_task_user.task_id')
                ->join('users', 'users.id', '=', 'pms_task_user.user_id')
                ->whereIn('pms_tasks.project_id', $chartProjectIds)
                ->whereIn('pms_tasks.status', ['pending', 'in_progress', 'in_review'])
                ->select('users.id', 'users.name', DB::raw('COUNT(DISTINCT pms_tasks.id) as task_count'))
                ->groupBy('users.id', 'users.name')
                ->orderByDesc('task_count')
                ->limit(8)
                ->get();
        }

        $deadlineQuery = Tasks::query()
            ->whereIn('project_id', $chartProjectIds)
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [now()->startOfDay(), now()->addDays(7)->endOfDay()])
            ->where('status', '!=', 'completed')
            ->with(['project:id,name', 'users:id,name,profile_img'])
            ->orderBy('due_date')
            ->limit(8);
        if (!$canViewTeamWorkload) {
            $deadlineQuery->where(fn ($query) => $query
                ->whereHas('users', fn ($users) => $users->where('users.id', $user->id))
                ->orWhereHas('subtasks.users', fn ($users) => $users->where('users.id', $user->id)));
        }

        $param['dashboardInsights'] = [
            'show_workload' => $canViewTeamWorkload,
            'workload' => [
                'labels' => $workload->pluck('name')->values(),
                'series' => $workload->pluck('task_count')->map(fn ($count) => (int) $count)->values(),
            ],
            'deadlines' => $deadlineQuery->get()->map(fn ($task) => [
                'id' => $task->id,
                'title' => $task->title,
                'project_id' => $task->project_id,
                'project' => $task->project?->name ?? 'Project',
                'due_date' => \Carbon\Carbon::parse($task->due_date)->toIso8601String(),
                'due_label' => \Carbon\Carbon::parse($task->due_date)->isToday()
                    ? 'Today'
                    : \Carbon\Carbon::parse($task->due_date)->format('M j'),
                'assignees' => $task->users->take(3)->map(fn ($assignee) => [
                    'name' => $assignee->name,
                    'avatar' => $assignee->profile_img ?: asset('assets/img/user-picture.png'),
                ])->values(),
            ])->values(),
        ];

        return view($view, $param);
    }

    private function projectStatusCounts($projectIds)
    {
        return Projects::query()
            ->whereIn('id', $projectIds)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');
    }

    private function taskStatusCounts($query)
    {
        return $query
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');
    }

    private function sumStatuses($counts, array $statuses): int
    {
        return collect($statuses)->sum(fn ($status) => (int) ($counts[$status] ?? 0));
    }

    private function departmentRoleCounts($departmentIds)
    {
        return DB::table('pms_user_departments')
            ->join('pms_role_users', 'pms_role_users.user_id', '=', 'pms_user_departments.user_id')
            ->join('pms_roles', 'pms_roles.id', '=', 'pms_role_users.role_id')
            ->whereIn('pms_user_departments.dept_id', $departmentIds)
            ->selectRaw("COUNT(DISTINCT CASE WHEN pms_roles.role_key = 'team_leader' THEN pms_role_users.user_id END) as team_leaders")
            ->selectRaw("COUNT(DISTINCT CASE WHEN pms_roles.role_key NOT IN ('team_leader','project_manager') THEN pms_role_users.user_id END) as team_members")
            ->first();
    }

    public function index()
    {
        Gate::authorize('dashboard-view');
        $user = Auth::user();
        if ($user->hasRole('team_leader')) {
            $departmentIds = $user->departments()->pluck('pms_departments.id');
            $teamMembers = User::whereHas('departments', function ($query) use ($departmentIds) {
                $query->whereIn('pms_departments.id', $departmentIds);
            })->whereHas('roles', function ($query) {
                $query->whereNotIn('role_key', ['team_leader', 'project_manager']);
            })->with('roles:id,role_name')
                ->withCount([
                    'projects as assigned_projects',
                    'tasks as assigned_tasks',
                    'tasks as pending_tasks' => fn ($tasks) => $tasks->where('status', 'pending'),
                    'tasks as in_progress_tasks' => fn ($tasks) => $tasks->where('status', 'in_progress'),
                    'tasks as completed_tasks' => fn ($tasks) => $tasks->where('pms_tasks.status', 'completed'),
                ])
                ->get(['users.id', 'users.name', 'users.designation']);
            $teamMembersData = $teamMembers->map(function ($member) {
                return [
                    'name' => $member->name,
                    'designation' => $member->designation,
                    'role' => $member->roles->pluck('role_name')->implode(', '),
                    'assigned_projects' => $member->assigned_projects,
                    'assigned_tasks' => $member->assigned_tasks,
                    'pending_tasks' => $member->pending_tasks,
                    'in_progress_tasks' => $member->in_progress_tasks,
                    'completed_tasks' => $member->completed_tasks,
                ];
            });

            return response()->json(['data' => $teamMembersData]);

        } elseif ($user->hasRole('project_manager') || $user->hasRole('admin')) {
            $projects = $this->projectTableQuery()
                ->when($user->hasRole('project_manager'), fn ($query) => $query
                    ->whereIn('pms_projects.id', $user->projects()->select('pms_projects.id')))
                ->get();

            return response()->json(['data' => $this->setCompletionPercentages($projects)]);
        } else {
            $projects = Projects::query()
                ->where('is_general', false)
                ->where('approval', 'approved')
                ->where(fn ($query) => $query
                    ->whereHas('tasks.users', fn ($users) => $users->where('users.id', $user->id))
                    ->orWhereHas('tasks.subtasks.users', fn ($users) => $users->where('users.id', $user->id)))
                ->with('users.roles:id,role_key,role_name')
                ->withCount([
                    'tasks as total_tasks' => fn ($tasks) => $this->assignedTaskConstraint($tasks, $user->id),
                    'tasks as completed_tasks' => fn ($tasks) => $this->assignedTaskConstraint($tasks, $user->id)->where('pms_tasks.status', 'completed'),
                    'subtasks as total_subtasks' => fn ($subtasks) => $subtasks->whereHas('users', fn ($users) => $users->where('users.id', $user->id)),
                    'subtasks as completed_subtasks' => fn ($subtasks) => $subtasks
                        ->whereHas('users', fn ($users) => $users->where('users.id', $user->id))
                        ->where('pms_subtasks.status', 'completed'),
                ])
                ->get(['pms_projects.id', 'pms_projects.name', 'pms_projects.image', 'pms_projects.start_date', 'pms_projects.end_date', 'pms_projects.status']);

            return response()->json(['data' => $this->setCompletionPercentages($projects)]);
        }
    }

    private function projectTableQuery()
    {
        return Projects::query()
            ->where('is_general', false)
            ->with('users.roles:id,role_key,role_name')
            ->withCount([
                'tasks as total_tasks',
                'tasks as completed_tasks' => fn ($tasks) => $tasks->where('status', 'completed'),
                'subtasks as total_subtasks',
                'subtasks as completed_subtasks' => fn ($subtasks) => $subtasks->where('pms_subtasks.status', 'completed'),
            ])
            ->addSelect(['pms_projects.id', 'pms_projects.name', 'pms_projects.image', 'pms_projects.start_date', 'pms_projects.end_date', 'pms_projects.status']);
    }

    private function assignedTaskConstraint($query, int $userId)
    {
        return $query->where(fn ($tasks) => $tasks
            ->whereHas('users', fn ($users) => $users->where('users.id', $userId))
            ->orWhereHas('subtasks.users', fn ($users) => $users->where('users.id', $userId)));
    }

    private function setCompletionPercentages($projects)
    {
        return $projects->each(function ($project) {
            $total = $project->total_tasks + $project->total_subtasks;
            $completed = $project->completed_tasks + $project->completed_subtasks;
            $project->completion_percentage = $total > 0 ? round(($completed / $total) * 100, 2) : 0;
        });
    }

    public function timeTrackingDashBoard(Request $request)
    {
        PmsAccess::requirePermission(request(),'time-tracking-dashboard');
        $actor = \App\Modules\Pms\Services\PmsAuth::user();
        $canViewAll = $actor->hasRole('admin') || $actor->hasRole('project_manager');
        $departmentIds = $actor->departments()->pluck('pms_departments.id');

        $users = User::query()
            ->with('roles')
            ->where('status', 1)
            ->whereDoesntHave('roles', fn ($roles) => $roles->where('role_key', 'admin'))
            ->when(! $canViewAll && $actor->hasRole('team_leader'), fn ($query) => $query
                ->whereHas('departments', fn ($departments) => $departments
                    ->whereIn('pms_departments.id', $departmentIds)))
            ->when(! $canViewAll && ! $actor->hasRole('team_leader'), fn ($query) => $query
                ->whereKey($actor->id))
            ->orderBy('name')
            ->get()
            ->map(function (User $user) {
                $user->setAttribute(
                    'is_online',
                    $user->last_seen_at?->gte(now()->subMinutes(2)) ?? false
                );

                return $user;
            });

        if ($request->ajax()) {
            return response()->json(['data' => $users->values()]);
        }

        return view('dashboard.analytics.time-tracking-dashboard');
    }
}
