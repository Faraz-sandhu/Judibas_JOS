@extends('layouts.app')

@push('css-before')
<style>
    .jira-shell {
        background: #f4f5f7;
        border-radius: 14px;
        padding: 20px;
        position: relative;
    }

    .board-loading {
        position: absolute;
        inset: 0;
        z-index: 20;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(244, 245, 247, .78);
        backdrop-filter: blur(1px);
        border-radius: 14px;
    }

    .board-loading-card {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px 18px;
        border-radius: 10px;
        background: #fff;
        box-shadow: 0 8px 30px rgba(9, 30, 66, .2);
        color: #172b4d;
        font-weight: 600;
    }

    .jira-toolbar {
        display: flex;
        gap: 12px;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
    }

    .jira-board {
        display: grid;
        grid-auto-flow: column;
        grid-auto-columns: minmax(280px, 1fr);
        gap: 14px;
        overflow-x: auto;
        padding-bottom: 8px;
    }

    .jira-column {
        background: #ebecf0;
        border-radius: 10px;
        min-height: 520px;
        padding: 10px;
    }

    .jira-column.drag-over {
        outline: 2px dashed #7367f0;
        background: #e8e6ff;
    }

    .jira-column-title {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 4px 6px 12px;
        font-size: .78rem;
        font-weight: 700;
        letter-spacing: .04em;
        text-transform: uppercase;
    }

    .jira-task-list {
        min-height: 450px;
    }

    .jira-card {
        background: #fff;
        border: 1px solid #dfe1e6;
        border-radius: 8px;
        padding: 12px;
        margin-bottom: 10px;
        box-shadow: 0 1px 2px rgba(9, 30, 66, .12);
        cursor: grab;
    }

    .jira-card:hover {
        box-shadow: 0 4px 10px rgba(9, 30, 66, .16);
        transform: translateY(-1px);
    }

    .jira-card.dragging {
        opacity: .45;
    }

    .jira-key {
        color: #6b778c;
        font-size: .72rem;
        font-weight: 600;
    }

    .jira-card-title {
        color: #172b4d;
        font-weight: 600;
        margin: 8px 0 14px;
        overflow-wrap: anywhere;
    }

    .jira-avatar {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        object-fit: cover;
        background: #dfe1e6;
    }

    .jira-priority {
        font-size: .7rem;
        font-weight: 700;
    }

    .handoff-history {
        border: 1px solid rgba(115, 103, 240, .28);
        border-radius: 10px;
        background: rgba(115, 103, 240, .06);
        padding: 14px 16px;
    }

    .handoff-history-route {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
        font-weight: 700;
    }

    .handoff-history-notes {
        margin-top: 10px;
        padding: 9px 11px;
        border-left: 3px solid #7367f0;
        border-radius: 4px;
        background: rgba(115, 103, 240, .08);
        white-space: pre-wrap;
    }

    .jira-empty {
        color: #8993a4;
        text-align: center;
        padding: 32px 8px;
        font-size: .85rem;
    }

    .sprint-goal-panel {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        padding: 10px 14px;
        margin-bottom: 16px;
        background: #fff;
        border: 1px solid #e3e5e8;
        border-left: 3px solid #7367f0;
        border-radius: 8px;
        color: #6b778c;
    }

    .sprint-goal-icon {
        color: #7367f0;
        line-height: 1.45;
        flex: 0 0 auto;
    }

    .sprint-goal-content {
        min-width: 0;
        overflow-wrap: anywhere;
    }

    .sprint-goal-label {
        color: #172b4d;
        font-size: .72rem;
        font-weight: 700;
        letter-spacing: .04em;
        text-transform: uppercase;
    }

    .sprint-goal-text {
        margin-top: 2px;
        font-size: .86rem;
        line-height: 1.45;
        white-space: pre-wrap;
    }

    .metric-card {
        background: #fff;
        border: 1px solid #e3e5e8;
        border-radius: 9px;
        padding: 12px 16px;
        min-width: 120px;
    }

    .metric-value {
        font-size: 1.35rem;
        font-weight: 700;
        color: #172b4d;
    }

    .comment-avatar {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        object-fit: cover;
    }

    .issue-details-main {
        padding: 1.5rem;
        max-height: 76vh;
        overflow-y: auto;
    }

    .issue-comments-panel {
        display: flex;
        flex-direction: column;
        height: 76vh;
        background: #f8f9fb;
        border-left: 1px solid #e3e5e8;
    }

    .issue-comments-header {
        padding: 1rem 1.15rem;
        background: #fff;
        border-bottom: 1px solid #e3e5e8;
    }

    .issue-comments-list {
        flex: 1;
        overflow-y: auto;
        padding: 1rem;
        scrollbar-width: thin;
    }

    .issue-comment {
        display: flex;
        gap: .5rem;
        margin-bottom: .8rem;
        align-items: flex-start;
    }

    .issue-comment.own-comment {
        flex-direction: row-reverse;
    }

    .issue-comment-avatar {
        width: 26px;
        height: 26px;
        border-radius: 50%;
        object-fit: cover;
        flex: 0 0 26px;
    }

    .issue-comment-content {
        min-width: 0;
        max-width: 85%;
    }

    .issue-comment-meta {
        display: flex;
        gap: .4rem;
        align-items: center;
        margin: 0 4px 3px;
        font-size: .68rem;
        color: #7a8494;
    }

    .own-comment .issue-comment-meta {
        justify-content: flex-end;
    }

    .issue-comment-name {
        color: #4d5870;
        font-weight: 600;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        max-width: 130px;
    }

    .issue-comment-bubble {
        position: relative;
        padding: .55rem .7rem;
        border-radius: 10px;
        background: #fff;
        border: 1px solid #e3e5e8;
        color: #26334d;
        font-size: .82rem;
        line-height: 1.35;
        white-space: pre-wrap;
        overflow-wrap: anywhere;
    }

    .own-comment .issue-comment-bubble {
        background: #7367f0;
        border-color: #7367f0;
        color: #fff;
    }

    .issue-comment-delete {
        position: absolute;
        top: 2px;
        right: 3px;
        opacity: 0;
        color: inherit;
    }

    .issue-comment-bubble:hover .issue-comment-delete {
        opacity: .7;
    }

    .issue-comment-composer {
        padding: .85rem;
        background: #fff;
        border-top: 1px solid #e3e5e8;
    }

    .issue-modal-close {
        width: 34px;
        height: 34px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 34px;
        border-radius: 8px;
    }

    .edit-assignee-summary {
        display: flex;
        flex-wrap: wrap;
        gap: .45rem;
        margin-bottom: .55rem;
    }

    .edit-assignee-chip {
        display: inline-flex;
        align-items: center;
        gap: .4rem;
        padding: .35rem .6rem;
        border: 1px solid color-mix(in srgb, #7367f0 38%, transparent);
        border-radius: 999px;
        background: color-mix(in srgb, #7367f0 16%, transparent);
        color: var(--pms-text, #172b4d);
        font-size: .78rem;
        font-weight: 600;
    }

    .edit-assignee-chip i {
        color: #7367f0;
    }

    #edit-assignee option {
        padding: .5rem .65rem;
        border-radius: .35rem;
    }

    #edit-assignee option:checked {
        background: #7367f0 linear-gradient(0deg, #7367f0 0%, #7367f0 100%);
        color: #fff;
        font-weight: 700;
    }

    @media(max-width:991.98px) {
        .issue-details-main {
            max-height: none
        }

        .issue-comments-panel {
            height: 55vh;
            border-left: 0;
            border-top: 1px solid #e3e5e8
        }
    }

    @media(max-width:1200px) {
        .jira-board {
            grid-auto-columns: 280px;
        }
    }
</style>
@endpush

@section('content')
@php
$columns = ($departmentMode ?? false)
    ? collect([
        (object)['id' => 'pending', 'name' => 'To Do', 'color' => 'secondary'],
        (object)['id' => 'in_progress', 'name' => 'In Progress', 'color' => 'primary'],
        (object)['id' => 'in_review', 'name' => 'In Review', 'color' => 'warning'],
        (object)['id' => 'completed', 'name' => 'Done', 'color' => 'success'],
    ])
    : ($selectedWorkflow?->columns ?? collect());
$canCreate = Gate::allows('task-add');
$canEdit = Gate::allows('task-edit');
$canChangeStatus = Gate::allows('task-status');
$canDelete = Gate::allows('task-trash');
$canQuickAssign = auth()->user()->hasRole('admin') || auth()->user()->hasRole('project_manager') || auth()->user()->hasRole('team_leader');
$canManageSprints = Gate::allows('project-edit');
$canManageEstimates = auth()->user()->hasRole('admin') || auth()->user()->hasRole('project_manager');
$departmentMode = $departmentMode ?? false;
@endphp

<div class="jira-shell">
    <div id="board-loading" class="board-loading d-none" role="status" aria-live="polite">
        <div class="board-loading-card"><span class="spinner-border spinner-border-sm text-primary" aria-hidden="true"></span><span id="board-loading-text">Updating task...</span></div>
    </div>
    <div class="jira-toolbar mb-3">
        <div>
            <div class="text-muted small mb-1">{{ $departmentMode ? 'TEAM SPACE / '.strtoupper($department->dept_name) : 'PROJECT / '.strtoupper($project->name) }}</div>
            <h3 class="mb-1">{{ $departmentMode ? 'Department sprint board' : 'Sprint board' }}</h3>
            @if($departmentMode)<small class="text-muted">Work across {{ $departmentProjects->count() }} accessible {{ Str::plural('project', $departmentProjects->count()) }}.</small>@endif
        </div>
        <div class="d-flex gap-2 align-items-center flex-wrap">
            @if($departmentMode)
            <select id="project-filter" class="form-select" style="min-width:220px"><option value="">All {{ $department->dept_name }} work</option><option value="department-backlog" @selected(request('project') === 'department-backlog')>{{ $department->dept_name }} department backlog</option>@foreach($departmentProjects as $departmentProject)<option value="{{ $departmentProject->id }}" @selected($selectedProjectId === $departmentProject->id)>{{ $departmentProject->name }} — project backlog and sprints</option>@endforeach</select>
            @endif
            @if(!$departmentMode && $workflows->count() > 1)
            <select id="workflow-filter" class="form-select" style="min-width:220px">
                @foreach($workflows as $workflow)<option value="{{ $workflow->id }}" @selected($selectedWorkflow?->id === $workflow->id)>{{ $workflow->name }}{{ $workflow->department ? ' · '.$workflow->department->dept_name : '' }}</option>@endforeach
            </select>
            @endif
            <select id="sprint-filter" class="form-select" style="min-width:220px">
                @if($departmentMode)<option value="all" @selected(!request()->filled('sprint') || request('sprint') === 'all')>All sprints and backlog</option>@endif
                <option value="backlog" @selected(request('sprint') === 'backlog' || (!$departmentMode && !$selectedSprint))>Backlog</option>
                @foreach($sprints as $sprint)
                <option value="{{ $sprint->id }}" @selected($selectedSprint?->id === $sprint->id)>
                    {{ $sprint->name }} · {{ ucfirst($sprint->status) }} ({{ $sprint->tasks_count }})
                </option>
                @endforeach
            </select>
            @if($canManageSprints && !$departmentMode)
            <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#sprintModal">
                <i class="fa fa-bolt me-1"></i> New sprint
            </button>
            @if($selectedSprint)
            <button type="button" class="btn btn-outline-secondary sprint-state-btn"
                data-status="{{ $selectedSprint->status === 'active' ? 'completed' : 'active' }}">
                {{ $selectedSprint->status === 'active' ? 'Complete sprint' : 'Start sprint' }}
            </button>
            @endif
            @endif
        </div>
    </div>

    <div class="sprint-goal-panel">
        <i class="fa fa-bullseye sprint-goal-icon" aria-hidden="true"></i>
        <div class="sprint-goal-content">
            <div class="sprint-goal-label">{{ $selectedSprint ? 'Sprint goal' : ($departmentMode ? 'Department overview' : 'Backlog') }}</div>
            <div class="sprint-goal-text">{{ $selectedSprint?->goal ?: ($selectedSprint ? 'No sprint goal defined.' : ($departmentMode ? 'Showing work across this department. Use the filters to focus the board.' : 'Tasks not assigned to a sprint.')) }}</div>
        </div>
    </div>

    <div class="d-flex gap-2 flex-wrap mb-3">
        @if($departmentMode)
        <div class="metric-card"><div class="small text-muted">Projects</div><div class="metric-value text-primary">{{ $departmentProjects->count() }}</div></div>
        <div class="metric-card"><div class="small text-muted">Visible tasks</div><div class="metric-value">{{ $departmentStats['tasks'] }}</div></div>
        <div class="metric-card"><div class="small text-muted">Backlog tasks</div><div class="metric-value text-warning">{{ $departmentStats['backlog'] }}</div></div>
        <div class="metric-card"><div class="small text-muted">Sprint tasks</div><div class="metric-value text-success">{{ $departmentStats['sprint'] }}</div></div>
        @else
        <div class="metric-card">
            <div class="small text-muted">All sprints</div>
            <div class="metric-value">{{ $sprintStats['total'] }}</div>
        </div>
        <div class="metric-card">
            <div class="small text-muted">Active</div>
            <div class="metric-value text-primary">{{ $sprintStats['active'] }}</div>
        </div>
        <div class="metric-card">
            <div class="small text-muted">Planned</div>
            <div class="metric-value text-warning">{{ $sprintStats['planned'] }}</div>
        </div>
        <div class="metric-card">
            <div class="small text-muted">Completed</div>
            <div class="metric-value text-success">{{ $sprintStats['completed'] }}</div>
        </div>
        <button class="btn btn-label-secondary ms-auto" data-bs-toggle="offcanvas" data-bs-target="#workloadCanvas"><i class="fa fa-users me-1"></i> Assignee workload</button>
        @endif
    </div>

    @if($canCreate)
    <div class="card mb-3">
        <div class="card-body py-2">
            <div class="input-group">
                <span class="input-group-text"><i class="fa fa-plus"></i></span>
                @if($departmentMode)<select id="quick-task-project" class="form-select" style="max-width:220px"><option value="">Select project first…</option>@foreach($departmentProjects as $departmentProject)<option value="{{ $departmentProject->id }}">{{ $departmentProject->name }}</option>@endforeach</select><select id="quick-task-sprint" class="form-select" style="max-width:210px" disabled><option value="">Backlog</option>@foreach($sprints as $sprint)<option value="{{ $sprint->id }}" data-project-id="{{ $sprint->project_id }}">{{ $sprint->name }}</option>@endforeach</select>@endif
                <input id="quick-task-title" class="form-control" maxlength="255" placeholder="Create a task in {{ $selectedSprint?->name ?? 'Backlog' }}">
                <button id="quick-add-task" class="btn btn-primary" type="button">Create task</button>
                <button class="btn btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#issueCreateModal">Full issue</button>
            </div>
        </div>
    </div>
    @endif

    <div class="jira-board" id="jira-board">
        @foreach($columns as $column)
        @php
        $tasks = $departmentMode ? $boardTasks->where('status', $column->id) : $boardTasks->where('workflow_column_id', $column->id);
        @endphp
        <section class="jira-column" data-column-id="{{ $column->id }}" data-status="{{ $departmentMode ? $column->id : '' }}">
            <div class="jira-column-title">
                <span><span class="badge bg-label-{{ $column->color }} me-1">{{ $column->name }}</span></span>
                <span class="column-count badge bg-white text-dark">{{ $tasks->count() }}</span>
            </div>
            <div class="jira-task-list">
                @forelse($tasks as $task)
                @include('dashboard.projects.partials.kanban-card', ['task' => $task, 'project' => $departmentMode ? $task->project : $project, 'showProject' => $departmentMode, 'canEdit' => $canEdit, 'canChangeStatus' => $canChangeStatus, 'canDelete' => $canDelete])
                @empty
                <div class="jira-empty">Drop tasks here</div>
                @endforelse
            </div>
        </section>
        @endforeach
    </div>
</div>

@if($canQuickAssign)
<div class="modal fade" id="quickAssigneeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title">Assign issue</h5><small id="quick-assignee-task-name" class="text-muted"></small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="input-group mb-3">
                    <span class="input-group-text"><i class="fa fa-search"></i></span>
                    <input id="quick-assignee-search" type="search" class="form-control" placeholder="Search a name or enter an email to invite..." autocomplete="off">
                </div>
                <div id="quick-assignee-list" class="list-group" style="max-height:360px;overflow-y:auto">
                    <button type="button" class="list-group-item list-group-item-action quick-assignee-option d-flex align-items-center gap-2"
                        data-user-id="" data-user-name="Unassigned" data-avatar="" data-search="unassigned">
                        <span class="jira-avatar d-inline-flex align-items-center justify-content-center"><i class="fa fa-user-slash text-muted"></i></span>
                        <span>Unassigned</span><i class="quick-assignee-check fa fa-check text-success ms-auto d-none"></i>
                    </button>
                    @foreach(collect($asignee) as $person)
                    <button type="button" class="list-group-item list-group-item-action quick-assignee-option d-flex align-items-center gap-2"
                        data-user-id="{{ $person->id }}" data-user-name="{{ $person->name }}"
                        data-avatar="{{ $person->profile_img ?: asset('assets/img/user-picture.png') }}" data-search="{{ strtolower($person->name.' '.$person->email) }}">
                        <img class="jira-avatar" src="{{ $person->profile_img ?: asset('assets/img/user-picture.png') }}" alt="">
                        <span><strong class="d-block">{{ $person->name }}</strong><small class="text-muted">{{ $person->email }}</small></span><i class="quick-assignee-check fa fa-check text-success ms-auto d-none"></i>
                    </button>
                    @endforeach
                    <div id="quick-assignee-invite-actions" class="list-group-item d-none align-items-center gap-2">
                        <span class="jira-avatar d-inline-flex align-items-center justify-content-center bg-label-primary"><i class="fa fa-envelope"></i></span>
                        <span class="flex-grow-1"><strong>Invite new employee</strong><small id="quick-assignee-invite-email" class="d-block text-muted"></small></span>
                        <button type="button" id="quick-assignee-invite" class="btn btn-sm btn-primary" title="Send invitation by email"><i class="fa fa-paper-plane me-1"></i>Send email</button>
                        <button type="button" id="quick-assignee-copy" class="btn btn-sm btn-icon btn-label-primary" title="Create and copy invitation link" aria-label="Create and copy invitation link"><i class="fa fa-copy"></i></button>
                    </div>
                    <button type="button" id="quick-assignee-existing" class="list-group-item list-group-item-action quick-assignee-option d-none align-items-center gap-2" data-user-id="" data-user-name="" data-avatar="" data-search="">
                        <img id="quick-assignee-existing-avatar" class="jira-avatar" src="{{ asset('assets/img/user-picture.png') }}" alt="">
                        <span><strong id="quick-assignee-existing-name"></strong><small id="quick-assignee-existing-email" class="d-block text-muted"></small><small id="quick-assignee-existing-help" class="d-block text-success">Existing employee — click to assign.</small></span>
                        <i class="quick-assignee-check fa fa-user-plus text-primary ms-auto"></i>
                    </button>
                    <div id="quick-assignee-empty" class="jira-empty d-none">No employees found.</div>
                </div>
            </div>
        </div>
    </div>
</div>
@endif

<div class="offcanvas offcanvas-end" tabindex="-1" id="workloadCanvas" style="width:460px">
    <div class="offcanvas-header">
        <div>
            <h5 class="offcanvas-title">Assignee workload</h5><small class="text-muted">Project vs {{ $selectedSprint?->name ?? 'Backlog' }}</small>
        </div><button class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body">
        @forelse($workloadUsers as $person)
        <div class="d-flex align-items-center justify-content-between border-bottom py-3">
            <div class="d-flex align-items-center gap-2"><img class="jira-avatar" src="{{ $person->profile_img ?: asset('assets/img/user-picture.png') }}"><strong>{{ $person->name }}</strong></div>
            <div class="text-end"><span class="badge bg-label-primary">{{ $person->sprint_tasks_count }} current</span>
                <div class="small text-muted mt-1">{{ $person->project_tasks_count }} project total</div>
            </div>
        </div>
        @if($person->tasks->isNotEmpty())
        <div class="pb-3 ps-4">
            @foreach($person->tasks as $assignedTask)
            @php
            $isCurrent = $selectedSprint
            ? $assignedTask->sprint_id === $selectedSprint->id
            : $assignedTask->sprint_id === null;
            @endphp
            <div class="small py-1 {{ $isCurrent ? 'text-primary fw-semibold' : 'text-muted' }}">
                <i class="fa fa-square-check me-1"></i>{{ $assignedTask->title }}
                <span class="badge bg-label-{{ $assignedTask->status === 'completed' ? 'success' : 'secondary' }} ms-1">{{ str_replace('_', ' ', $assignedTask->status) }}</span>
            </div>
            @endforeach
        </div>
        @endif
        @empty<div class="jira-empty">No users assigned to this project.</div>@endforelse
    </div>
</div>

<div class="modal fade" id="issueCreateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create issue</h5><button class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="issue-create-form" class="row g-3" enctype="multipart/form-data">
                    @if($departmentMode)<div class="col-12"><label class="form-label">Project <span class="text-danger">*</span></label><select name="project_id" id="create-task-project" class="form-select" required><option value="">Choose a department project</option>@foreach($departmentProjects as $departmentProject)<option value="{{ $departmentProject->id }}">{{ $departmentProject->name }}</option>@endforeach</select><div class="form-text">The new task will belong to the selected project.</div></div>@endif
                    <div class="col-12"><label class="form-label">Summary</label><input name="title" class="form-control" required maxlength="255"></div>
                    <div class="col-12"><label class="form-label">Description</label><textarea id="create-task-description" name="description" class="form-control task-description-editor" rows="5" placeholder="Acceptance criteria, steps, context..."></textarea></div>
                    <input type="hidden" name="status" value="pending"><input type="hidden" name="workflow_id" value="{{ $selectedWorkflow?->id }}">
                    <div class="col-md-6"><label class="form-label">Workflow column</label><select name="workflow_column_id" class="form-select">@foreach($columns as $column)<option value="{{ $column->id }}">{{ $column->name }}</option>@endforeach</select></div>
                    <div class="col-md-6"><label class="form-label">Priority</label><select name="priority" class="form-select">
                            <option value="0">Low</option>
                            <option value="1" selected>Normal</option>
                            <option value="2">High</option>
                            <option value="3">Urgent</option>
                        </select></div>
                    <div class="col-md-6"><label class="form-label">Assignees</label><select name="assignee_ids[]" class="form-select" multiple size="4">@foreach(collect($asignee) as $person)<option value="{{ $person->id }}">{{ $person->name }}</option>@endforeach</select>
                        <div class="form-text">Select one or more employees. Hold Ctrl/Cmd to select multiple.</div>
                    </div>
                    <div class="col-md-6"><label class="form-label">Sprint</label><select name="sprint_id" class="form-select">
                            <option value="">Backlog</option>@foreach($sprints as $sprint)<option value="{{ $sprint->id }}" data-project-id="{{ $sprint->project_id }}" @selected(!$departmentMode && $selectedSprint?->id===$sprint->id)>{{ $sprint->name }}</option>@endforeach
                        </select></div>
                    <div class="col-md-6"><label class="form-label">Due date</label><input name="due_date" type="datetime-local" class="form-control"></div>
                    <div class="col-md-6"><label class="form-label">Attachments</label>
                        <div class="form-text mb-1"><i class="fa fa-circle-info me-1"></i>PDF, Office, CSV, TXT, JPG, PNG, WebP, GIF or ZIP · Up to 10 files, 10 MB each.</div><input name="attachments[]" type="file" multiple class="form-control" accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.csv,.txt,.jpg,.jpeg,.png,.webp,.gif,.zip">
                    </div>
                </form>
            </div>
            <div class="modal-footer"><button class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button><button id="create-full-issue" class="btn btn-primary">Create issue</button></div>
        </div>
    </div>
</div>

<div class="modal fade" id="taskEditModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit task</h5><button class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="task-edit-form" class="row g-3">
                    <input type="hidden" id="edit-task-id">
                    <div class="col-12"><label class="form-label">Title</label><input id="edit-title" class="form-control" required maxlength="255"></div>
                    <div class="col-12"><label class="form-label">Description</label><textarea id="edit-description" class="form-control task-description-editor" rows="4"></textarea></div>
                    <div class="col-md-6"><label class="form-label">Workflow column</label><select id="edit-column" class="form-select" @disabled(!$canChangeStatus || $departmentMode)>
                            @foreach($columns as $column)<option value="{{ $column->id }}">{{ $column->name }}</option>@endforeach
                        </select>@unless($canChangeStatus)<div class="form-text">You do not have permission to change task status.</div>@endunless</div>
                    <div class="col-md-6"><label class="form-label">Priority</label><select id="edit-priority" class="form-select">
                            <option value="0">Low</option>
                            <option value="1">Normal</option>
                            <option value="2">High</option>
                            <option value="3">Urgent</option>
                        </select></div>
                    <div class="col-md-6"><label class="form-label">Due date</label><input id="edit-due-date" type="datetime-local" class="form-control"></div>
                    <div class="col-md-6"><label class="form-label">Sprint</label><select id="edit-sprint" class="form-select">
                            <option value="">Backlog</option>@foreach($sprints as $sprint)<option value="{{ $sprint->id }}" data-project-id="{{ $sprint->project_id }}">{{ $sprint->name }}</option>@endforeach
                        </select></div>
                    <div class="col-12"><label class="form-label">Assignees</label>
                        <div id="edit-assignee-selected" class="edit-assignee-summary"></div><select id="edit-assignee" class="form-select" multiple size="5">
                            @foreach(collect($asignee) as $person)<option value="{{ $person->id }}">{{ $person->name }}</option>@endforeach
                        </select>
                        <div class="form-text">Select everyone who will work on this task. Clear all selections to unassign everyone.</div>
                    </div>
                    @if($canManageEstimates)<div class="col-12"><label class="form-label">Original estimate (hours)</label><input id="edit-estimate-hours" type="number" min="0.25" max="8760" step="0.25" class="form-control" placeholder="Example: 8">
                        <div class="form-text">Warning at 80%; red when logged time exceeds this estimate.</div>
                    </div>@endif
                </form>
            </div>
            <div class="modal-footer"><button class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button><button id="save-task" class="btn btn-primary">Save changes</button></div>
        </div>
    </div>
</div>

<div class="modal fade" id="taskHandoffModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header"><div><h5 class="modal-title">Hand off task</h5><small id="handoff-task-title" class="text-muted"></small></div><button class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="alert alert-primary"><i class="fa fa-circle-info me-1"></i>The current task will be completed and a linked task will be created in the destination department backlog, or in the selected project's backlog.</div>
                <form id="task-handoff-form" class="row g-3">
                    <input type="hidden" id="handoff-task-id">
                    <div class="col-12"><label class="form-label">Destination department <span class="text-danger">*</span></label><select id="handoff-department" class="form-select" required><option value="">Select department</option></select></div>
                    <div class="col-12"><label class="form-label">Destination project <span class="text-muted">(optional)</span></label><select id="handoff-project" class="form-select" disabled><option value="">Department backlog</option></select><div class="form-text">Leave this empty when the receiving team should decide the project and sprint later.</div></div>
                    <div class="col-12"><label class="form-label">Handoff instructions</label><textarea id="handoff-notes" class="form-control" rows="4" maxlength="5000" placeholder="Explain what the receiving team needs to deliver..."></textarea></div>
                </form>
            </div>
            <div class="modal-footer"><button class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button><button id="submit-task-handoff" class="btn btn-primary"><i class="fa fa-arrow-right-arrow-left me-1"></i>Complete and hand off</button></div>
        </div>
    </div>
</div>

@if($departmentMode)
<div class="modal fade" id="taskPlacementModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <div class="modal-header"><div><h5 class="modal-title">Place task in project</h5><small id="placement-task-title" class="text-muted"></small></div><button class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body"><form class="row g-3">
            <input type="hidden" id="placement-task-id">
            <div class="col-12"><label class="form-label">Project <span class="text-danger">*</span></label><select id="placement-project" class="form-select"><option value="">Select project</option>@foreach($departmentProjects as $departmentProject)<option value="{{ $departmentProject->id }}">{{ $departmentProject->name }}</option>@endforeach</select></div>
            <div class="col-12"><label class="form-label">Sprint <span class="text-muted">(optional)</span></label><select id="placement-sprint" class="form-select" disabled><option value="">Project backlog</option>@foreach($sprints as $sprint)<option value="{{ $sprint->id }}" data-project-id="{{ $sprint->project_id }}">{{ $sprint->name }}</option>@endforeach</select></div>
        </form></div>
        <div class="modal-footer"><button class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button><button id="submit-task-placement" class="btn btn-primary"><i class="fa fa-folder-plus me-1"></i>Place task</button></div>
    </div></div>
</div>
@endif

<div class="modal fade" id="taskDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content overflow-hidden">
            <div class="modal-header">
                <div>
                    <div class="jira-key" id="details-key"></div>
                    <div class="d-flex align-items-center gap-2 flex-wrap mt-1">
                        <h5 class="modal-title mb-0" id="details-title">Task details</h5>
                        <span id="details-priority" class="badge bg-label-info"></span>
                    </div>
                </div>
                <button type="button" class="btn btn-sm btn-label-secondary issue-modal-close" data-bs-dismiss="modal" aria-label="Close" title="Close">
                    <i class="fa fa-xmark"></i>
                </button>
            </div>
            <div class="modal-body p-0">
                <div class="row g-0">
                    <div class="col-lg-8 issue-details-main">
                        <div id="details-description" class="text-muted mb-4"></div>
                        <div id="details-handoff" class="handoff-history d-none mb-4"></div>
                        <div class="card bg-label-primary border-0 mb-4">
                            <div class="card-body py-3">
                                <div class="d-flex justify-content-between align-items-center gap-3">
                                    <div><small class="text-muted">TIME TRACKING</small>
                                        <h5 id="details-time-total" class="mb-0 mt-1">0m logged</h5>
                                    </div>
                                    <div id="details-timer-controls" class="d-flex gap-2"></div>
                                </div>
                                <div id="details-time-users" class="mt-3"></div>
                                <div id="details-estimate" class="mt-3 pt-3 border-top"></div>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="mb-0">Subtasks</h6><span id="subtask-progress" class="small text-muted"></span>
                        </div>
                        <div id="subtask-list" class="list-group mb-3"></div>
                        @can('sub-task-add')
                        <div class="input-group"><input id="new-subtask-title" class="form-control" placeholder="Add a subtask"><button id="add-subtask" class="btn btn-outline-primary">Add</button></div>
                        @endcan
                        <hr class="my-4">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="mb-0">Attachments</h6><span id="attachment-count" class="small text-muted"></span>
                        </div>
                        <div id="attachment-list" class="list-group mb-3"></div>
                        <div class="form-text mb-1"><i class="fa fa-circle-info me-1"></i>PDF, Office, CSV, TXT, JPG, PNG, WebP, GIF or ZIP · Up to 10 files, 10 MB each.</div>
                        <div class="input-group"><input id="issue-attachments" type="file" multiple class="form-control" accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.csv,.txt,.jpg,.jpeg,.png,.webp,.gif,.zip"><button id="upload-attachments" class="btn btn-outline-primary">Upload</button></div>
                    </div>
                    <aside class="col-lg-4 issue-comments-panel">
                        <div class="issue-comments-header d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0"><i class="fa fa-comments me-2 text-primary"></i>Comments</h6><small class="text-muted">Issue conversation</small>
                            </div>
                            <span id="comment-count" class="badge bg-label-primary">0</span>
                        </div>
                        <div id="comment-list" class="issue-comments-list"></div>
                        <div class="issue-comment-composer">
                            <textarea id="new-comment" class="form-control mb-2" rows="2" maxlength="5000" placeholder="Write a comment..."></textarea>
                            <div class="d-flex justify-content-between align-items-center"><small class="text-muted">Ctrl + Enter to send</small><button id="add-comment" class="btn btn-primary btn-sm"><i class="fa fa-paper-plane me-1"></i>Send</button></div>
                        </div>
                    </aside>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="sprintModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create sprint</h5><button class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="sprint-form" class="row g-3">
                    <div class="col-12"><label class="form-label">Sprint name</label><input id="sprint-name" class="form-control" required placeholder="Sprint 1"></div>
                    <div class="col-12"><label class="form-label">Sprint goal</label><textarea id="sprint-goal" class="form-control" rows="3"></textarea></div>
                    <div class="col-6"><label class="form-label">Start date</label><input id="sprint-start" type="date" class="form-control"></div>
                    <div class="col-6"><label class="form-label">End date</label><input id="sprint-end" type="date" class="form-control"></div>
                    <div class="col-12 form-check ms-2"><input id="sprint-active" type="checkbox" class="form-check-input"><label for="sprint-active" class="form-check-label">Start immediately</label>
                        <div class="form-text">Multiple sprints can be active in this project at the same time.</div>
                    </div>
                </form>
            </div>
            <div class="modal-footer"><button class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button><button id="create-sprint" class="btn btn-primary">Create sprint</button></div>
        </div>
    </div>
</div>
@endsection

@push('page-scripts')
<script>
    (() => {
        const csrf = document.querySelector('meta[name="csrf-token"]').content;
        const departmentMode = @json($departmentMode);
        const currentDepartmentId = @json($departmentMode ? $department->id : null);
        const projectId = @json($departmentMode ? null : $project->id);
        const selectedSprintId = @json($selectedSprint?->id);
        const selectedWorkflowId = @json($selectedWorkflow?->id);
        const canEdit = @json($canEdit);
        const canChangeStatus = @json($canChangeStatus);
        const renderEditAssigneeSelection = () => {
            const select = document.getElementById('edit-assignee');
            const summary = document.getElementById('edit-assignee-selected');
            if (!select || !summary) return;
            const selected = [...select.selectedOptions];
            summary.innerHTML = selected.length ?
                selected.map(option => `<span class="edit-assignee-chip"><i class="fa fa-circle-check"></i>${escapeHtml(option.textContent.trim())}</span>`).join('') :
                '<span class="small text-muted"><i class="fa fa-user-slash me-1"></i>No employees currently assigned</span>';
        };
        const taskStoreUrl = @json(route('tasks.store'));
        const sprintStoreUrl = @json($departmentMode ? null : route('sprints.store', $project));
        const sprintUpdateTemplate = @json(route('sprints.update', ':id'));
        const boardUpdateTemplate = @json(route('tasks.board-update', ':id'));
        const taskInviteTemplate = @json(route('tasks.invite', ':id'));
        const taskInviteCheckTemplate = @json(route('tasks.invite.check', ':id'));
        const taskDeleteTemplate = @json(route('tasks.destroy', ':id'));
        const taskShowTemplate = @json(route('tasks.show', ':id'));
        const taskHandoffOptionsTemplate = @json(route('tasks.handoff.options', ':id'));
        const taskHandoffStoreTemplate = @json(route('tasks.handoff.store', ':id'));
        const taskPlacementStoreTemplate = @json(route('tasks.placement.store', ':id'));
        const subtaskStoreUrl = @json(route('subtasks.store'));
        const subtaskDeleteTemplate = @json(route('subtasks.destroy', ':id'));
        const commentStoreTemplate = @json(route('tasks.comments.store', ':id'));
        const commentDeleteTemplate = @json(route('tasks.comments.destroy', ':id'));
        const attachmentStoreTemplate = @json(route('tasks.attachments.store', ':id'));
        const attachmentDeleteTemplate = @json(route('tasks.attachments.destroy', ':id'));
        const timerUrls = {
            start: @json(route('timer_logs.start')),
            stop: @json(route('timer_logs.stop'))
        };
        const currentUserId = @json(auth()->id());
        let detailsTaskId = null;
        let quickAssigneeCard = null;
        let handoffDepartments = [];
        const resetQuickAssigneeModal = () => {
            const modalElement = document.getElementById('quickAssigneeModal');
            if (!modalElement) return;

            bootstrap.Modal.getInstance(modalElement)?.hide();
            modalElement.classList.remove('show');
            modalElement.style.display = 'none';
            modalElement.setAttribute('aria-hidden', 'true');
            modalElement.removeAttribute('aria-modal');
            modalElement.removeAttribute('role');
            quickAssigneeCard = null;

            document.querySelectorAll('.modal-backdrop').forEach(backdrop => backdrop.remove());
            if (!document.querySelector('.modal.show')) {
                document.body.classList.remove('modal-open');
                document.body.style.removeProperty('overflow');
                document.body.style.removeProperty('padding-right');
            }
        };

        // Browsers may restore an open Bootstrap modal from the back/forward cache.
        // Assignment dialogs must only open following an explicit assignee-button click.
        window.addEventListener('pageshow', resetQuickAssigneeModal);
        const filterSprintOptions = (select, targetProjectId) => {
            if (!select) return;
            [...select.options].forEach(option => {
                if (!option.value) return;
                option.hidden = Boolean(targetProjectId) && option.dataset.projectId !== String(targetProjectId);
            });
            if (select.selectedOptions[0]?.hidden) select.value = '';
            select.disabled = !targetProjectId;
        };
        document.getElementById('quick-task-project')?.addEventListener('change', e => filterSprintOptions(document.getElementById('quick-task-sprint'), e.currentTarget.value));
        document.getElementById('create-task-project')?.addEventListener('change', e => filterSprintOptions(document.querySelector('#issue-create-form [name="sprint_id"]'), e.currentTarget.value));

        const request = async (url, method, data = {}, useGlobalLoader = true) => {
            const response = await fetch(url, {
                method,
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf
                },
                body: method === 'GET' ? undefined : JSON.stringify(data),
                appLoader: useGlobalLoader
            });
            const payload = await response.json().catch(() => ({}));
            if (!response.ok) {
                const validation = payload.errors ? Object.values(payload.errors).flat().join('\n') : null;
                throw new Error(validation || payload.message || 'The request failed.');
            }
            return payload;
        };
        const formRequest = async (url, method, formData) => {
            const response = await fetch(url, {
                method,
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf
                },
                body: formData
            });
            const payload = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(payload.errors ? Object.values(payload.errors).flat().join('\n') : (payload.message || 'The request failed.'));
            return payload;
        };
        const notify = (message, error = false) => window.toastr ? toastr[error ? 'error' : 'success'](message) : alert(message);
        const setBoardLoading = (loading, message = 'Updating task...') => {
            document.getElementById('board-loading-text').textContent = message;
            document.getElementById('board-loading').classList.toggle('d-none', !loading);
        };
        const refreshBoardColumns = () => {
            document.querySelectorAll('.jira-column').forEach(column => {
                const list = column.querySelector('.jira-task-list');
                const cards = list.querySelectorAll('.jira-card');
                column.querySelector('.column-count').textContent = cards.length;
                const empty = list.querySelector('.jira-empty');
                if (cards.length && empty) empty.remove();
                if (!cards.length && !empty) list.insertAdjacentHTML('beforeend', '<div class="jira-empty">Drop tasks here</div>');
            });
        };
        const openTaskDetails = async taskId => {
            detailsTaskId = taskId;
            const result = await request(taskShowTemplate.replace(':id', detailsTaskId), 'GET');
            renderDetails(result.task);
            bootstrap.Modal.getOrCreateInstance(document.getElementById('taskDetailsModal')).show();
        };
        const confirmDelete = async (title, text) => {
            if (!window.Swal) return {
                isConfirmed: window.confirm(text)
            };
            return Swal.fire({
                title,
                text,
                icon: 'warning',
                showCancelButton: true,
                showDenyButton: false,
                focusCancel: true,
                confirmButtonText: '<i class="fa fa-trash me-1"></i> Delete',
                cancelButtonText: 'Cancel',
                reverseButtons: true,
                buttonsStyling: false,
                customClass: {
                    actions: 'd-flex gap-2',
                    confirmButton: 'btn btn-danger px-4',
                    cancelButton: 'btn btn-label-secondary px-4',
                    denyButton: 'd-none'
                },
                didOpen: popup => {
                    const denyButton = popup.querySelector('.swal2-deny');
                    if (denyButton) {
                        denyButton.hidden = true;
                        denyButton.style.display = 'none';
                        denyButton.remove();
                    }
                }
            });
        };
        const reload = () => window.location.reload();

        const requestedIssue = new URLSearchParams(window.location.search).get('issue');
        if (requestedIssue) {
            openTaskDetails(requestedIssue).catch(error => notify(error.message, true));
        }

        document.getElementById('sprint-filter')?.addEventListener('change', e => {
            const sprint = e.currentTarget.value;
            e.currentTarget.disabled = true;
            window.AppLoader?.show('Loading sprint...');
            requestAnimationFrame(() => {
                requestAnimationFrame(() => {
                    const project = document.getElementById('project-filter')?.value || '';
                    window.location.href = `${window.location.pathname}?project=${encodeURIComponent(project)}&sprint=${encodeURIComponent(sprint)}&workflow=${selectedWorkflowId}`;
                });
            });
        });
        document.getElementById('workflow-filter')?.addEventListener('change', e => {
            e.currentTarget.disabled = true;
            window.AppLoader?.show('Loading workflow...');
            const project = document.getElementById('project-filter')?.value || '';
            window.location.href = `${window.location.pathname}?project=${encodeURIComponent(project)}&sprint=${encodeURIComponent(document.getElementById('sprint-filter').value)}&workflow=${encodeURIComponent(e.currentTarget.value)}`;
        });
        document.getElementById('project-filter')?.addEventListener('change', e => {
            window.AppLoader?.show('Loading project work…');
            window.location.href = `${window.location.pathname}?project=${encodeURIComponent(e.currentTarget.value)}&sprint=all&workflow=${selectedWorkflowId}`;
        });

        document.getElementById('quick-add-task')?.addEventListener('click', async () => {
            const input = document.getElementById('quick-task-title');
            if (!input.value.trim()) return notify('Task title is required.', true);
            const targetProjectId = departmentMode ? document.getElementById('quick-task-project')?.value : projectId;
            if (!targetProjectId) return notify('Select the project where this task should be created.', true);
            try {
                await request(taskStoreUrl, 'POST', {
                    title: input.value.trim(),
                    project_id: targetProjectId,
                    sprint_id: departmentMode ? (document.getElementById('quick-task-sprint')?.value || null) : selectedSprintId,
                    workflow_id: selectedWorkflowId,
                    workflow_column_id: @json($columns->firstWhere('is_initial', true)?->id ?? $columns->first()?->id),
                    status: 'pending',
                    priority: 1
                });
                reload();
            } catch (error) {
                notify(error.message, true);
            }
        });
        document.getElementById('quick-task-title')?.addEventListener('keydown', e => {
            if (e.key === 'Enter') {
                e.preventDefault();
                document.getElementById('quick-add-task').click();
            }
        });
        document.getElementById('create-full-issue')?.addEventListener('click', async () => {
            const form = document.getElementById('issue-create-form');
            const data = new FormData(form);
            if (!departmentMode) data.set('project_id', projectId);
            if (!data.get('project_id')) return notify('Select the project where this task should be created.', true);
            if (!data.get('title')?.trim()) return notify('Issue summary is required.', true);
            try {
                await formRequest(taskStoreUrl, 'POST', data);
                reload();
            } catch (error) {
                notify(error.message, true);
            }
        });

        document.querySelectorAll('.jira-card').forEach(card => {
            card.addEventListener('pointerdown', event => {
                card.dataset.preventDrag = event.target.closest('.quick-assignee-control') ? 'true' : 'false';
            });
            card.addEventListener('dragstart', event => {
                if (!canChangeStatus || card.dataset.preventDrag === 'true') {
                    event.preventDefault();
                    return;
                }
                card.classList.add('dragging');
            });
            card.addEventListener('dragend', () => card.classList.remove('dragging'));
        });
        if (canChangeStatus) document.querySelectorAll('.jira-column').forEach(column => {
            column.addEventListener('dragover', e => {
                e.preventDefault();
                column.classList.add('drag-over');
            });
            column.addEventListener('dragleave', () => column.classList.remove('drag-over'));
            column.addEventListener('drop', async e => {
                e.preventDefault();
                column.classList.remove('drag-over');
                const card = document.querySelector('.jira-card.dragging');
                if (!card || card.dataset.columnId === column.dataset.columnId) return;
                const targetColumnId = column.dataset.columnId;
                setBoardLoading(true, 'Moving task...');
                try {
                    // Allow the browser to paint the loader before starting the request.
                    await new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)));
                    const movement = departmentMode
                        ? {status: column.dataset.status}
                        : {workflow_id: selectedWorkflowId, workflow_column_id: targetColumnId};
                    await request(boardUpdateTemplate.replace(':id', card.dataset.id), 'PATCH', movement, false);
                    card.dataset.columnId = targetColumnId;
                    column.querySelector('.jira-task-list').prepend(card);
                    refreshBoardColumns();
                    notify('Task moved successfully.');
                } catch (error) {
                    notify(error.message, true);
                } finally {
                    setBoardLoading(false);
                }
            });
        });

        document.addEventListener('click', async e => {
            const card = e.target.closest('.jira-card');
            const interactiveElement = e.target.closest('button, a, input, select, textarea, .dropdown, .dropdown-menu');
            if (card && !interactiveElement) {
                try {
                    await openTaskDetails(card.dataset.id);
                } catch (error) {
                    notify(error.message, true);
                }
            }
            const assigneeToggle = e.target.closest('.quick-assignee-toggle');
            if (assigneeToggle) {
                quickAssigneeCard = assigneeToggle.closest('.jira-card');
                const currentAssignees = new Set((quickAssigneeCard.dataset.assigneeIds || '').split(',').filter(Boolean));
                document.getElementById('quick-assignee-task-name').textContent = quickAssigneeCard.dataset.title;
                document.getElementById('quick-assignee-search').value = '';
                document.getElementById('quick-assignee-invite-actions').classList.add('d-none');
                document.getElementById('quick-assignee-invite-actions').classList.remove('d-flex');
                document.getElementById('quick-assignee-existing').classList.add('d-none');
                document.getElementById('quick-assignee-existing').classList.remove('d-flex');
                document.querySelectorAll('.quick-assignee-option:not(#quick-assignee-existing)').forEach(option => {
                    option.classList.remove('d-none');
                    const selected = option.dataset.userId ? currentAssignees.has(option.dataset.userId) : currentAssignees.size === 0;
                    option.querySelector('.quick-assignee-check').classList.toggle('d-none', !selected);
                });
                document.getElementById('quick-assignee-empty').classList.add('d-none');
                bootstrap.Modal.getOrCreateInstance(document.getElementById('quickAssigneeModal')).show();
                setTimeout(() => document.getElementById('quick-assignee-search').focus(), 200);
                return;
            }
            const assigneeOption = e.target.closest('.quick-assignee-option');
            if (assigneeOption && quickAssigneeCard) {
                const card = quickAssigneeCard;
                const assigneeId = assigneeOption.dataset.userId || null;
                const selectedIds = new Set((card.dataset.assigneeIds || '').split(',').filter(Boolean));
                const action = !assigneeId ? 'clear' : (selectedIds.has(String(assigneeId)) ? 'remove' : 'add');

                // Close the dialog completely before displaying the board loader.
                // Otherwise Bootstrap's modal and backdrop remain above the loader
                // during the assignment request.
                const assigneeModalElement = document.getElementById('quickAssigneeModal');
                const assigneeModal = bootstrap.Modal.getOrCreateInstance(assigneeModalElement);
                const modalHidden = new Promise(resolve => {
                    assigneeModalElement.addEventListener('hidden.bs.modal', resolve, {once: true});
                });
                assigneeModal.hide();
                quickAssigneeCard = null;
                await modalHidden;

                setBoardLoading(true, action === 'add' ? 'Assigning employee...' : 'Removing assignee...');
                try {
                    await new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)));
                    const result = await request(boardUpdateTemplate.replace(':id', card.dataset.id), 'PATCH', {
                        assignee_id: assigneeId,
                        assignee_action: action
                    }, false);
                    const users = result.task?.users || [];
                    card.dataset.assigneeIds = users.map(user => user.id).join(',');
                    card.dataset.assigneeId = users[0]?.id || '';
                    const toggle = card.querySelector('.quick-assignee-toggle');
                    toggle.title = users.length ? 'Manage assignees' : 'Assign employee';
                    toggle.innerHTML = users.length ?
                        `<span class="quick-assignee-avatars d-flex align-items-center">${users.slice(0,3).map((user,index) => `<img class="jira-avatar quick-assignee-avatar" style="margin-left:${index ? -7 : 0}px" src="${user.profile_img || @json(asset('assets/img/user-picture.png'))}" title="${escapeHtml(user.name)}" alt="${escapeHtml(user.name)}">`).join('')}${users.length > 3 ? `<span class="badge rounded-pill bg-label-primary ms-1">+${users.length-3}</span>` : ''}</span>` :
                        '<span class="jira-avatar quick-assignee-avatar d-inline-flex align-items-center justify-content-center"><i class="fa fa-user-plus text-primary"></i></span>';
                    document.querySelectorAll('.quick-assignee-option').forEach(option => {
                        const selected = option.dataset.userId ? users.some(user => String(user.id) === option.dataset.userId) : users.length === 0;
                        option.querySelector('.quick-assignee-check').classList.toggle('d-none', !selected);
                    });
                    notify(action === 'add' ? `${assigneeOption.dataset.userName} assigned successfully.` : (assigneeId ? `${assigneeOption.dataset.userName} unassigned.` : 'All employees unassigned.'));
                } catch (error) {
                    notify(error.message, true);
                } finally {
                    setBoardLoading(false);
                }
                return;
            }
            const inviteOption = e.target.closest('#quick-assignee-invite, #quick-assignee-copy');
            if (inviteOption && quickAssigneeCard) {
                const email = document.getElementById('quick-assignee-search').value.trim().toLowerCase();
                inviteOption.disabled = true;
                bootstrap.Modal.getOrCreateInstance(document.getElementById('quickAssigneeModal')).hide();
                setBoardLoading(true, 'Sending invitation...');
                try {
                    const manual = inviteOption.id === 'quick-assignee-copy';
                    const response = await request(taskInviteTemplate.replace(':id', quickAssigneeCard.dataset.id), 'POST', {
                        email, delivery_method: manual ? 'manual' : 'email'
                    }, false);
                    if (manual) {
                        await navigator.clipboard.writeText(response.invitation_url);
                        notify('Invitation link created and copied.');
                    } else {
                        await window.showInvitationDeliveryResult(response);
                    }
                } catch (error) {
                    notify(error.message, true);
                } finally {
                    inviteOption.disabled = false;
                    setBoardLoading(false);
                }
                return;
            }
            const handoff = e.target.closest('.handoff-task');
            if (handoff) {
                const card = handoff.closest('.jira-card');
                document.getElementById('handoff-task-id').value = card.dataset.id;
                document.getElementById('handoff-task-title').textContent = card.dataset.title;
                document.getElementById('handoff-notes').value = '';
                const departmentSelect = document.getElementById('handoff-department');
                const projectSelect = document.getElementById('handoff-project');
                departmentSelect.innerHTML = '<option value="">Loading departments…</option>';
                projectSelect.innerHTML = '<option value="">Department backlog</option>';
                projectSelect.disabled = true;
                bootstrap.Modal.getOrCreateInstance(document.getElementById('taskHandoffModal')).show();
                try {
                    const optionsUrl = taskHandoffOptionsTemplate.replace(':id', card.dataset.id)
                        + (currentDepartmentId ? `?source_department_id=${currentDepartmentId}` : '');
                    const result = await request(optionsUrl, 'GET');
                    handoffDepartments = result.departments || [];
                    departmentSelect.innerHTML = '<option value="">Select department</option>' + handoffDepartments.map(item => `<option value="${item.id}">${escapeHtml(item.name)}</option>`).join('');
                } catch (error) {
                    departmentSelect.innerHTML = '<option value="">Unable to load departments</option>';
                    notify(error.message, true);
                }
                return;
            }
            const placement = e.target.closest('.place-task');
            if (placement) {
                const card = placement.closest('.jira-card');
                document.getElementById('placement-task-id').value = card.dataset.id;
                document.getElementById('placement-task-title').textContent = card.dataset.title;
                document.getElementById('placement-project').value = '';
                filterSprintOptions(document.getElementById('placement-sprint'), '');
                bootstrap.Modal.getOrCreateInstance(document.getElementById('taskPlacementModal')).show();
                return;
            }
            const edit = e.target.closest('.edit-task');
            if (edit) {
                const card = edit.closest('.jira-card');
                if (departmentMode) filterSprintOptions(document.getElementById('edit-sprint'), card.dataset.projectId);
                document.getElementById('edit-task-id').value = card.dataset.id;
                document.getElementById('edit-title').value = card.dataset.title;
                document.getElementById('edit-column').value = card.dataset.columnId;
                document.getElementById('edit-priority').value = card.dataset.priority;
                document.getElementById('edit-due-date').value = card.dataset.dueDate || '';
                document.getElementById('edit-sprint').value = card.dataset.sprintId || '';
                let assignedIds = new Set((card.dataset.assigneeIds || '').split(',').filter(Boolean));
                [...document.getElementById('edit-assignee').options].forEach(option => option.selected = assignedIds.has(option.value));
                renderEditAssigneeSelection();
                try {
                    const result = await request(taskShowTemplate.replace(':id', card.dataset.id), 'GET');
                    assignedIds = new Set((result.task.users || []).map(user => String(user.id)));
                    [...document.getElementById('edit-assignee').options].forEach(option => option.selected = assignedIds.has(option.value));
                    renderEditAssigneeSelection();
                    document.getElementById('edit-priority').value = String(result.task.priority ?? 1);
                    TaskDescriptionEditor.set('edit-description', result.task.description || '');
                    @if($canManageEstimates) document.getElementById('edit-estimate-hours').value = result.task.original_estimate_minutes ? result.task.original_estimate_minutes / 60 : '';
                    @endif
                } catch (error) {
                    return notify(error.message, true);
                }
                bootstrap.Modal.getOrCreateInstance(document.getElementById('taskEditModal')).show();
            }
            const remove = e.target.closest('.delete-task');
            if (remove) {
                const confirmation = await confirmDelete('Delete this issue?', 'The issue, its subtasks, comments, and attachments will be permanently deleted.');
                if (!confirmation.isConfirmed) return;
                try {
                    Swal.fire({
                        title: 'Deleting issue...',
                        allowOutsideClick: false,
                        didOpen: () => Swal.showLoading()
                    });
                    await request(taskDeleteTemplate.replace(':id', remove.dataset.id), 'DELETE');
                    await Swal.fire({
                        title: 'Issue deleted',
                        text: 'The issue was deleted successfully.',
                        icon: 'success',
                        timer: 1400,
                        showConfirmButton: false
                    });
                    reload();
                } catch (error) {
                    Swal.close();
                    notify(error.message, true);
                }
            }
            const removeSubtask = e.target.closest('.delete-subtask');
            if (removeSubtask) {
                const confirmation = await confirmDelete('Delete this subtask?', 'This subtask will be permanently deleted.');
                if (!confirmation.isConfirmed) return;
                try {
                    await request(subtaskDeleteTemplate.replace(':id', removeSubtask.dataset.id), 'DELETE');
                    notify('Subtask deleted successfully.');
                    await refreshDetails();
                } catch (error) {
                    notify(error.message, true);
                }
            }
            const removeComment = e.target.closest('.delete-comment');
            if (removeComment) {
                const confirmation = await confirmDelete('Delete this comment?', 'This comment will be permanently removed.');
                if (!confirmation.isConfirmed) return;
                try {
                    await request(commentDeleteTemplate.replace(':id', removeComment.dataset.id), 'DELETE');
                    await refreshDetails();
                } catch (error) {
                    notify(error.message, true);
                }
            }
            const removeAttachment = e.target.closest('.delete-attachment');
            if (removeAttachment) {
                const confirmation = await confirmDelete('Delete this attachment?', 'The uploaded file will be permanently removed.');
                if (!confirmation.isConfirmed) return;
                try {
                    await request(attachmentDeleteTemplate.replace(':id', removeAttachment.dataset.id), 'DELETE');
                    await refreshDetails();
                } catch (error) {
                    notify(error.message, true);
                }
            }
        });

        document.getElementById('handoff-department')?.addEventListener('change', e => {
            const department = handoffDepartments.find(item => String(item.id) === e.currentTarget.value);
            const projectSelect = document.getElementById('handoff-project');
            projectSelect.innerHTML = '<option value="">Department backlog (choose project later)</option>' + (department?.projects || []).map(project => `<option value="${project.id}">${escapeHtml(project.name)}</option>`).join('');
            projectSelect.disabled = !department;
        });
        document.getElementById('placement-project')?.addEventListener('change', e => filterSprintOptions(document.getElementById('placement-sprint'), e.currentTarget.value));
        document.getElementById('submit-task-placement')?.addEventListener('click', async e => {
            const taskId = document.getElementById('placement-task-id').value;
            const projectId = document.getElementById('placement-project').value;
            if (!projectId) return notify('Select a project.', true);
            e.currentTarget.disabled = true;
            try {
                const result = await request(taskPlacementStoreTemplate.replace(':id', taskId), 'POST', {project_id: projectId, sprint_id: document.getElementById('placement-sprint').value || null});
                notify(result.message);
                window.location.reload();
            } catch (error) { notify(error.message, true); }
            finally { e.currentTarget.disabled = false; }
        });

        document.getElementById('submit-task-handoff')?.addEventListener('click', async e => {
            const taskId = document.getElementById('handoff-task-id').value;
            const departmentId = document.getElementById('handoff-department').value;
            const destinationProjectId = document.getElementById('handoff-project').value;
            if (!departmentId) return notify('Select a destination department.', true);
            e.currentTarget.disabled = true;
            setBoardLoading(true, 'Creating department handoff…');
            try {
                const result = await request(taskHandoffStoreTemplate.replace(':id', taskId), 'POST', {
                    source_department_id: currentDepartmentId,
                    destination_department_id: departmentId,
                    destination_project_id: destinationProjectId,
                    notes: document.getElementById('handoff-notes').value.trim()
                }, false);
                bootstrap.Modal.getOrCreateInstance(document.getElementById('taskHandoffModal')).hide();
                notify(result.message);
                window.location.reload();
            } catch (error) {
                notify(error.message, true);
            } finally {
                e.currentTarget.disabled = false;
                setBoardLoading(false);
            }
        });

        let quickInviteLookup = 0;
        document.getElementById('quick-assignee-search')?.addEventListener('input', async event => {
            const query = event.currentTarget.value.trim().toLowerCase();
            const lookup = ++quickInviteLookup;
            let visible = 0;
            document.querySelectorAll('.quick-assignee-option').forEach(option => {
                const matches = !query || option.dataset.search.includes(query);
                option.classList.toggle('d-none', !matches);
                if (matches) visible++;
            });
            const exactEmployee = [...document.querySelectorAll('.quick-assignee-option[data-user-id]')].some(option => option.dataset.userId && option.dataset.search.split(' ').includes(query));
            const validEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(query);
            const invite = document.getElementById('quick-assignee-invite-actions');
            const existing = document.getElementById('quick-assignee-existing');
            invite.classList.add('d-none');
            invite.classList.remove('d-flex');
            existing.classList.add('d-none');
            existing.classList.remove('d-flex');
            document.getElementById('quick-assignee-invite-email').textContent = query;
            if (validEmail && !exactEmployee && quickAssigneeCard) {
                try {
                    const result = await request(`${taskInviteCheckTemplate.replace(':id', quickAssigneeCard.dataset.id)}?email=${encodeURIComponent(query)}`, 'GET', {}, false);
                    if (lookup !== quickInviteLookup) return;
                    if (result.exists) {
                        document.getElementById('quick-assignee-existing-name').textContent = result.employee.name;
                        document.getElementById('quick-assignee-existing-email').textContent = result.employee.email;
                        document.getElementById('quick-assignee-existing-avatar').src = result.employee.profile_img || @json(asset('assets/img/user-picture.png'));
                        existing.dataset.userId = result.employee.id;
                        existing.dataset.userName = result.employee.name;
                        existing.dataset.avatar = result.employee.profile_img || @json(asset('assets/img/user-picture.png'));
                        existing.disabled = !result.assignable;
                        document.getElementById('quick-assignee-existing-help').textContent = result.assignable ? 'Existing employee - click to assign.' : 'This employee is not currently eligible for assignment.';
                        document.getElementById('quick-assignee-existing-help').className = `d-block ${result.assignable ? 'text-success' : 'text-warning'}`;
                        existing.classList.remove('d-none');
                        existing.classList.add('d-flex');
                    } else {
                        invite.classList.remove('d-none');
                        invite.classList.add('d-flex');
                    }
                } catch (_) {}
            }
            document.getElementById('quick-assignee-empty').classList.toggle('d-none', visible > 0 || validEmail);
        });

        const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, character => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            "'": '&#039;',
            '"': '&quot;'
        } [character]));
        const updateCardSubtaskCount = (taskId, count) => {
            const card = document.querySelector(`.jira-card[data-id="${taskId}"]`);
            if (!card) return;
            const indicator = card.querySelector('.subtask-count-indicator');
            indicator.querySelector('.subtask-count').textContent = count;
            indicator.classList.toggle('d-none', count === 0);
        };
        const renderDetails = task => {
            const priorities = {
                0: {label: 'Low', icon: 'fa-arrow-down', className: 'bg-label-secondary'},
                1: {label: 'Normal', icon: 'fa-equals', className: 'bg-label-info'},
                2: {label: 'High', icon: 'fa-arrow-up', className: 'bg-label-warning'},
                3: {label: 'Urgent', icon: 'fa-angles-up', className: 'bg-label-danger'}
            };
            const priority = priorities[Number(task.priority)] || priorities[1];
            document.getElementById('details-key').textContent = `${@json(strtoupper(substr($departmentMode ? $department->dept_name : $project->name, 0, 3)))}-${task.id}`;
            document.getElementById('details-title').textContent = task.title;
            const priorityBadge = document.getElementById('details-priority');
            priorityBadge.className = `badge ${priority.className}`;
            priorityBadge.title = `Priority: ${priority.label}`;
            priorityBadge.setAttribute('aria-label', `Priority: ${priority.label}`);
            priorityBadge.innerHTML = `<i class="fa ${priority.icon} me-1" aria-hidden="true"></i>${priority.label} priority`;
            document.getElementById('details-description').innerHTML = task.description || '<span class="text-muted">No description provided.</span>';
            const handoffPanel = document.getElementById('details-handoff');
            const handoffEntries = [];
            if (task.incoming_handoff) {
                const handoff = task.incoming_handoff;
                handoffEntries.push({
                    direction: 'Received',
                    from: handoff.source_department?.dept_name || 'Previous department',
                    to: handoff.destination_department?.dept_name || task.department?.dept_name || 'Current department',
                    task: handoff.source_task?.title || task.title,
                    project: handoff.source_project?.name || 'Department backlog',
                    actor: handoff.actor?.name || 'System',
                    notes: handoff.notes,
                    date: handoff.created_at
                });
            }
            (task.outgoing_handoffs || []).forEach(handoff => handoffEntries.push({
                direction: 'Sent',
                from: handoff.source_department?.dept_name || task.department?.dept_name || 'Source department',
                to: handoff.destination_department?.dept_name || 'Receiving department',
                task: handoff.destination_task?.title || task.title,
                project: handoff.destination_project?.name || 'Department backlog',
                actor: handoff.actor?.name || 'System',
                notes: handoff.notes,
                date: handoff.created_at
            }));
            handoffPanel.classList.toggle('d-none', handoffEntries.length === 0);
            handoffPanel.innerHTML = handoffEntries.length ? `
                <div class="d-flex align-items-center gap-2 mb-3"><span class="avatar avatar-sm bg-label-primary rounded d-inline-flex align-items-center justify-content-center"><i class="fa fa-arrow-right-arrow-left"></i></span><div><strong>Task handoff history</strong><div class="small text-muted">Department transfer and ownership trail</div></div></div>
                ${handoffEntries.map((entry, index) => `<div class="${index ? 'border-top pt-3 mt-3' : ''}">
                    <div class="handoff-history-route"><span>${escapeHtml(entry.from)}</span><i class="fa fa-arrow-right text-primary"></i><span>${escapeHtml(entry.to)}</span><span class="badge bg-label-${entry.direction === 'Received' ? 'info' : 'success'}">${entry.direction}</span></div>
                    <div class="small text-muted mt-2"><i class="fa fa-folder me-1"></i>${escapeHtml(entry.project)} <span class="mx-1">•</span> ${escapeHtml(entry.task)}</div>
                    <div class="small text-muted mt-1"><i class="fa fa-user me-1"></i>${escapeHtml(entry.actor)}${entry.date ? ` <span class="mx-1">•</span> ${new Date(entry.date).toLocaleString([], {month:'short', day:'numeric', year:'numeric', hour:'numeric', minute:'2-digit'})}` : ''}</div>
                    ${entry.notes ? `<div class="handoff-history-notes"><strong class="d-block small mb-1">Handoff notes</strong>${escapeHtml(entry.notes)}</div>` : ''}
                </div>`).join('')}` : '';
            const timeTracking = task.time_tracking || {
                formatted: '0m',
                by_user: []
            };
            document.getElementById('details-time-total').textContent = `${timeTracking.formatted} logged`;
            const timerControls = document.getElementById('details-timer-controls');
            if (task.directly_assigned_to_me && task.status !== 'completed') {
                timerControls.innerHTML = task.own_running_timer ?
                    `<button type="button" class="btn btn-sm btn-success project-task-timer" data-action="stop"><i class="fa fa-stop"></i><span>Stop timer</span></button>` :
                    `<button type="button" class="btn btn-sm btn-primary project-task-timer" data-action="start"><i class="fa fa-play"></i><span>Start timer</span></button>`;
            } else {
                timerControls.innerHTML = '<i class="fa fa-clock fs-3 text-primary"></i>';
            }
            document.getElementById('details-time-users').innerHTML = timeTracking.by_user.length ? timeTracking.by_user.map(person => `
            <div class="d-flex justify-content-between align-items-center py-1"><div class="d-flex align-items-center gap-2"><img class="jira-avatar" src="${person.profile_img || @json(asset('assets/img/user-picture.png'))}"><span>${escapeHtml(person.name)}</span></div><strong>${escapeHtml(person.formatted)}</strong></div>`).join('') : '<small class="text-muted">No time logged on this issue yet.</small>';
            const estimateMinutes = Number(task.original_estimate_minutes || 0);
            const estimatePercent = estimateMinutes ? Math.round((Number(timeTracking.total_seconds || 0) / (estimateMinutes * 60)) * 100) : 0;
            const estimateColor = estimatePercent > 100 ? 'danger' : (estimatePercent >= 80 ? 'warning' : 'success');
            document.getElementById('details-estimate').innerHTML = `<div class="d-flex justify-content-between"><span>Original estimate</span><strong class="text-${estimateColor}">${estimateMinutes ? `${Math.floor(estimateMinutes/60)}h ${estimateMinutes%60}m (${estimatePercent}%)` : 'Not set'}</strong></div>${estimateMinutes ? `<div class="progress mt-2" style="height:7px"><div class="progress-bar bg-${estimateColor}" style="width:${Math.min(estimatePercent,100)}%"></div></div>` : ''}`;
            const subtasks = task.subtasks || [];
            updateCardSubtaskCount(task.id, subtasks.length);
            const done = subtasks.filter(item => item.status === 'completed').length;
            document.getElementById('subtask-progress').textContent = `${done}/${subtasks.length} complete`;
            document.getElementById('subtask-list').innerHTML = subtasks.length ? subtasks.map(item => `
            <div class="list-group-item d-flex justify-content-between align-items-center">
                <div><i class="fa ${item.status === 'completed' ? 'fa-circle-check text-success' : 'fa-circle text-muted'} me-2"></i>${escapeHtml(item.title)}<span class="badge bg-label-secondary ms-2">${escapeHtml(item.status.replace('_', ' '))}</span></div>
                @can('sub-task-trash')<button class="btn btn-sm btn-icon text-danger delete-subtask" data-id="${item.id}"><i class="fa fa-trash"></i></button>@endcan
            </div>`).join('') : '<div class="jira-empty">No subtasks yet</div>';
            const attachments = task.attachments || [];
            document.getElementById('attachment-count').textContent = `${attachments.length} file(s)`;
            document.getElementById('attachment-list').innerHTML = attachments.length ? attachments.map(file => `
            <div class="list-group-item d-flex justify-content-between align-items-center"><a href="${file.url}" target="_blank" rel="noopener"><i class="fa fa-paperclip me-2"></i>${escapeHtml(file.original_name)}</a><button class="btn btn-sm btn-icon text-danger delete-attachment" data-id="${file.id}"><i class="fa fa-trash"></i></button></div>`).join('') : '<div class="jira-empty">No attachments</div>';
            const comments = task.comments || [];
            document.getElementById('comment-count').textContent = comments.length;
            const commentList = document.getElementById('comment-list');
            commentList.innerHTML = comments.length ? comments.slice().reverse().map(comment => {
                const ownComment = Number(comment.user_id) === Number(currentUserId);
                return `<div class="issue-comment ${ownComment ? 'own-comment' : ''}">
                <img class="issue-comment-avatar" src="${comment.user?.profile_img || @json(asset('assets/img/user-picture.png'))}" alt="">
                <div class="issue-comment-content">
                    <div class="issue-comment-meta"><span class="issue-comment-name" title="${escapeHtml(comment.user?.name || 'Deleted user')}">${escapeHtml(comment.user?.name || 'Deleted user')}</span><span>${new Date(comment.created_at).toLocaleString([], {month:'short',day:'numeric',hour:'numeric',minute:'2-digit'})}</span></div>
                    <div class="issue-comment-bubble">${escapeHtml(comment.body)}<button class="btn btn-sm btn-icon issue-comment-delete delete-comment" data-id="${comment.id}" title="Delete comment"><i class="fa fa-trash"></i></button></div>
                </div>
            </div>`;
            }).join('') : '<div class="jira-empty"><i class="fa fa-comments d-block mb-2 fs-4"></i>No comments yet.<br><small>Start the conversation below.</small></div>';
            requestAnimationFrame(() => {
                commentList.scrollTop = commentList.scrollHeight;
            });
        };
        const refreshDetails = async () => {
            const result = await request(taskShowTemplate.replace(':id', detailsTaskId), 'GET');
            renderDetails(result.task);
        };
        document.getElementById('details-timer-controls')?.addEventListener('click', async event => {
            const button = event.target.closest('.project-task-timer');
            if (!button || !detailsTaskId) return;
            const controls = document.querySelectorAll('#details-timer-controls .project-task-timer');
            controls.forEach(control => control.disabled = true);
            try {
                const action = button.dataset.action;
                await request(timerUrls[action], 'POST', {
                    task_id: detailsTaskId,
                    subtask_id: null
                });
                window.dispatchEvent(new CustomEvent('task-timer-updated', {
                    detail: {
                        is_running: action === 'start',
                        task_id: detailsTaskId,
                        subtask_id: null,
                        project_id: projectId,
                        project_name: @json($departmentMode ? $department->dept_name : $project->name),
                        task_title: document.getElementById('details-title').textContent
                    }
                }));
                await refreshDetails();
                notify(action === 'stop' ? 'Timer stopped and tracked time saved.' : 'Timer started.');
            } catch (error) {
                controls.forEach(control => control.disabled = false);
                notify(error.message, true);
            }
        });
        document.getElementById('add-subtask')?.addEventListener('click', async () => {
            const input = document.getElementById('new-subtask-title');
            if (!input.value.trim()) return notify('Subtask title is required.', true);
            try {
                await request(subtaskStoreUrl, 'POST', {
                    title: input.value.trim(),
                    task_id: detailsTaskId,
                    status: 'pending',
                    priority: 1
                });
                input.value = '';
                await refreshDetails();
            } catch (error) {
                notify(error.message, true);
            }
        });
        document.getElementById('add-comment')?.addEventListener('click', async () => {
            const input = document.getElementById('new-comment');
            if (!input.value.trim()) return notify('Comment cannot be empty.', true);
            try {
                await request(commentStoreTemplate.replace(':id', detailsTaskId), 'POST', {
                    body: input.value.trim()
                });
                input.value = '';
                await refreshDetails();
            } catch (error) {
                notify(error.message, true);
            }
        });
        document.getElementById('new-comment')?.addEventListener('keydown', event => {
            if (event.key === 'Enter' && (event.ctrlKey || event.metaKey)) {
                event.preventDefault();
                document.getElementById('add-comment').click();
            }
        });
        document.getElementById('upload-attachments')?.addEventListener('click', async () => {
            const input = document.getElementById('issue-attachments');
            if (!input.files.length) return notify('Select at least one file.', true);
            const data = new FormData();
            Array.from(input.files).forEach(file => data.append('attachments[]', file));
            try {
                await formRequest(attachmentStoreTemplate.replace(':id', detailsTaskId), 'POST', data);
                input.value = '';
                await refreshDetails();
            } catch (error) {
                notify(error.message, true);
            }
        });

        document.getElementById('save-task')?.addEventListener('click', async () => {
            TaskDescriptionEditor.sync();
            const id = document.getElementById('edit-task-id').value;
            try {
                const updateData = {
                    title: document.getElementById('edit-title').value.trim(),
                    description: document.getElementById('edit-description').value.trim() || null,
                    priority: Number(document.getElementById('edit-priority').value),
                    due_date: document.getElementById('edit-due-date').value || null,
                    sprint_id: document.getElementById('edit-sprint').value || null,
                    assignee_ids: [...document.getElementById('edit-assignee').selectedOptions].map(option => Number(option.value))
                    @if($canManageEstimates),
                    original_estimate_minutes: Math.round(Number(document.getElementById('edit-estimate-hours').value) * 60) || undefined @endif
                };
                if (canChangeStatus && !departmentMode) {
                    updateData.workflow_id = selectedWorkflowId;
                    updateData.workflow_column_id = document.getElementById('edit-column').value;
                }
                await request(boardUpdateTemplate.replace(':id', id), 'PATCH', updateData);
                reload();
            } catch (error) {
                notify(error.message, true);
            }
        });
        document.getElementById('edit-assignee')?.addEventListener('change', renderEditAssigneeSelection);

        document.getElementById('create-sprint')?.addEventListener('click', async () => {
            try {
                const result = await request(sprintStoreUrl, 'POST', {
                    name: document.getElementById('sprint-name').value.trim(),
                    goal: document.getElementById('sprint-goal').value.trim() || null,
                    start_date: document.getElementById('sprint-start').value || null,
                    end_date: document.getElementById('sprint-end').value || null,
                    status: document.getElementById('sprint-active').checked ? 'active' : 'planned'
                });
                window.location.href = `${window.location.pathname}?sprint=${result.sprint.id}`;
            } catch (error) {
                notify(error.message, true);
            }
        });

        document.querySelector('.sprint-state-btn')?.addEventListener('click', async e => {
            try {
                await request(sprintUpdateTemplate.replace(':id', selectedSprintId), 'PUT', {
                    status: e.currentTarget.dataset.status
                });
                reload();
            } catch (error) {
                notify(error.message, true);
            }
        });
    })();
</script>
@endpush
