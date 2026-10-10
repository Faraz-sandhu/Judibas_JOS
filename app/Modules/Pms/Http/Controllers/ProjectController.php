<?php

namespace App\Modules\Pms\Http\Controllers;
use App\Http\Controllers\Controller;
use App\Modules\Pms\Services\PmsAccess;

use App\Modules\Pms\Models\Company;
use App\Modules\Pms\Models\Department;
use App\Modules\Pms\Models\Projects;
use App\Modules\Pms\Models\Sprint;
use App\Modules\Pms\Models\Tasks;
use App\Modules\Pms\Models\TimerLog;
use App\Modules\Pms\Models\User;
use App\Modules\Pms\Models\Workflow;
use Carbon\Carbon;
use Carbon\CarbonInterval;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Modules\Pms\Services\PmsAuth as Auth;
use Illuminate\Support\Facades\DB;

class ProjectController extends Controller
{
    use AuthorizesRequests;

    public function departmentBoard(Department $department)
    {
        PmsAccess::requirePermission(request(), 'project-view');
        $user = Auth::user();
        $canViewAllDepartmentTasks = $user->can('department-task-view-all');
        $canViewAssignedDepartmentTasks = $user->can('department-task-view-assigned');
        abort_unless($canViewAllDepartmentTasks || $canViewAssignedDepartmentTasks, 403, 'You do not have permission to view department tasks.');
        if (! $user->hasRole('admin') && ! $user->hasRole('project_manager')) {
            $isOwnDepartment = $user->departments()->where('pms_departments.id', $department->id)->exists();
            $hasAssignedWork = Tasks::query()
                ->where(function (Builder $tasks) use ($department) {
                    $tasks->whereHas('project.departments', fn (Builder $departments) => $departments->where('pms_departments.id', $department->id))
                        ->orWhere(fn (Builder $backlog) => $backlog
                            ->whereNull('project_id')
                            ->where('department_id', $department->id));
                })
                ->where(fn (Builder $tasks) => $tasks
                    ->whereHas('users', fn (Builder $users) => $users->where('users.id', $user->id))
                    ->orWhereHas('subtasks.users', fn (Builder $users) => $users->where('users.id', $user->id)))
                ->exists();

            abort_unless(
                ($canViewAllDepartmentTasks && $isOwnDepartment) || $hasAssignedWork,
                403,
                'This department has no work assigned to you.'
            );
        }

        $projectsQuery = $department->projects();
        if (! $user->hasRole('admin') && ! $user->hasRole('project_manager')) {
            $projectsQuery->where(fn (Builder $query) => $query
                ->where('approval', 'approved')
                ->orWhereHas('users', fn (Builder $users) => $users->where('users.id', $user->id))
                ->orWhereHas('tasks.users', fn (Builder $users) => $users->where('users.id', $user->id))
                ->orWhereHas('tasks.subtasks.users', fn (Builder $users) => $users->where('users.id', $user->id)));
        }
        if (! $canViewAllDepartmentTasks) {
            $projectsQuery->where(function (Builder $query) use ($user) {
                $query->whereHas('tasks.users', fn (Builder $users) => $users->where('users.id', $user->id))
                    ->orWhereHas('tasks.subtasks.users', fn (Builder $users) => $users->where('users.id', $user->id));
            });
        }

        $departmentProjects = $projectsQuery->orderBy('name')->get(['pms_projects.id', 'pms_projects.name']);
        $departmentBacklogQuery = Tasks::where('department_id', $department->id)->whereNull('project_id');
        if (! $canViewAllDepartmentTasks) {
            $departmentBacklogQuery->where(fn (Builder $tasks) => $tasks
                ->whereHas('users', fn (Builder $users) => $users->where('users.id', $user->id))
                ->orWhereHas('subtasks.users', fn (Builder $users) => $users->where('users.id', $user->id)));
        }
        $hasDepartmentBacklog = $departmentBacklogQuery->exists();
        if ($departmentProjects->isEmpty() && ! $hasDepartmentBacklog) {
            return view('dashboard.projects.department-empty', compact('department'));
        }
        $projectIds = $departmentProjects->pluck('id');
        $departmentBacklogOnly = request('project') === 'department-backlog';
        $selectedProjectId = request()->filled('project') && ! $departmentBacklogOnly ? (int) request('project') : null;
        abort_if($selectedProjectId && ! $projectIds->contains($selectedProjectId), 404);

        $workflows = Workflow::query()->where('is_active', true)
            ->where(function (Builder $query) use ($department, $projectIds) {
                $query->where('department_id', $department->id)
                    ->orWhereHas('projects', fn (Builder $projects) => $projects->whereIn('pms_projects.id', $projectIds));
            })
            ->with(['columns', 'department:id,dept_name'])->orderBy('name')->get();
        if ($workflows->isEmpty()) $workflows = Workflow::where('is_default', true)->where('is_active', true)->with(['columns','department:id,dept_name'])->get();
        $selectedWorkflow = $workflows->firstWhere('id', (int) request('workflow')) ?? $workflows->first();

        $sprints = Sprint::query()->whereIn('project_id', $selectedProjectId ? [$selectedProjectId] : $projectIds)
            ->with('project:id,name')->withCount('tasks')->orderByDesc('id')->get();
        $selectedSprint = request()->filled('sprint') && request('sprint') !== 'all' && request('sprint') !== 'backlog'
            ? $sprints->firstWhere('id', (int) request('sprint')) : null;

        $departmentTaskScope = function (Builder $query) use ($department, $projectIds, $selectedProjectId, $departmentBacklogOnly): void {
            if ($selectedProjectId) {
                $query->where('project_id', $selectedProjectId);
            } elseif ($departmentBacklogOnly) {
                $query->where('department_id', $department->id)->whereNull('project_id');
            } else {
                // Show work from projects linked to this department plus this
                // department's own unplaced backlog. A task assigned to this
                // department but belonging to an unrelated project is excluded.
                $query->where(function (Builder $tasks) use ($department, $projectIds) {
                    $tasks->whereIn('project_id', $projectIds)
                        ->orWhere(function (Builder $backlog) use ($department) {
                            $backlog->whereNull('project_id')
                                ->where('department_id', $department->id);
                        });
                });
            }
        };
        $departmentVisibilityScope = function (Builder $query) use ($canViewAllDepartmentTasks, $user): void {
            if (! $canViewAllDepartmentTasks) {
                $query->where(fn (Builder $tasks) => $tasks
                    ->whereHas('users', fn (Builder $users) => $users->where('users.id', $user->id))
                    ->orWhereHas('subtasks.users', fn (Builder $users) => $users->where('users.id', $user->id)));
            }
        };

        $departmentStatsQuery = Tasks::query();
        $departmentTaskScope($departmentStatsQuery);
        $departmentVisibilityScope($departmentStatsQuery);
        $departmentStats = [
            'tasks' => (clone $departmentStatsQuery)->count(),
            'backlog' => (clone $departmentStatsQuery)->whereNull('sprint_id')->count(),
            'sprint' => (clone $departmentStatsQuery)->whereNotNull('sprint_id')->count(),
        ];

        $boardTasks = Tasks::query();
        $departmentTaskScope($boardTasks);
        $departmentVisibilityScope($boardTasks);
        $boardTasks = $boardTasks
            ->when(request('sprint') === 'backlog', fn (Builder $query) => $query->whereNull('sprint_id'))
            ->when($selectedSprint, fn (Builder $query) => $query->where('sprint_id', $selectedSprint->id))
            ->with(['project:id,name', 'department:id,dept_name', 'sprint:id,project_id,name', 'incomingHandoff.sourceDepartment:id,dept_name', 'outgoingHandoffs.destinationDepartment:id,dept_name', 'users:id,name,profile_img', 'subtasks:id,task_id,status', 'timerLogs:id,task_id,user_id,start_time,end_time'])
            ->withCount(['comments', 'attachments'])->orderBy('position')->orderBy('id')->get();

        if ($user->hasRole('admin') || $user->hasRole('project_manager')) {
            $asignee = User::where('status', 1)->whereDoesntHave('roles', fn ($q) => $q->where('role_key', 'admin'))->orderBy('name')->get(['id','name','email','profile_img']);
        } elseif ($user->hasRole('team_leader')) {
            $asignee = User::where('status', 1)->whereHas('departments', fn ($q) => $q->where('pms_departments.id', $department->id))->orderBy('name')->get(['id','name','email','profile_img']);
        } else {
            $asignee = null;
        }

        $sprintStats = ['total' => $sprints->count(), 'planned' => $sprints->where('status','planned')->count(), 'active' => $sprints->where('status','active')->count(), 'completed' => $sprints->where('status','completed')->count()];
        $workloadUsers = collect();
        $project = $departmentProjects->first(); // Nullable in a new department-only backlog.
        $departmentMode = true;

        return view('dashboard.projects.tasks', compact('department', 'departmentMode', 'departmentProjects', 'departmentStats', 'selectedProjectId', 'project', 'asignee', 'sprints', 'selectedSprint', 'boardTasks', 'sprintStats', 'workloadUsers', 'workflows', 'selectedWorkflow'));
    }

    public function index(Request $request)
    {
        PmsAccess::requirePermission(request(), 'project-view');
        $user = Auth::user();
        $userId = Auth::id();
        if (! $user->hasRole('admin') && ! $user->hasRole('project_manager') && ! $user->hasRole('team_leader')) {
            return redirect(\App\Modules\Pms\Services\PmsUrls::workspace(['section' => 'my-work']));
        }
        $isIndividualContributor = ! $user->hasRole('admin') && ! $user->hasRole('project_manager') && ! $user->hasRole('team_leader');
        $visibleTasks = function (Builder $query) use ($isIndividualContributor, $userId): void {
            if ($isIndividualContributor) {
                $query->where(fn (Builder $tasks) => $tasks
                    ->whereHas('users', fn (Builder $users) => $users->where('users.id', $userId))
                    ->orWhereHas('subtasks.users', fn (Builder $users) => $users->where('users.id', $userId)));
            }
        };

        // $projectsQuery = Projects::with(['users'])->withCount('users','tasks');
        $projectsQuery = Projects::where('is_general', false)->with(['users.roles'])
            ->withCount([
                'users',
                'tasks' => $visibleTasks,
                'tasks as pending_tasks_count' => function ($query) use ($visibleTasks) {
                    $visibleTasks($query);
                    $query->where('status', 'pending');
                },
                'tasks as in_progress_tasks_count' => function ($query) use ($visibleTasks) {
                    $visibleTasks($query);
                    $query->where('status', 'in_progress');
                },
                'tasks as completed_tasks_count' => function ($query) use ($visibleTasks) {
                    $visibleTasks($query);
                    $query->where('status', 'completed');
                },
                'Tasks as unassigned_tasks_count' => function ($query) use ($visibleTasks) {
                    $visibleTasks($query);
                    $query->whereDoesntHave('users');
                },
                'chatMessages as unseen_comments_count' => function (Builder $query) use ($userId) {
                    $query->where(function ($q) use ($userId) {
                        $q->whereNull('seen_by')
                            ->orWhereJsonDoesntContain('seen_by', $userId);
                    });
                },

            ]);
        if (! $user->hasRole('admin')) {
            if ($user->hasRole('project_manager') || $user->hasRole('team_leader')) {
                $projectsQuery->whereHas('users', fn ($query) => $query->where('users.id', $user->id));
            } else {
                $projectsQuery->where(fn (Builder $query) => $query
                    ->whereHas('users', fn (Builder $users) => $users->where('users.id', $user->id))
                    ->orWhereHas('tasks.users', fn (Builder $users) => $users->where('users.id', $user->id))
                    ->orWhereHas('tasks.subtasks.users', fn (Builder $users) => $users->where('users.id', $user->id)));
            }
        }
        if ($user->hasRole('project_manager') || $user->hasRole('admin') || $user->hasRole('team_leader')) {
            $projectsQuery->whereIn('approval', ['approved', 'pending', 'rejected']);
        } else {
            $projectsQuery->where('approval', 'approved');
        }
        if ($request->has('status') && ! empty($request->status)) {
            $projectsQuery->where('status', $request->status);
        }
        if ($request->has('approval') && ! empty($request->approval)) {
            $projectsQuery->where('approval', $request->approval);
        }
        $projects = $projectsQuery->get();

        $filteredUsersQuery = User::with('roles');
        switch (true) {
            case $user->hasRole('admin'):
                $filteredUsersQuery->whereHas('roles', fn ($query) => $query->whereIn('role_key', ['project_manager', 'team_leader']));
                break;
            case $user->hasRole('project_manager'):
                $filteredUsersQuery->whereHas('roles', fn ($query) => $query->where('role_key', 'team_leader'));
                break;
            case $user->hasRole('team_leader'):
                $filteredUsersQuery->whereDoesntHave('roles', fn ($query) => $query->where('role_key', 'admin'));
                break;
            default:
                $filteredUsersQuery = null; // Return empty collection later
        }

        $filteredUsers = $filteredUsersQuery ? $filteredUsersQuery->get() : collect();
        $approvedProjects = $projects->where('approval', 'approved')->count();
        $pendingProjects = $projects->where('approval', 'pending')->count();
        $rejectedProjects = $projects->where('approval', 'rejected')->count();

        if ($request->ajax()) {
            return response()->json(['data' => $projects->toArray()]);
        }

        $availableWorkflows = Workflow::where('is_active', true)->with('department:id,dept_name')->orderBy('name')->get();
        $companies = Company::where('status', true)->orderBy('name')->get(['id', 'name']);
        $departments = Department::query()->where('status', 1)
            ->when($user->hasRole('team_leader'), fn ($query) => $query->whereHas('users', fn ($users) => $users->where('users.id', $user->id)))
            ->orderBy('dept_name')->get(['id', 'dept_name']);
        return view('dashboard.projects.index', compact('projects', 'filteredUsers', 'approvedProjects', 'pendingProjects', 'rejectedProjects', 'availableWorkflows', 'companies', 'departments'));
    }

    public function assignProjectToTeamLeaderAndMembers(Request $request)
    {
        PmsAccess::requirePermission(request(), 'project-assign');
        $userId = explode(',', $request->userId);
        $user = Auth::user();
        $project = Projects::findOrFail($request->projectId);

        if (! $project) {
            return response()->json(['status' => false, 'message' => 'Project Not Found'], Response::HTTP_BAD_REQUEST);
        }

        if (! \App\Modules\Pms\Services\PmsAuth::user()->hasRole('admin') && $project->approval != 'approved') {
            return response()->json(['status' => false, 'message' => 'Project is not approved yet'], Response::HTTP_BAD_REQUEST);
        }

        $roleKey = $user->roles->pluck('role_key')->first();

        switch ($roleKey) {
            case 'admin':
                $allowedRoles = ['project_manager', 'team_leader'];
                $errorMessage = 'Project Manager Not Found';
                $requireInvitation = false;
                break;

            case 'project_manager':
                $allowedRoles = ['team_leader'];
                $errorMessage = 'Team Leader Not Found';
                $requireInvitation = false;
                break;

            case 'team_leader':
                $allowedRoles = [];
                $errorMessage = 'Team Member Not Found or Not in Your Department';
                $requireInvitation = true;
                break;

            default:
                return response()->json(['status' => false, 'message' => 'Unauthorized role'], Response::HTTP_FORBIDDEN);
        }

        // Fetch users based on role and department
        $assignedUsers = User::with(['roles', 'departments'])
            ->whereIn('id', $userId)
            ->when(! empty($allowedRoles), function ($query) use ($allowedRoles) {
                $query->whereHas('roles', fn ($q) => $q->whereIn('role_key', $allowedRoles));
            })
            ->when($roleKey === 'team_leader', function ($query) use ($user) {
                $departmentIds = $user->departments->pluck('id');
                $query->whereDoesntHave('roles', fn ($q) => $q->where('role_key', 'admin'));
            })
            ->get();

        if ($assignedUsers->isEmpty()) {
            return response()->json(['status' => false, 'message' => $errorMessage], Response::HTTP_BAD_REQUEST);
        }

        // For team leader, check department membership
        $results = [];
        $invitationService = app(\App\Modules\Pms\Services\InvitationService::class);

        foreach ($assignedUsers as $assignee) {
            if ($project->users()->where('user_id', $assignee->id)->exists()) {
                $results[] = [
                    'user_id' => $assignee->id,
                    'status' => 'already_assigned',
                    'message' => 'Project is already assigned to this user',
                ];

                continue;
            }

            // Check if team leader is assigning to someone outside their department
            $isSameDepartment = $user->departments->pluck('id')
                ->intersect($assignee->departments->pluck('id'))
                ->isNotEmpty();

            if ($requireInvitation && ! $isSameDepartment) {
                try {
                    // Send invitation instead of direct assignment
                    $invitation = $invitationService->sendInvitation(
                        $user,
                        $assignee,
                        $project,
                        'team_member' // Default role for invited users
                    );

                    $results[] = [
                        'user_id' => $assignee->id,
                        'status' => 'invitation_sent',
                        'message' => 'Invitation sent to user in different department',
                    ];

                    // Notify user about invitation
                    // $assignee->notify(new \App\Modules\Pms\Notifications\InvitationNotification($invitation));

                } catch (\Exception $e) {
                    $results[] = [
                        'user_id' => $assignee->id,
                        'status' => 'error',
                        'message' => $e->getMessage(),
                    ];
                }

                continue;
            }

            // Direct assignment for same department or non-team-leader cases
            $project->users()->attach($assignee->id, ['assigned_by' => $user->id]);

            $results[] = [
                'user_id' => $assignee->id,
                'status' => 'assigned',
                'message' => 'Project assigned successfully',
            ];

            $assignee->notify(new \App\Modules\Pms\Notifications\ProjectAssignedNotification([
                'project' => $project,
                'user_id' => $assignee->id,
                'message' => 'Project assigned to you',
            ]));
        }

        // Convert results to collection
        $resultsCollection = collect($results);

        // Categorize results
        $assignedResults = $resultsCollection->where('status', 'assigned');
        $invitationResults = $resultsCollection->where('status', 'invitation_sent');
        $failedResults = $resultsCollection->whereNotIn('status', ['assigned', 'invitation_sent']);

        // Generate specific success messages
        $successMessages = [];
        if ($assignedResults->isNotEmpty()) {
            $successMessages[] = 'Project assigned successfully';
        }
        if ($invitationResults->isNotEmpty()) {
            $successMessages[] = 'Invitation sent successfully';
        }

        // Generate error messages
        $errorMessages = $failedResults->pluck('message')->unique();

        // Prepare final response
        if ($failedResults->isEmpty()) {
            // All successful
            $message = implode(' and ', $successMessages);
            $statusCode = Response::HTTP_OK;
        } elseif ($assignedResults->isEmpty() && $invitationResults->isEmpty()) {
            // All failed
            $message = 'Operation failed: '.$errorMessages->implode('. ');
            $statusCode = Response::HTTP_BAD_REQUEST;
        } else {
            // Partial success
            $message = implode(' and ', $successMessages).
                '. However, some operations failed: '.
                $errorMessages->implode('. ');
            $statusCode = Response::HTTP_OK;
        }

        return response()->json([
            'status' => $failedResults->isEmpty(),
            'message' => $message,
            'results' => $results,
            'summary' => [
                'assigned_count' => $assignedResults->count(),
                'invitation_sent_count' => $invitationResults->count(),
                'failed_count' => $failedResults->count(),
                'failed_messages' => $errorMessages,
            ],
        ], $statusCode);
    }

    public function create()
    {
        //
    }

    public function store(Request $request)
    {
        PmsAccess::requirePermission(request(), 'project-add');
        $request->merge(['name' => preg_replace('/\s+/u', ' ', trim((string) $request->input('name')))]);

        $validatedData = $request->validate([
            'name' => ['required', 'string', 'max:255', function ($attribute, $value, $fail) {
                if (Projects::where('name_key', Projects::normalizeName($value))->exists()) {
                    $fail('A project with this name already exists.');
                }
            }],
            'company_name' => ['nullable', 'string', 'max:255'],
            'url' => ['nullable', 'url', 'max:2048'],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'file', 'mimes:jpeg,png,jpg,webp,svg', 'max:2048'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,csv,jpg,jpeg,png,txt', 'max:10240'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'workflow_ids' => ['required', 'array', 'min:1'],
            'workflow_ids.*' => ['exists:pms_workflows,id'],
            'department_ids' => ['nullable', 'array'],
            'department_ids.*' => ['integer', 'distinct', 'exists:pms_departments,id'],
        ], [
            'end_date.after_or_equal' => 'End Date must be the same as or later than the Start Date.',
        ]);

        try {
            if ($request->hasFile('image')) {
                $imagePath = $request->file('image')->store('projects', 'public');
                $imageUrl = asset('storage/'.$imagePath);
            } else {
                // Set a default image path if no image is uploaded from public img
                $imageUrl = asset('project.png');
            }
            $attachmentUrl = null;
            if ($request->hasFile('attachment')) {
                $file = $request->file('attachment');
                $extension = $file->getClientOriginalExtension(); // Get the original file extension
                $filename = time().'.'.$extension; // Generate a unique filename
                $attachmentPath = $file->storeAs('projects', $filename, 'public'); // Store with original extension
                $attachmentUrl = asset('storage/'.$attachmentPath);
            }

            // for local development sotrage path set

            // if ($request->hasFile('image') && $request->file('image')->isValid()) {
            //     $imagePath = $request->file('image')->move(public_path('uploads/projects'), time() . '_' . $request->file('image')->getClientOriginalName());
            //     $imageUrl = asset('uploads/projects/' . basename($imagePath));
            // } else {
            //     $imageUrl = asset('project.png');
            // }

            $companyId = ! empty($validatedData['company_name'])
                ? Company::firstOrCreate(['name' => trim($validatedData['company_name'])])->id
                : null;
            $project = Projects::create([
                'company_id' => $companyId,
                'name' => $validatedData['name'],
                'description' => $validatedData['description'] ?? null,
                'image' => $imageUrl,
                'attachment' => $attachmentUrl,
                'url' => $validatedData['url'] ?? null,
                'start_date' => $validatedData['start_date'],
                'end_date' => $validatedData['end_date'] ?? null,
            ]);
            $workflowIds = $validatedData['workflow_ids'] ?? Workflow::where('is_default', true)->pluck('id')->all();
            $project->workflows()->sync($workflowIds);
            $project->departments()->sync($validatedData['department_ids'] ?? []);

            $user = Auth::user();
            if ($user->hasRole('project_manager') || $user->hasRole('team_leader')) {
                $project->users()->attach($user->id, [
                    'assigned_by' => $user->id,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Project created successfully',
                'project_id' => $project->id,
                'redirect_url' => route('pms_projects.show', $project->id),
            ], Response::HTTP_CREATED);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Unable to create project. Please try again.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show(string $id)
    {
        PmsAccess::requirePermission(request(), 'project-view');
        $user = Auth::user();
        $project = Projects::with('company:id,name')->findOrFail($id);

        if (! $user->hasRole('admin')) {
            if ($project->approval !== 'approved') {
                $message = 'Project is not approved yet';

                return view('dashboard.custom-pages.message', compact('message'));
            }

            $hasProjectAccess = $project->users()->where('users.id', Auth::id())->exists();
            if (! $user->hasRole('project_manager') && ! $user->hasRole('team_leader')) {
                $hasProjectAccess = $hasProjectAccess
                    || $project->tasks()->whereHas('users', fn (Builder $users) => $users->where('users.id', Auth::id()))->exists()
                    || $project->tasks()->whereHas('subtasks.users', fn (Builder $users) => $users->where('users.id', Auth::id()))->exists();
            }
            if (! $hasProjectAccess) {
                abort(404);
            }
        }
        // Board tasks are loaded below with the exact sprint and visibility filters.
        // Avoid loading the complete task/subtask tree here a second time.
        if ($user->hasRole('admin') || $user->hasRole('project_manager')) {
            $asignee = User::query()
                ->where('status', 1)
                ->whereDoesntHave('roles', fn ($query) => $query->where('role_key', 'admin'))
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'profile_img']);
        } elseif ($user->hasRole('team_leader')) {
            $teamLeaderDepartmentId = $user->departments->pluck('id');
            $asignee = User::query()
                ->where('status', 1)
                ->whereDoesntHave('roles', fn ($query) => $query->where('role_key', 'admin'))
                ->whereHas('departments', fn ($query) => $query->whereIn('pms_departments.id', $teamLeaderDepartmentId))
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'profile_img']);
        } else {
            $asignee = null;
        }

        $sprints = $project->sprints()->withCount('tasks')->get();
        $workflows = $project->workflows()->where('is_active', true)->with(['columns', 'department:id,dept_name'])->orderBy('name')->get();
        if ($workflows->isEmpty()) {
            $defaultWorkflow = Workflow::where('is_default', true)->where('is_active', true)->with('columns')->first();
            if ($defaultWorkflow) {
                $project->workflows()->syncWithoutDetaching([$defaultWorkflow->id]);
                $workflows = collect([$defaultWorkflow]);
            }
        }
        $selectedWorkflow = $workflows->firstWhere('id', (int) request()->query('workflow')) ?? $workflows->first();
        $requestedSprint = request()->query('sprint');
        // Do not open an empty active sprint while the project has backlog work.
        // Prefer an active sprint containing tasks; otherwise default to Backlog.
        // Empty active sprints remain available in the selector.
        $activeSprint = $sprints
            ->where('status', 'active')
            ->first(fn (Sprint $sprint) => (int) $sprint->tasks_count > 0);
        $selectedSprint = null;

        if ($requestedSprint && $requestedSprint !== 'backlog') {
            $selectedSprint = $sprints->firstWhere('id', (int) $requestedSprint);
        } elseif ($requestedSprint === null) {
            $selectedSprint = $activeSprint;
        }

        $boardTasks = Tasks::query()
            ->where('project_id', $project->id)
            ->when($selectedWorkflow, fn ($query) => $query->where('workflow_id', $selectedWorkflow->id))
            ->when(
                ! $user->hasRole('admin') && ! $user->hasRole('project_manager') && ! $user->hasRole('team_leader'),
                fn (Builder $query) => $query->where(fn (Builder $tasks) => $tasks
                    ->whereHas('users', fn (Builder $users) => $users->where('users.id', $user->id))
                    ->orWhereHas('subtasks.users', fn (Builder $users) => $users->where('users.id', $user->id)))
            )
            ->when($selectedSprint, fn ($query) => $query->where('sprint_id', $selectedSprint->id))
            ->when(! $selectedSprint, fn ($query) => $query->whereNull('sprint_id'))
            ->with(['incomingHandoff.sourceDepartment:id,dept_name', 'outgoingHandoffs.destinationDepartment:id,dept_name', 'users:id,name,profile_img', 'subtasks:id,task_id,status', 'timerLogs:id,task_id,user_id,start_time,end_time'])
            ->withCount(['comments', 'attachments'])
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        $sprintStats = [
            'total' => $sprints->count(),
            'planned' => $sprints->where('status', 'planned')->count(),
            'active' => $sprints->where('status', 'active')->count(),
            'completed' => $sprints->where('status', 'completed')->count(),
        ];

        $workloadUsers = User::query()
            ->whereHas('projects', fn ($query) => $query->where('pms_projects.id', $project->id))
            ->with(['tasks' => fn ($query) => $query->where('project_id', $project->id)
                ->when($selectedSprint, fn ($taskQuery) => $taskQuery->where('sprint_id', $selectedSprint->id))
                ->when(! $selectedSprint, fn ($taskQuery) => $taskQuery->whereNull('sprint_id'))
                ->select('pms_tasks.id', 'pms_tasks.project_id', 'pms_tasks.sprint_id', 'pms_tasks.title', 'pms_tasks.status')])
            ->withCount([
                'tasks as project_tasks_count' => fn ($query) => $query->where('project_id', $project->id),
                'tasks as sprint_tasks_count' => fn ($query) => $query->where('project_id', $project->id)
                    ->when($selectedSprint, fn ($taskQuery) => $taskQuery->where('sprint_id', $selectedSprint->id))
                    ->when(! $selectedSprint, fn ($taskQuery) => $taskQuery->whereNull('sprint_id')),
            ])
            ->orderBy('name')
            ->get(['id', 'name', 'profile_img']);

        return view('dashboard.projects.tasks', compact('project', 'asignee', 'sprints', 'selectedSprint', 'boardTasks', 'sprintStats', 'workloadUsers', 'workflows', 'selectedWorkflow'));
    }

    public function edit(string $id)
    {
        PmsAccess::requirePermission(request(), 'project-edit');
        $project = Projects::with('company:id,name')->findOrFail($id);

        return response()->json(['data' => $project, 'workflow_ids' => $project->workflows()->pluck('pms_workflows.id'), 'department_ids' => $project->departments()->pluck('pms_departments.id')]);
    }

    public function update(Request $request, string $id)
    {
        PmsAccess::requirePermission(request(), 'project-edit');
        $request->merge(['name' => preg_replace('/\s+/u', ' ', trim((string) $request->input('name')))]);

        $validatedData = $request->validate([
            'name' => ['required', 'string', 'max:255', function ($attribute, $value, $fail) use ($id) {
                if (Projects::where('name_key', Projects::normalizeName($value))->whereKeyNot($id)->exists()) {
                    $fail('A project with this name already exists.');
                }
            }],
            'company_name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'url' => ['nullable', 'url', 'max:2048'],
            'image' => ['nullable', 'file', 'mimes:jpeg,png,jpg,webp,svg', 'max:2048'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,csv,jpg,jpeg,png,txt', 'max:10240'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'workflow_ids' => ['required', 'array', 'min:1'],
            'workflow_ids.*' => ['exists:pms_workflows,id'],
            'department_ids' => ['nullable', 'array'],
            'department_ids.*' => ['integer', 'distinct', 'exists:pms_departments,id'],
        ], [
            'end_date.after_or_equal' => 'End Date must be the same as or later than the Start Date.',
        ]);

        try {
            if ($request->image != null) {
                $imagePath = $request->file('image')->store('projects', 'public');
                $imageUrl = asset('storage/'.$imagePath);
            }

            if ($request->hasFile('attachment')) {
                $file = $request->file('attachment');
                $extension = $file->getClientOriginalExtension();
                $filename = time().'.'.$extension;
                $attachmentPath = $file->storeAs('projects', $filename, 'public');
                $attachmentUrl = asset('storage/'.$attachmentPath);
            }
            // update the
            $project = Projects::findOrFail($id);
            $project->company_id = ! empty($validatedData['company_name'])
                ? Company::firstOrCreate(['name' => trim($validatedData['company_name'])])->id
                : null;
            $project->name = $validatedData['name'];
            $project->description = $validatedData['description'] ?? null;
            if ($request->image != null) {
                $project->image = $imageUrl;
            }
            if ($request->hasFile('attachment')) {
                $project->attachment = $attachmentUrl;
            }
            $project->url = $validatedData['url'] ?? null;
            $project->start_date = $validatedData['start_date'];
            $project->end_date = $validatedData['end_date'] ?? null;
            $project->save();
            if (array_key_exists('workflow_ids', $validatedData)) {
                $project->workflows()->sync($validatedData['workflow_ids']);
            }
            if ($request->boolean('sync_departments')) {
                $project->departments()->sync($validatedData['department_ids'] ?? []);
            }

            return response()->json(['success' => true, 'message' => 'Project updated successfully'], Response::HTTP_OK);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['status' => false, 'message' => 'Unable to update project. Please try again.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(string $id)
    {
        PmsAccess::requirePermission(request(), 'project-trash');
        $project = Projects::findOrFail($id);

        // Delete related notifications before deleting the project
        DB::table('pms_notifications')
            ->whereJsonContains('data->task->project_id', (int) $id)
            ->delete();
        $project->delete();
        if ($project) {
            return response()->json(['status' => true, 'message' => 'Project deleted Successfully!'], Response::HTTP_OK);
        } else {
            return response()->json(['status' => false, 'message' => 'Project Failed to Delete!'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function RemoveAssignUser(Request $request)
    {
        PmsAccess::requirePermission(request(), 'project-assign');
        $project = Projects::findOrFail($request->project_id);
        $project->users()->detach($request->user_id);
        // notify
        $userNotifier = User::findOrFail($request->user_id);
        $userNotifier->notify(new \App\Modules\Pms\Notifications\ProjectAssignedNotification(['project' => $project, 'user_id' => $userNotifier->id, 'message' => 'Project removed from you']));

        return response()->json(['status' => true, 'message' => 'User removed from Project Successfully!'], Response::HTTP_OK);
    }

    // public function GetAllTasks(string $id)
    // {
    //     $user = Auth::user();

    //     if ($user->hasRole('admin') || $user->hasRole('project_manager')) {
    //         $project = Projects::with(['tasks.subtasks.users', 'tasks.users'])->findOrFail($id);
    //         $asignee = User::whereHas('roles', function ($query) {
    //             $query->whereNotIn('role_key', ['admin', 'project_manager', 'team_leader']);
    //         })->get();
    //     } elseif ($user->hasRole('team_leader')) {
    //         $project = Projects::with(['tasks.subtasks.users', 'tasks.users'])->findOrFail($id);
    //         $teamLeaderDepartmentId = $user->departments->pluck('id');
    //         $filteredUsers = User::with('roles')
    //             ->whereHas('departments', function ($query) use ($teamLeaderDepartmentId) {
    //                 $query->whereIn('pms_departments.id', $teamLeaderDepartmentId);
    //             })
    //             ->whereDoesntHave('roles', function ($query) {
    //                 $query->where('role_key', 'team_leader');
    //             })
    //             ->get();
    //             // dd($project->toArray());

    //         $asignee = $filteredUsers;
    //     } else {
    //         $project = Projects::with([
    //             'tasks' => function ($query) use ($user) {
    //                 $query->whereHas('users', function ($q) use ($user) {
    //                     $q->where('user_id', $user->id);
    //                 })->with(['subtasks', 'users']);
    //             },

    //         ])->findOrFail($id);
    //         $asignee = null;
    //     }
    //     // dd($project->toArray());

    //     return response()->json([
    //         'asignee' => $asignee,
    //         'project' => $project
    //     ]);
    // }

    public function GetAllTasks(string $id)
    {
        $user = Auth::user();
        $projectId = $id;
        $userId = $user->id;

        // Fetch running timers for the user
        $runningTimers = TimerLog::where('user_id', $userId)
            ->whereNotNull('start_time')
            ->whereNull('end_time')
            ->whereHas('task', function ($query) use ($projectId) {
                $query->where('project_id', $projectId);
            })
            ->select('id', 'task_id', 'subtask_id', 'user_id')
            ->get()
            ->map(function ($timer) {
                return [
                    'task_id' => $timer->task_id,
                    'subtask_id' => $timer->subtask_id,
                    'user_id' => $timer->user_id,
                ];
            });

        // Initialize tasks and asignee based on role
        if ($user->hasRole('admin') || $user->hasRole('project_manager')) {
            // Admin or Project Manager: Show all tasks and subtasks
            $tasks = Tasks::where('project_id', $projectId)
                ->with([
                    'assignees' => function ($query) {
                        $query->select('users.id', 'name', 'profile_img');
                    },
                    'subtasks' => function ($query) use ($userId) {
                        $query->with([
                            'timerLogs' => function ($query) use ($userId) {
                                $query->where('user_id', $userId)
                                    ->select('id', 'task_id', 'subtask_id', 'user_id', 'start_time', 'end_time');
                            },
                            'assignees' => function ($query) {
                                $query->select('users.id', 'name', 'profile_img');
                            },
                        ]);
                    },
                    'timerLogs' => function ($query) use ($userId) {
                        $query->where('user_id', $userId)
                            ->select('id', 'task_id', 'subtask_id', 'user_id', 'start_time', 'end_time');
                    },
                ])
                ->select('id', 'project_id', 'title', 'due_date', 'priority', 'status', 'invest_time')
                ->get();

            // Assignees: Exclude admin, project_manager, team_leader
            $asignee = User::whereHas('roles', function ($query) {
                $query->whereNotIn('role_key', ['admin']);
            })
                ->select('id', 'name', 'profile_img', 'designation')
                ->get()
                ->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'profile_img' => $user->profile_img ?? '/images/default-avatar.png',
                        'designation' => $user->designation,
                        'roles' => $user->roles->map(function ($role) {
                            return [
                                'role_key' => $role->role_key,
                                'role_name' => $role->role_name,
                            ];
                        }),
                    ];
                });
        } elseif ($user->hasRole('team_leader')) {
            // Team Leader: Show all tasks and subtasks
            $tasks = Tasks::where('project_id', $projectId)
                ->with([
                    'assignees' => function ($query) {
                        $query->select('users.id', 'name', 'profile_img');
                    },
                    'subtasks' => function ($query) use ($userId) {
                        $query->with([
                            'timerLogs' => function ($query) use ($userId) {
                                $query->where('user_id', $userId)
                                    ->select('id', 'task_id', 'subtask_id', 'user_id', 'start_time', 'end_time');
                            },
                            'assignees' => function ($query) {
                                $query->select('users.id', 'name', 'profile_img');
                            },
                        ]);
                    },
                    'timerLogs' => function ($query) use ($userId) {
                        $query->where('user_id', $userId)
                            ->select('id', 'task_id', 'subtask_id', 'user_id', 'start_time', 'end_time');
                    },
                ])
                ->select('id', 'project_id', 'title', 'due_date', 'priority', 'status', 'invest_time')
                ->get();

            // Assignees: Users in team leader's department, excluding other team leaders
            $teamLeaderDepartmentId = $user->departments->pluck('id');
            $asignee = User::with('roles')
                ->whereHas('departments', function ($query) use ($teamLeaderDepartmentId) {
                    $query->whereIn('pms_departments.id', $teamLeaderDepartmentId);
                })
                ->whereDoesntHave('roles', function ($query) {
                    $query->where('role_key', 'team_leader');
                })
                ->select('id', 'name', 'profile_img', 'designation')
                ->get()
                ->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'profile_img' => $user->profile_img ?? '/images/default-avatar.png',
                        'designation' => $user->designation,
                        'roles' => $user->roles->map(function ($role) {
                            return [
                                'role_key' => $role->role_key,
                                'role_name' => $role->role_name,
                            ];
                        }),
                    ];
                });
        } else {
            // Other roles: Show only tasks and subtasks assigned to the user
            $tasks = Tasks::where('project_id', $projectId)
                ->where(function ($query) use ($userId) {
                    $query->whereHas('assignees', function ($q) use ($userId) {
                        $q->where('user_id', $userId);
                    })
                        ->orWhereHas('subtasks', function ($subQuery) use ($userId) {
                            $subQuery->whereHas('assignees', function ($q) use ($userId) {
                                $q->where('user_id', $userId);
                            });
                        });
                })
                ->with([
                    'assignees' => function ($query) {
                        $query->select('users.id', 'name', 'profile_img');
                    },
                    'subtasks' => function ($query) use ($userId) {
                        $query->whereHas('assignees', function ($q) use ($userId) {
                            $q->where('user_id', $userId);
                        })->with([
                            'timerLogs' => function ($query) use ($userId) {
                                $query->where('user_id', $userId)
                                    ->select('id', 'task_id', 'subtask_id', 'user_id', 'start_time', 'end_time');
                            },
                            'assignees' => function ($query) {
                                $query->select('users.id', 'name', 'profile_img');
                            },
                        ]);
                    },
                    'timerLogs' => function ($query) use ($userId) {
                        $query->where('user_id', $userId)
                            ->select('id', 'task_id', 'subtask_id', 'user_id', 'start_time', 'end_time');
                    },
                ])
                ->select('id', 'project_id', 'title', 'due_date', 'priority', 'status', 'invest_time')
                ->get();

            $asignee = null;
        }

        // Map tasks to the desired response format
        $tasks = $tasks->map(function ($task) {
            return [
                'id' => $task->id,
                'title' => $task->title,
                'due_date' => $task->due_date,
                'priority' => $task->priority,
                'status' => $task->status,
                'invest_time' => $task->invest_time,
                'timerLogs' => $task->timerLogs->map(function ($log) {
                    return [
                        'id' => $log->id,
                        'task_id' => $log->task_id,
                        'subtask_id' => $log->subtask_id,
                        'user_id' => $log->user_id,
                        'start_time' => $log->start_time,
                        'end_time' => $log->end_time,
                    ];
                }),
                'users' => $task->assignees->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'profile_img' => $user->profile_img ?? '/images/default-avatar.png',
                    ];
                }),
                'subtasks' => $task->subtasks->map(function ($subtask) {
                    return [
                        'id' => $subtask->id,
                        'title' => $subtask->title,
                        'description' => $subtask->description,
                        'due_date' => $subtask->due_date,
                        'priority' => $subtask->priority,
                        'status' => $subtask->status,
                        'task_id' => $subtask->task_id,
                        'invest_time' => $subtask->invest_time,
                        'timerLogs' => $subtask->timerLogs->map(function ($log) {
                            return [
                                'id' => $log->id,
                                'task_id' => $log->task_id,
                                'subtask_id' => $log->subtask_id,
                                'user_id' => $log->user_id,
                                'start_time' => $log->start_time,
                                'end_time' => $log->end_time,
                            ];
                        }),
                        'users' => $subtask->assignees->map(function ($user) {
                            return [
                                'id' => $user->id,
                                'name' => $user->name,
                                'profile_img' => $user->profile_img ?? '/images/default-avatar.png',
                            ];
                        }),
                    ];
                }),
            ];
        });

        return response()->json([
            'success' => true,
            'asignee' => $asignee,
            'project' => ['tasks' => $tasks],
            'runningTimers' => $runningTimers,
        ]);
    }

    // private function calculateInvestTime($timerLogs)
    // {
    //     $totalSeconds = $timerLogs->whereNotNull('end_time')->sum(function ($log) {
    //         // Convert start_time and end_time to Carbon instances
    //         $startTime = is_string($log->start_time) ? Carbon::parse($log->start_time) : $log->start_time;
    //         $endTime = is_string($log->end_time) ? Carbon::parse($log->end_time) : $log->end_time;

    //         return $endTime->diffInSeconds($startTime);
    //     });

    //     $days = floor($totalSeconds / (24 * 3600));
    //     $totalSeconds %= (24 * 3600);
    //     $hours = floor($totalSeconds / 3600);
    //     $totalSeconds %= 3600;
    //     $minutes = floor($totalSeconds / 60);

    //     return "$days days, $hours hours, $minutes minutes";
    // }

    private function calculateInvestTime($timerLogs)
    {
        $totalSeconds = $timerLogs->whereNotNull('end_time')->sum(function ($log) {
            $startTime = is_string($log->start_time) ? Carbon::parse($log->start_time) : $log->start_time;
            $endTime = is_string($log->end_time) ? Carbon::parse($log->end_time) : $log->end_time;

            return $endTime->diffInSeconds($startTime);
        });

        return CarbonInterval::seconds($totalSeconds)->cascade()->forHumans();
    }

    public function updateProjectStatus(Request $request)
    {
        PmsAccess::requirePermission(request(), 'project-edit');
        $validated = $request->validate([
            'id' => ['required', 'exists:pms_projects,id'],
            'status' => ['required', 'in:pending,progress,completed,delivered,cancelled'],
        ]);
        $project = Projects::findOrFail($validated['id']);
        $project->status = $validated['status'];
        $project->save();

        return response()->json(['status' => true, 'message' => 'Project Status Updated Successfully!'], Response::HTTP_OK);
    }

    public function updateProjectApprovalStatus(Request $request, string $id)
    {
        PmsAccess::requirePermission(request(), 'project-edit');
        $validated = $request->validate([
            'status' => ['required', 'in:pending,approved,rejected'],
        ]);
        $project = Projects::findOrFail($id);
        $project->approval = $validated['status'];
        $project->save();

        return response()->json(['status' => true, 'message' => 'Project Approved Successfully!'], Response::HTTP_OK);
    }
}
