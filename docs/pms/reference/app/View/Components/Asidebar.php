<?php

namespace App\View\Components;

use App\Models\ChMessage;
use App\Models\Invitation;
use App\Models\Department;
use App\Models\Company;
use App\Models\Projects;
use App\Models\Tasks;
use App\Models\Workflow;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\Component;

class Asidebar extends Component
{
    public $assignedProjects;

    public $projectDepartments;

    public $unassignedDepartmentProjects;

    public $sidebarCompanies;

    public $sidebarWorkflows;

    protected $pendingInvitationsCount;

    protected $unSeenMessagesCount;

    /**
     * Create a new component instance.
     */
    public function __construct()
    {
        $user = Auth::user();
        $this->assignedProjects = collect();
        $this->projectDepartments = collect();
        $this->unassignedDepartmentProjects = collect();
        $this->sidebarCompanies = collect();
        $this->sidebarWorkflows = collect();

        if ($user && $user->can('project-view')) {
            $projectsQuery = Projects::query()
                ->where('is_general', false);

            // Reviewers must see newly created projects in Team Space while they
            // are pending approval. Other users may see approved projects plus
            // pending projects explicitly assigned to them.
            if (! $user->hasRole('admin') && ! $user->hasRole('project_manager')) {
                $projectsQuery->where(fn ($query) => $query
                    ->where('approval', 'approved')
                    ->orWhereHas('users', fn ($users) => $users->where('users.id', $user->id))
                    ->orWhereHas('tasks.users', fn ($users) => $users->where('users.id', $user->id))
                    ->orWhereHas('tasks.subtasks.users', fn ($users) => $users->where('users.id', $user->id)));
            }

            if (! $user->hasRole('admin')) {
                $isIndividualContributor = ! $user->hasRole('project_manager') && ! $user->hasRole('team_leader');
                $projectsQuery->where(function ($query) use ($user, $isIndividualContributor) {
                    if (! $isIndividualContributor) {
                        $query->whereHas('users', fn ($users) => $users->where('users.id', $user->id));
                    }
                    $method = $isIndividualContributor ? 'whereHas' : 'orWhereHas';
                    $query->{$method}('tasks.users', fn ($users) => $users->where('users.id', $user->id))
                        ->orWhereHas('tasks.subtasks.users', fn ($users) => $users->where('users.id', $user->id));
                });
            }

            $projects = $projectsQuery->with(['departments' => fn ($query) => $query->where('status', 1)->orderBy('dept_name')])
                ->orderBy('name')->get(['projects.id', 'projects.name', 'projects.approval']);
            $this->assignedProjects = $projects;

            $canonicalDepartmentIds = $projects->pluck('departments')->flatten()->pluck('id')->unique();
            $userDepartmentIds = $user->departments()->pluck('departments.id');
            $assignedBacklogDepartmentIds = Tasks::query()
                ->whereNull('project_id')
                ->whereNotNull('department_id')
                ->where(fn ($tasks) => $tasks
                    ->whereHas('users', fn ($users) => $users->where('users.id', $user->id))
                    ->orWhereHas('subtasks.users', fn ($users) => $users->where('users.id', $user->id)))
                ->pluck('department_id');
            $assignedDepartmentIds = $canonicalDepartmentIds->merge($assignedBacklogDepartmentIds)->unique();
            $departmentsQuery = Department::query()->where('status', 1);
            if ($user->hasRole('team_leader')) {
                $departmentsQuery->where(fn ($query) => $query
                    ->whereIn('departments.id', $userDepartmentIds)
                    ->orWhereIn('departments.id', $assignedDepartmentIds));
            } elseif (! $user->hasRole('admin') && ! $user->hasRole('project_manager')) {
                $departmentsQuery->whereIn('departments.id', $assignedDepartmentIds);
            }

            $this->projectDepartments = $departmentsQuery->orderBy('dept_name')->get(['id', 'dept_name'])
                ->map(function (Department $department) use ($projects, $user, $userDepartmentIds) {
                    $department->setRelation('projects', $projects->filter(
                        fn (Projects $project) => $project->departments->contains('id', $department->id)
                    )->values());
                    $department->setAttribute('can_open_board', true);
                    return $department;
                });
            $groupedProjectIds = $this->projectDepartments->pluck('projects')->flatten()->pluck('id')->unique();
            $this->unassignedDepartmentProjects = $projects->whereNotIn('id', $groupedProjectIds)->values();

            if ($user->can('project-add')) {
                $this->sidebarCompanies = Company::where('status', true)->orderBy('name')->get(['id', 'name']);
                $this->sidebarWorkflows = Workflow::where('is_active', true)->orderByDesc('is_default')->orderBy('name')->get(['id', 'name', 'is_default']);
            }
        }
        // Invitations and Messages are currently hidden from navigation.
        // Avoid their count queries on every page until those features are enabled again.
        $this->pendingInvitationsCount = 0;
        $this->unSeenMessagesCount = 0;

    }

    protected function getPendingInvitationsCount($user): int
    {
        return Invitation::where('recipient_id', $user->id)
            ->whereNull('accepted_at')
            ->whereNull('declined_at') // Adjust if you don't have a declined_at column
            ->where('expires_at', '>=', now())
            ->count();
    }

    protected function unreadMessages($user)
    {
        return ChMessage::where('to_id', $user->id)->where('seen', false)->count();
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('components.asidebar', [

            'assignedProjects' => $this->assignedProjects,
            'projectDepartments' => $this->projectDepartments,
            'unassignedDepartmentProjects' => $this->unassignedDepartmentProjects,
            'sidebarCompanies' => $this->sidebarCompanies,
            'sidebarWorkflows' => $this->sidebarWorkflows,
            'pendingInvitationsCount' => $this->pendingInvitationsCount,
            'unSeenMessagesCount' => $this->unSeenMessagesCount,
        ]);
    }
}
