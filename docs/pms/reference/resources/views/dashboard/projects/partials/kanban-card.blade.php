@php
    $priority = [
        0 => ['Low', 'text-secondary', 'fa-arrow-down'],
        1 => ['Normal', 'text-info', 'fa-equals'],
        2 => ['High', 'text-warning', 'fa-arrow-up'],
        3 => ['Urgent', 'text-danger', 'fa-angles-up'],
    ][$task->priority ?? 1];
    $assignee = $task->users->first();
    $assigneeIds = $task->users->pluck('id')->map(fn ($id) => (int) $id);
    $dueValue = $task->due_date ? \Carbon\Carbon::parse($task->due_date)->format('Y-m-d\TH:i') : '';
    $trackedSeconds = $task->timerLogs->sum(fn ($log) => $log->start_time ? $log->start_time->diffInSeconds($log->end_time ?? now()) : 0);
    $trackedHours = intdiv($trackedSeconds, 3600);
    $trackedMinutes = intdiv($trackedSeconds % 3600, 60);
    $trackedLabel = trim(($trackedHours ? $trackedHours.'h ' : '').($trackedMinutes ? $trackedMinutes.'m' : '')) ?: '< 1m';
    $estimateSeconds = ($task->original_estimate_minutes ?? 0) * 60;
    $estimatePercent = $estimateSeconds ? ($trackedSeconds / $estimateSeconds) * 100 : 0;
    $estimateColor = $estimatePercent > 100 ? 'danger' : ($estimatePercent >= 80 ? 'warning' : 'success');
@endphp
<article class="jira-card" draggable="{{ $canChangeStatus ? 'true' : 'false' }}"
    data-id="{{ $task->id }}" data-project-id="{{ $task->project_id }}" data-title="{{ $task->title }}" data-status="{{ $task->status }}"
    data-column-id="{{ $task->workflow_column_id }}"
    data-priority="{{ $task->priority ?? 1 }}" data-due-date="{{ $dueValue }}"
    data-sprint-id="{{ $task->sprint_id }}" data-assignee-id="{{ $assignee?->id }}" data-assignee-ids="{{ $assigneeIds->implode(',') }}">
    <div class="d-flex justify-content-between align-items-center">
        <span class="jira-key">{{ strtoupper(substr($project?->name ?? $task->department?->dept_name ?? 'TSK', 0, 3)) }}-{{ $task->id }}</span>
        <div class="dropdown">
            <button class="btn btn-sm btn-icon" data-bs-toggle="dropdown"><i class="fa fa-ellipsis"></i></button>
            <div class="dropdown-menu dropdown-menu-end">
                @if($canEdit)<button class="dropdown-item edit-task"><i class="fa fa-pen me-2"></i>Edit</button>@endif
                @can('task-handoff')@if($task->outgoingHandoffs->isEmpty())<button class="dropdown-item handoff-task"><i class="fa fa-arrow-right-arrow-left me-2"></i>Hand off</button>@endif @endcan
                @if(!$task->project_id)@can('task-edit')<button class="dropdown-item place-task"><i class="fa fa-folder-plus me-2"></i>Place in project</button>@endcan @endif
                @if($canDelete)<button class="dropdown-item text-danger delete-task" data-id="{{ $task->id }}"><i class="fa fa-trash me-2"></i>Delete</button>@endif
            </div>
        </div>
    </div>
    <div class="jira-card-title">{{ $task->title }}</div>
    @if($task->incomingHandoff)<div class="mb-2"><span class="badge bg-label-info"><i class="fa fa-arrow-right-arrow-left me-1"></i>From {{ $task->incomingHandoff->sourceDepartment?->dept_name ?? 'another team' }}</span></div>@endif
    @if($task->outgoingHandoffs->isNotEmpty())<div class="mb-2"><span class="badge bg-label-success"><i class="fa fa-circle-check me-1"></i>Handed off</span></div>@endif
    @if($showProject ?? false)<div class="mb-2"><span class="badge bg-label-primary"><i class="fa {{ $task->project ? 'fa-folder' : 'fa-building' }} me-1"></i>{{ $task->project?->name ?? 'Department backlog' }}</span>@if($task->sprint)<span class="badge bg-label-secondary ms-1">{{ $task->sprint->name }}</span>@endif</div>@endif
    <div class="d-flex justify-content-between align-items-center gap-2">
        <div class="d-flex align-items-center gap-2">
            <span class="jira-priority {{ $priority[1] }}" title="Priority: {{ $priority[0] }}"
                aria-label="Priority: {{ $priority[0] }}" data-bs-toggle="tooltip" data-bs-placement="top">
                <i class="fa {{ $priority[2] }}" aria-hidden="true"></i>
            </span>
            <span class="small text-muted subtask-count-indicator {{ $task->subtasks->count() ? '' : 'd-none' }}">
                <i class="fa fa-list-check me-1"></i><span class="subtask-count">{{ $task->subtasks->count() }}</span>
            </span>
            @if($task->comments_count)<span class="small text-muted"><i class="fa fa-comment me-1"></i>{{ $task->comments_count }}</span>@endif
            @if($task->attachments_count)<span class="small text-muted"><i class="fa fa-paperclip me-1"></i>{{ $task->attachments_count }}</span>@endif
            @if($trackedSeconds)<span class="small text-muted" title="Total time logged"><i class="fa fa-clock me-1"></i>{{ $trackedLabel }}</span>@endif
            @if($estimateSeconds)<span class="badge bg-label-{{ $estimateColor }}" title="Estimate usage">{{ round($estimatePercent) }}%</span>@endif
            @if($task->due_date)<span class="small {{ \Carbon\Carbon::parse($task->due_date)->isPast() && $task->status !== 'completed' ? 'text-danger' : 'text-muted' }}"><i class="fa fa-calendar me-1"></i>{{ \Carbon\Carbon::parse($task->due_date)->format('M d') }}</span>@endif
        </div>
        @if($canQuickAssign)
            <div class="quick-assignee-control">
                <button type="button" class="btn btn-sm p-0 border-0 quick-assignee-toggle"
                    title="{{ $assignee ? 'Manage assignees' : 'Assign employee' }}">
                    @if($task->users->isNotEmpty())
                        <span class="quick-assignee-avatars d-flex align-items-center">
                            @foreach($task->users->take(3) as $person)<img class="jira-avatar quick-assignee-avatar" style="margin-left:{{ $loop->first ? 0 : -7 }}px" src="{{ $person->profile_img ?: asset('assets/img/user-picture.png') }}" title="{{ $person->name }}" alt="{{ $person->name }}">@endforeach
                            @if($task->users->count() > 3)<span class="badge rounded-pill bg-label-primary ms-1">+{{ $task->users->count()-3 }}</span>@endif
                        </span>
                    @else
                        <span class="jira-avatar quick-assignee-avatar d-inline-flex align-items-center justify-content-center"><i class="fa fa-user-plus text-primary"></i></span>
                    @endif
                </button>
            </div>
        @elseif($task->users->isNotEmpty())
            <span class="d-flex align-items-center">@foreach($task->users->take(3) as $person)<img class="jira-avatar" style="margin-left:{{ $loop->first ? 0 : -7 }}px" src="{{ $person->profile_img ?: asset('assets/img/user-picture.png') }}" title="{{ $person->name }}" alt="{{ $person->name }}">@endforeach @if($task->users->count()>3)<small class="ms-1">+{{ $task->users->count()-3 }}</small>@endif</span>
        @else
            <span class="jira-avatar d-inline-flex align-items-center justify-content-center" title="Unassigned"><i class="fa fa-user text-muted"></i></span>
        @endif
    </div>
</article>
