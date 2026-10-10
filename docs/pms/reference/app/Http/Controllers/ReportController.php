<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Department;
use App\Models\Projects;
use App\Models\Subtasks;
use App\Models\Tasks;
use App\Models\TimerLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;

class ReportController extends Controller
{
    use AuthorizesRequests;

    public function index()
    {
        $this->authorize('report-view');

        $user = auth()->user();
        $canViewAll = $this->canViewAllReports($user);
        $departmentIds = $this->reportDepartmentIds($user);

        $projectsQuery = Projects::query()->select('id', 'company_id', 'name');
        if (! $canViewAll) {
            $projectsQuery->whereHas('tasks', function (Builder $tasks) use ($user, $departmentIds): void {
                $this->applyTaskVisibility($tasks, $user, $departmentIds);
            });
        }
        $projects = $projectsQuery->orderBy('name')->get();

        $users = User::query()
            ->select('id', 'name')
            ->when(! $canViewAll && $user->hasRole('team_leader'), fn (Builder $query) => $query
                ->whereHas('departments', fn (Builder $departments) => $departments->whereIn('departments.id', $departmentIds)))
            ->when(! $canViewAll && ! $user->hasRole('team_leader'), fn (Builder $query) => $query->whereKey($user->id))
            ->orderBy('name')
            ->get();

        $companies = Company::where('status', true)
            ->whereHas('projects', fn ($query) => $query->whereIn('projects.id', $projects->pluck('id')))
            ->orderBy('name')
            ->get(['id', 'name']);

        $departments = Department::query()
            ->when(! $canViewAll, fn (Builder $query) => $query->whereIn('id', $departmentIds))
            ->orderBy('dept_name')
            ->get(['id', 'dept_name']);

        return view('dashboard.reports.index', compact('projects', 'users', 'companies', 'departments'));
    }

    public function overview(Request $request)
    {
        $this->authorize('report-view');
        $filters = $request->validate([
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            'deadline_health' => ['nullable', 'in:on_track,overdue,met,late,no_deadline'],
        ]);
        $this->enforceReportFilterAccess($filters);

        $tasks = $this->filteredTasks($filters);
        $total = (clone $tasks)->count();
        $completed = (clone $tasks)->where('tasks.status', 'completed')->count();
        $overdue = (clone $tasks)->whereNotNull('tasks.due_date')->whereDate('tasks.due_date', '<', today())
            ->where('tasks.status', '!=', 'completed')->count();
        $dueSoon = (clone $tasks)->whereNotNull('tasks.due_date')->where('tasks.status', '!=', 'completed')
            ->whereBetween('tasks.due_date', [today(), today()->addDays(7)->endOfDay()])->count();

        $taskIds = (clone $tasks)->pluck('tasks.id');
        $loggedSeconds = $taskIds->isEmpty() ? 0 : (int) TimerLog::whereIn('task_id', $taskIds)
            ->when($filters['user_id'] ?? null, fn ($q, $id) => $q->where('user_id', $id))
            ->when($filters['from_date'] ?? null, fn ($q, $date) => $q->whereDate('start_time', '>=', $date))
            ->when($filters['to_date'] ?? null, fn ($q, $date) => $q->whereDate('start_time', '<=', $date))
            ->whereNotNull('end_time')->get(['start_time', 'end_time'])
            ->sum(fn ($log) => $log->start_time->diffInSeconds($log->end_time));

        $departments = $this->filteredTasks($filters)
            ->leftJoin('departments', 'departments.id', '=', 'tasks.department_id')
            ->selectRaw("COALESCE(departments.dept_name, 'Unassigned') as department")
            ->selectRaw('COUNT(tasks.id) as total')
            ->selectRaw("SUM(CASE WHEN tasks.status = 'completed' THEN 1 ELSE 0 END) as completed")
            ->selectRaw("SUM(CASE WHEN tasks.due_date < ? AND tasks.status <> 'completed' THEN 1 ELSE 0 END) as overdue", [now()])
            ->groupBy('departments.id', 'departments.dept_name')->orderByDesc('total')->get()
            ->map(fn ($row) => [
                'department' => $row->department,
                'total' => (int) $row->total,
                'completed' => (int) $row->completed,
                'overdue' => (int) $row->overdue,
                'progress' => $row->total ? round(($row->completed / $row->total) * 100, 1) : 0,
            ]);

        return response()->json([
            'summary' => compact('total', 'completed', 'overdue', 'dueSoon', 'loggedSeconds') + [
                'progress' => $total ? round(($completed / $total) * 100, 1) : 0,
            ],
            'departments' => $departments,
        ]);
    }

    private function filteredTasks(array $filters): Builder
    {
        $query = Tasks::query()
            ->join('projects', 'projects.id', '=', 'tasks.project_id')
            ->when($filters['company_id'] ?? null, fn ($q, $id) => $q->where('projects.company_id', $id))
            ->when($filters['project_id'] ?? null, fn ($q, $id) => $q->where('tasks.project_id', $id))
            ->when($filters['department_id'] ?? null, fn ($q, $id) => $q->where('tasks.department_id', $id))
            ->when($filters['user_id'] ?? null, fn ($q, $id) => $q->whereHas('timerLogs', fn ($logs) => $logs->where('user_id', $id)))
            ->when(($filters['from_date'] ?? null) || ($filters['to_date'] ?? null), function ($q) use ($filters) {
                $q->whereHas('timerLogs', fn ($logs) => $logs
                    ->when($filters['from_date'] ?? null, fn ($l, $date) => $l->whereDate('start_time', '>=', $date))
                    ->when($filters['to_date'] ?? null, fn ($l, $date) => $l->whereDate('start_time', '<=', $date)));
            });

        $this->applyTaskVisibility($query);

        return $this->applyDeadlineHealth($query, $filters['deadline_health'] ?? null);
    }

    private function canViewAllReports(?User $user = null): bool
    {
        $user ??= auth()->user();

        return $user->hasRole('admin') || $user->hasRole('project_manager');
    }

    private function reportDepartmentIds(?User $user = null)
    {
        $user ??= auth()->user();

        return $user->departments()->pluck('departments.id');
    }

    private function enforceReportFilterAccess(array $filters): void
    {
        $user = auth()->user();

        if ($this->canViewAllReports($user)) {
            return;
        }

        $departmentIds = $this->reportDepartmentIds($user);

        if (($filters['department_id'] ?? null) && ! $departmentIds->contains((int) $filters['department_id'])) {
            abort(403, 'You cannot view reports for this department.');
        }

        if (! ($filters['user_id'] ?? null)) {
            return;
        }

        $canViewUser = $user->hasRole('team_leader')
            ? User::query()
                ->whereKey($filters['user_id'])
                ->whereHas('departments', fn (Builder $departments) => $departments
                    ->whereIn('departments.id', $departmentIds))
                ->exists()
            : (int) $filters['user_id'] === (int) $user->id;

        abort_unless($canViewUser, 403, 'You cannot view reports for this employee.');
    }

    private function applyTaskVisibility(
        Builder $query,
        ?User $user = null,
        $departmentIds = null
    ): void {
        $user ??= auth()->user();

        if ($this->canViewAllReports($user)) {
            return;
        }

        if ($user->hasRole('team_leader')) {
            $departmentIds ??= $this->reportDepartmentIds($user);
            $query->whereIn('tasks.department_id', $departmentIds);

            return;
        }

        $query->where(function (Builder $tasks) use ($user): void {
            $tasks->whereHas('users', fn (Builder $users) => $users->where('users.id', $user->id))
                ->orWhereHas('subtasks.users', fn (Builder $users) => $users->where('users.id', $user->id));
        });
    }

    private function applySubtaskVisibility(
        Builder $query,
        ?User $user = null,
        $departmentIds = null
    ): void {
        $user ??= auth()->user();

        if ($this->canViewAllReports($user)) {
            return;
        }

        if ($user->hasRole('team_leader')) {
            $departmentIds ??= $this->reportDepartmentIds($user);
            $query->whereHas('task', fn (Builder $tasks) => $tasks->whereIn('tasks.department_id', $departmentIds));

            return;
        }

        $query->whereHas('users', fn (Builder $users) => $users->where('users.id', $user->id));
    }

    private function applyDeadlineHealth(Builder $query, ?string $health): Builder
    {
        return $query->when($health, function ($q, $health) {
            match ($health) {
                'overdue' => $q->whereNotNull('tasks.due_date')->whereDate('tasks.due_date', '<', today())->where('tasks.status', '!=', 'completed'),
                'on_track' => $q->whereNotNull('tasks.due_date')->whereDate('tasks.due_date', '>=', today())->where('tasks.status', '!=', 'completed'),
                'met' => $q->where('tasks.status', 'completed')->whereColumn('tasks.updated_at', '<=', 'tasks.due_date'),
                'late' => $q->where('tasks.status', 'completed')->whereColumn('tasks.updated_at', '>', 'tasks.due_date'),
                'no_deadline' => $q->whereNull('tasks.due_date'),
            };
        });
    }

    public function getReportData(Request $request)
    {
        $this->authorize('report-view');
        $filters = $request->validate([
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            'deadline_health' => ['nullable', 'in:on_track,overdue,met,late,no_deadline'],
        ]);
        $this->enforceReportFilterAccess($filters);

        $project_id = $request->input('project_id');
        $task_id = $request->input('task_id');
        $subtask_id = $request->input('subtask_id');
        $draw = $request->input('draw');
        $start = $request->input('start', 0);
        $length = $request->input('length', 10);
        $search = $request->input('search.value');
        $order = $request->input('order.0', ['column' => 0, 'dir' => 'asc']);
        $columnIndex = $order['column'];
        $sortDirection = $order['dir'];

        // Map DataTable column index to database column
        $columns = [1 => 'company_name', 2 => 'project_name', 3 => 'department_name', 4 => 'title',
            5 => 'type', 6 => 'status', 7 => 'deadline_health', 8 => 'due_date', 9 => 'assignee',
            10 => 'worked_by', 11 => 'invest_time'];
        $sortColumn = isset($columns[$columnIndex]) ? $columns[$columnIndex] : 'created_at';

        // Initialize collections for tasks and subtasks
        $tasks = collect();
        $subtasks = collect();

        if ($subtask_id) {
            // Only fetch the specific subtask
            $subtasksQuery = Subtasks::where('id', $subtask_id)
                ->with(['assignees:id,name', 'task.project.company:id,name', 'task.department:id,dept_name', 'timerLogs.user:id,name']);

            $this->applySubtaskReportFilters($subtasksQuery, $filters);

            // Apply search filter on query if search is provided
            if ($search) {
                $subtasksQuery->where(function ($query) use ($search) {
                    $query->where('title', 'like', '%'.$search.'%')
                        ->orWhereHas('task.project', function ($q) use ($search) {
                            $q->where('name', 'like', '%'.$search.'%');
                        })
                        ->orWhereHas('createdBy', function ($q) use ($search) {
                            $q->where('name', 'like', '%'.$search.'%');
                        })
                        ->orWhereHas('completedBy', function ($q) use ($search) {
                            $q->where('name', 'like', '%'.$search.'%');
                        })
                        ->orWhereHas('assignees', function ($q) use ($search) {
                            $q->where('name', 'like', '%'.$search.'%');
                        })
                        ->orWhere('status', 'like', '%'.$search.'%');
                });
            }

            $subtasks = $subtasksQuery->get()->map(fn ($subtask) => $this->subtaskReportRow($subtask, $filters));
        } else {
            // Fetch tasks
            $tasksQuery = Tasks::with(['assignees:id,name', 'project.company:id,name', 'department:id,dept_name', 'timerLogs.user:id,name']);
            $this->applyTaskReportFilters($tasksQuery, $filters);
            if ($project_id) {
                $tasksQuery->where('project_id', $project_id);
            }
            if ($task_id) {
                $tasksQuery->where('id', $task_id);
            }

            // Apply search filter on tasks query
            if ($search) {
                $tasksQuery->where(function ($query) use ($search) {
                    $query->where('title', 'like', '%'.$search.'%')
                        ->orWhereHas('project', function ($q) use ($search) {
                            $q->where('name', 'like', '%'.$search.'%');
                        })
                        ->orWhereHas('createdBy', function ($q) use ($search) {
                            $q->where('name', 'like', '%'.$search.'%');
                        })
                        ->orWhereHas('completedBy', function ($q) use ($search) {
                            $q->where('name', 'like', '%'.$search.'%');
                        })
                        ->orWhereHas('assignees', function ($q) use ($search) {
                            $q->where('name', 'like', '%'.$search.'%');
                        })
                        ->orWhere('status', 'like', '%'.$search.'%');
                });
            }

            $tasks = $tasksQuery->get()->map(fn ($task) => $this->taskReportRow($task, $filters));

            // Fetch subtasks
            $subtasksQuery = Subtasks::with(['assignees:id,name', 'task.project.company:id,name', 'task.department:id,dept_name', 'timerLogs.user:id,name']);
            $this->applySubtaskReportFilters($subtasksQuery, $filters);
            if ($task_id) {
                $subtasksQuery->where('task_id', $task_id);
            } elseif ($project_id) {
                $subtasksQuery->whereHas('task', function ($query) use ($project_id) {
                    $query->where('project_id', $project_id);
                });
            }

            // Apply search filter on subtasks query
            if ($search) {
                $subtasksQuery->where(function ($query) use ($search) {
                    $query->where('title', 'like', '%'.$search.'%')
                        ->orWhereHas('task.project', function ($q) use ($search) {
                            $q->where('name', 'like', '%'.$search.'%');
                        })
                        ->orWhereHas('createdBy', function ($q) use ($search) {
                            $q->where('name', 'like', '%'.$search.'%');
                        })
                        ->orWhereHas('completedBy', function ($q) use ($search) {
                            $q->where('name', 'like', '%'.$search.'%');
                        })
                        ->orWhereHas('assignees', function ($q) use ($search) {
                            $q->where('name', 'like', '%'.$search.'%');
                        })
                        ->orWhere('status', 'like', '%'.$search.'%');
                });
            }

            $subtasks = $subtasksQuery->get()->map(fn ($subtask) => $this->subtaskReportRow($subtask, $filters));
        }

        // Combine tasks and subtasks
        $data = $tasks->merge($subtasks);

        // Get total records (before filtering)
        $totalRecords = $data->count();

        // Get filtered records count (after search)
        $filteredRecords = $data->count();

        // Apply sorting
        $data = $data->sortBy([
            [$sortColumn, $sortDirection],
        ]);

        // Apply pagination on collection
        $data = $data->slice($start, $length)->values();

        // Format data
        $data = $data->map(function ($row) {
            return [
                ...$row,
                'due_date' => $row['due_date'] ? Carbon::parse($row['due_date'])->format('Y-m-d') : '-',
            ];
        });

        return response()->json([
            'draw' => intval($draw),
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'data' => $data,
        ]);
    }

    private function applyTaskReportFilters(Builder $query, array $filters): void
    {
        $this->applyTaskVisibility($query);

        $query->when($filters['company_id'] ?? null, fn ($q, $id) => $q->whereHas('project', fn ($p) => $p->where('company_id', $id)))
            ->when($filters['department_id'] ?? null, fn ($q, $id) => $q->where('department_id', $id));
        $this->applyWorkLogFilters($query, $filters);
        $this->applyDeadlineHealth($query, $filters['deadline_health'] ?? null);
    }

    private function applySubtaskReportFilters(Builder $query, array $filters): void
    {
        $this->applySubtaskVisibility($query);

        $query->when($filters['company_id'] ?? null, fn ($q, $id) => $q->whereHas('task.project', fn ($p) => $p->where('company_id', $id)))
            ->when($filters['department_id'] ?? null, fn ($q, $id) => $q->whereHas('task', fn ($t) => $t->where('department_id', $id)))
            ->when($filters['deadline_health'] ?? null, function ($q, $health) {
                $column = 'subtasks.due_date';
                match ($health) {
                    'overdue' => $q->whereNotNull($column)->whereDate($column, '<', today())->where('subtasks.status', '!=', 'completed'),
                    'on_track' => $q->whereNotNull($column)->whereDate($column, '>=', today())->where('subtasks.status', '!=', 'completed'),
                    'met' => $q->where('subtasks.status', 'completed')->whereColumn('subtasks.updated_at', '<=', $column),
                    'late' => $q->where('subtasks.status', 'completed')->whereColumn('subtasks.updated_at', '>', $column),
                    'no_deadline' => $q->whereNull($column),
                };
            });
        $this->applyWorkLogFilters($query, $filters);
    }

    private function applyWorkLogFilters(Builder $query, array $filters): void
    {
        if (($filters['user_id'] ?? null) || ($filters['from_date'] ?? null) || ($filters['to_date'] ?? null)) {
            $query->whereHas('timerLogs', fn ($logs) => $logs
                ->when($filters['user_id'] ?? null, fn ($l, $id) => $l->where('user_id', $id))
                ->when($filters['from_date'] ?? null, fn ($l, $date) => $l->whereDate('start_time', '>=', $date))
                ->when($filters['to_date'] ?? null, fn ($l, $date) => $l->whereDate('start_time', '<=', $date)));
        }
    }

    private function taskReportRow(Tasks $task, array $filters): array
    {
        return $this->reportRow($task, $task->project, $task->department, $task->timerLogs, 'Task', $filters);
    }

    private function subtaskReportRow(Subtasks $subtask, array $filters): array
    {
        return $this->reportRow($subtask, $subtask->task?->project, $subtask->task?->department, $subtask->timerLogs, 'Subtask', $filters);
    }

    private function reportRow($item, $project, $department, $logs, string $type, array $filters): array
    {
        $periodLogs = $logs->filter(fn ($log) => (!($filters['user_id'] ?? null) || (int) $log->user_id === (int) $filters['user_id'])
            && (!($filters['from_date'] ?? null) || $log->start_time?->toDateString() >= $filters['from_date'])
            && (!($filters['to_date'] ?? null) || $log->start_time?->toDateString() <= $filters['to_date']));
        $seconds = (int) $periodLogs->whereNotNull('end_time')->sum(fn ($log) => $log->start_time->diffInSeconds($log->end_time));

        return [
            'company_name' => $project?->company?->name ?? 'Unassigned',
            'project_name' => $project?->name ?? '-',
            'department_name' => $department?->dept_name ?? 'Unassigned',
            'title' => $item->title,
            'type' => $type,
            'status' => $item->status,
            'deadline_health' => $this->deadlineHealthLabel($item->due_date, $item->status, $item->updated_at),
            'due_date' => $item->due_date,
            'assignee' => $item->assignees->pluck('name')->implode(', ') ?: '-',
            'worked_by' => $periodLogs->pluck('user.name')->filter()->unique()->implode(', ') ?: '-',
            'invest_time' => $this->formatSeconds($seconds),
        ];
    }

    private function deadlineHealthLabel($dueDate, string $status, $updatedAt): string
    {
        if (! $dueDate) return 'No deadline';
        $due = Carbon::parse($dueDate)->endOfDay();
        if ($status === 'completed') return Carbon::parse($updatedAt)->lte($due) ? 'Met' : 'Late';
        return now()->gt($due) ? 'Overdue' : 'On track';
    }

    private function formatSeconds(int $seconds): string
    {
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        return sprintf('%dh %02dm', $hours, $minutes);
    }

    public function getTasks(Request $request)
    {
        $this->authorize('report-view');

        $validated = $request->validate([
            'project_id' => ['required', 'integer', 'exists:projects,id'],
        ]);

        $tasks = Tasks::query()->where('project_id', $validated['project_id']);
        $this->applyTaskVisibility($tasks);

        return response()->json($tasks->orderBy('title')->get(['id', 'title']));
    }

    public function getSubtasks(Request $request)
    {
        $this->authorize('report-view');

        $validated = $request->validate([
            'task_id' => ['required', 'integer', 'exists:tasks,id'],
        ]);

        $subtasks = Subtasks::query()->where('task_id', $validated['task_id']);
        $this->applySubtaskVisibility($subtasks);

        return response()->json($subtasks->orderBy('title')->get(['id', 'title']));
    }
}
