<?php
namespace App\Modules\Pms\Http\Controllers;
use App\Http\Controllers\Controller;
use App\Modules\Pms\Models\Projects;
use App\Modules\Pms\Models\Tasks;
use App\Modules\Pms\Models\Department;
use App\Modules\Pms\Services\PmsAccess;
use App\Modules\Pms\Services\PmsAuth;
class TeamSpaceController extends Controller {
 public function index(){PmsAccess::requirePermission(request(),'project-view');$user=PmsAuth::user();$assignedProjects=collect();$projectDepartments=collect();$unassignedDepartmentProjects=collect();
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
                ->orderBy('name')->get(['pms_projects.id', 'pms_projects.name', 'pms_projects.approval']);
            $assignedProjects = $projects;

            $canonicalDepartmentIds = $projects->pluck('departments')->flatten()->pluck('id')->unique();
            $userDepartmentIds = $user->departments()->pluck('pms_departments.id');
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
                    ->whereIn('pms_departments.id', $userDepartmentIds)
                    ->orWhereIn('pms_departments.id', $assignedDepartmentIds));
            } elseif (! $user->hasRole('admin') && ! $user->hasRole('project_manager')) {
                $departmentsQuery->whereIn('pms_departments.id', $assignedDepartmentIds);
            }

            $projectDepartments = $departmentsQuery->orderBy('dept_name')->get(['id', 'dept_name'])
                ->map(function (Department $department) use ($projects, $user, $userDepartmentIds) {
                    $department->setRelation('projects', $projects->filter(
                        fn (Projects $project) => $project->departments->contains('id', $department->id)
                    )->values());
                    $department->setAttribute('can_open_board', true);
                    return $department;
                });
            $groupedProjectIds = $projectDepartments->pluck('projects')->flatten()->pluck('id')->unique();
            $unassignedDepartmentProjects = $projects->whereNotIn('id', $groupedProjectIds)->values();

        }
return response()->json(['departments'=>$projectDepartments,'otherProjects'=>$unassignedDepartmentProjects]);}
}
