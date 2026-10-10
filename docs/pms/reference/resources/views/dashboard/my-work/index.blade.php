@extends('layouts.app')

@push('css-before')
<style>
    .work-shell{background:#f4f5f7;border-radius:14px;padding:20px}.work-toolbar{display:flex;gap:14px;justify-content:space-between;align-items:flex-start;flex-wrap:wrap}.work-board{display:grid;grid-auto-flow:column;grid-auto-columns:minmax(290px,1fr);gap:14px;overflow-x:auto;padding-bottom:8px}.work-column{background:#ebecf0;border-radius:10px;min-height:560px;padding:10px}.work-column.drag-over{outline:2px dashed #7367f0;background:#e8e6ff}.work-column-title{display:flex;justify-content:space-between;align-items:center;padding:5px 6px 12px;font-size:.78rem;font-weight:700;letter-spacing:.04em;text-transform:uppercase}.work-list{min-height:490px}.work-card{background:#fff;border:1px solid #dfe1e6;border-radius:8px;padding:12px;margin-bottom:10px;box-shadow:0 1px 2px rgba(9,30,66,.12);cursor:pointer;transition:.15s ease}.work-card:hover{box-shadow:0 4px 12px rgba(9,30,66,.18);transform:translateY(-1px)}.work-card.dragging{opacity:.45}.work-key{color:#6b778c;font-size:.72rem;font-weight:700}.work-title{color:#172b4d;font-weight:600;margin:8px 0 12px;overflow-wrap:anywhere}.work-avatar{width:27px;height:27px;border-radius:50%;object-fit:cover;background:#dfe1e6;border:2px solid #fff;margin-left:-6px}.work-avatar:first-child{margin-left:0}.work-meta{font-size:.74rem;color:#6b778c}.work-empty{text-align:center;color:#8993a4;padding:38px 8px;font-size:.85rem}.filter-card{background:#fff;border:1px solid #e3e5e8;border-radius:10px}.project-chip{max-width:155px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}@media(max-width:1200px){.work-board{grid-auto-columns:290px}}
    .work-details-main{padding:1.5rem;max-height:76vh;overflow-y:auto}.work-comments-panel{display:flex;flex-direction:column;height:76vh;background:#f8f9fb;border-left:1px solid #e3e5e8}.work-comments-list{flex:1;overflow-y:auto;padding:1rem;scrollbar-width:thin}.work-comment{display:flex;gap:.5rem;margin-bottom:.8rem;align-items:flex-start}.work-comment.own-comment{flex-direction:row-reverse}.work-comment-avatar{width:26px;height:26px;border-radius:50%;object-fit:cover;flex:0 0 26px}.work-comment-content{min-width:0;max-width:85%}.work-comment-meta{display:flex;gap:.4rem;align-items:center;margin:0 4px 3px;font-size:.68rem;color:#7a8494}.own-comment .work-comment-meta{justify-content:flex-end}.work-comment-name{font-weight:600;color:#4d5870;max-width:130px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.work-comment-bubble{padding:.55rem .7rem;border-radius:10px;background:#fff;border:1px solid #e3e5e8;font-size:.82rem;line-height:1.35;white-space:pre-wrap;overflow-wrap:anywhere}.own-comment .work-comment-bubble{background:#7367f0;border-color:#7367f0;color:#fff}@media(max-width:991.98px){.work-details-main{max-height:none}.work-comments-panel{height:55vh;border-left:0;border-top:1px solid #e3e5e8}}
    .work-modal-close{width:34px;height:34px;display:inline-flex;align-items:center;justify-content:center;flex:0 0 34px;border-radius:8px}
</style>
@endpush

@section('content')
@php
    $columns = $selectedWorkflow?->columns ?? collect();
    $priorities = [
        0 => ['Low', 'text-secondary', 'fa-arrow-down'],
        1 => ['Normal', 'text-info', 'fa-equals'],
        2 => ['High', 'text-warning', 'fa-arrow-up'],
        3 => ['Urgent', 'text-danger', 'fa-angles-up'],
    ];
    $canChangeStatus = Gate::allows('task-status');
@endphp

<div class="work-shell">
    <div class="work-toolbar mb-3">
        <div><div class="text-muted small mb-1">WORKSPACE / ASSIGNED ISSUES</div><h3 class="mb-1">{{ $canManageTeam && request('assignee') ? 'Team member board' : 'My Work' }}</h3><div class="text-muted">{{ $tasks->count() }} issues across your accessible projects and sprints.</div></div>
        <a href="{{ route('my-work.index') }}" class="btn btn-label-secondary"><i class="fa fa-rotate-left me-1"></i>Reset board</a>
    </div>

    <div class="filter-card p-3 mb-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3"><label class="form-label">Project</label><select name="project" class="form-select"><option value="">All projects</option>@foreach($projects as $project)<option value="{{ $project->id }}" @selected((string)request('project') === (string)$project->id)>{{ $project->name }}</option>@endforeach</select></div>
            <div class="col-md-3"><label class="form-label">Sprint</label><select name="sprint" class="form-select"><option value="">All sprints and backlog</option>@foreach($sprints as $sprint)<option value="{{ $sprint->id }}" @selected((string)request('sprint') === (string)$sprint->id)>{{ $sprint->name }} - {{ ucfirst($sprint->status) }}</option>@endforeach</select></div>
            <div class="col-md-2"><label class="form-label">Workflow</label><select name="workflow" class="form-select">@foreach($workflows as $workflow)<option value="{{ $workflow->id }}" @selected($selectedWorkflow?->id===$workflow->id)>{{ $workflow->name }}</option>@endforeach</select></div>
            @if($canManageTeam)<div class="col-md-2"><label class="form-label">Assignee</label><select name="assignee" class="form-select"><option value="">Everyone</option>@foreach($assignees as $person)<option value="{{ $person->id }}" @selected((string)request('assignee') === (string)$person->id)>{{ $person->name }}</option>@endforeach</select></div>@endif
            <div class="col-md-2"><button class="btn btn-primary w-100"><i class="fa fa-filter me-1"></i>Apply filters</button></div>
        </form>
    </div>

    <div class="work-board">
        @foreach($columns as $column)
            @php
                $columnTasks = $tasks->where('workflow_column_id', $column->id);
            @endphp
            <section class="work-column" data-column-id="{{ $column->id }}">
                <div class="work-column-title"><span class="badge bg-label-{{ $column->color }}">{{ $column->name }}</span><span class="column-count badge bg-white text-dark">{{ $columnTasks->count() }}</span></div>
                <div class="work-list">
                    @forelse($columnTasks as $task)
                        @php
                            $priority = $priorities[$task->priority ?? 1];
                            $trackedSeconds = $task->timerLogs->sum(fn ($log) => $log->start_time ? $log->start_time->diffInSeconds($log->end_time ?? now()) : 0);
                            $trackedHours = intdiv($trackedSeconds, 3600);
                            $trackedMinutes = intdiv($trackedSeconds % 3600, 60);
                            $trackedLabel = trim(($trackedHours ? $trackedHours.'h ' : '').($trackedMinutes ? $trackedMinutes.'m' : '')) ?: '< 1m';
                            $estimateSeconds = ($task->original_estimate_minutes ?? 0) * 60;
                            $estimatePercent = $estimateSeconds ? ($trackedSeconds / $estimateSeconds) * 100 : 0;
                            $estimateColor = $estimatePercent > 100 ? 'danger' : ($estimatePercent >= 80 ? 'warning' : 'success');
                        @endphp
                        <article class="work-card" draggable="{{ $canChangeStatus ? 'true' : 'false' }}" data-id="{{ $task->id }}" data-status="{{ $task->status }}" data-column-id="{{ $task->workflow_column_id }}" data-title="{{ $task->title }}" data-assignee-id="{{ $task->users->first()?->id }}" data-assignee-ids="{{ $task->users->pluck('id')->implode(',') }}">
                            <div class="d-flex justify-content-between gap-2"><span class="work-key">{{ strtoupper(substr($task->project->name,0,3)) }}-{{ $task->id }}</span><span class="badge bg-label-primary project-chip" title="{{ $task->project->name }}">{{ $task->project->name }}</span></div>
                            <div class="work-title">{{ $task->title }}</div>
                            <div class="d-flex align-items-center justify-content-between gap-2 mb-2"><span class="badge bg-label-{{ $task->sprint ? 'info' : 'secondary' }}"><i class="fa fa-bolt me-1"></i>{{ $task->sprint?->name ?? 'Backlog' }}</span>@if($task->sprint)<span class="work-meta">{{ ucfirst($task->sprint->status) }}</span>@endif</div>
                            @if($estimateSeconds)<div class="mb-2"><span class="badge bg-label-{{ $estimateColor }}"><i class="fa fa-hourglass-half me-1"></i>{{ round($estimatePercent) }}% of estimate</span></div>@endif
                            <div class="d-flex justify-content-between align-items-center gap-2">
                                <div class="d-flex align-items-center gap-2 work-meta"><span class="{{ $priority[1] }}" title="{{ $priority[0] }} priority"><i class="fa {{ $priority[2] }}"></i></span>@if($trackedSeconds)<span title="Total time logged"><i class="fa fa-clock"></i> {{ $trackedLabel }}</span>@endif @if($task->subtasks_count)<span title="Subtasks"><i class="fa fa-list-check"></i> {{ $task->subtasks_count }}</span>@endif @if($task->comments_count)<span title="Comments"><i class="fa fa-comment"></i> {{ $task->comments_count }}</span>@endif @if($task->attachments_count)<span title="Attachments"><i class="fa fa-paperclip"></i> {{ $task->attachments_count }}</span>@endif @if($task->due_date)<span class="{{ \Carbon\Carbon::parse($task->due_date)->isPast() && $task->status !== 'completed' ? 'text-danger fw-semibold' : '' }}"><i class="fa fa-calendar"></i> {{ \Carbon\Carbon::parse($task->due_date)->format('M d') }}</span>@endif</div>
                                <div class="d-flex align-items-center">
                                    @if($canQuickAssign)
                                        <button type="button" class="btn btn-sm p-0 border-0 work-assignee-toggle" title="{{ $task->users->isNotEmpty() ? 'Manage assignees' : 'Assign employee' }}">
                                            @if($task->users->isNotEmpty())<span class="work-assignee-avatars d-flex align-items-center">@foreach($task->users->take(3) as $person)<img class="work-avatar work-assignee-avatar" src="{{ $person->profile_img ?: asset('assets/img/user-picture.png') }}" title="{{ $person->name }}" alt="{{ $person->name }}">@endforeach @if($task->users->count()>3)<small class="ms-1">+{{ $task->users->count()-3 }}</small>@endif</span>@else<span class="work-avatar m-0 d-inline-flex align-items-center justify-content-center work-assignee-avatar"><i class="fa fa-user-plus text-primary"></i></span>@endif
                                        </button>
                                    @else
                                        @forelse($task->users->take(3) as $person)
                                            <img class="work-avatar" src="{{ $person->profile_img ?: asset('assets/img/user-picture.png') }}" title="{{ $person->name }}" alt="{{ $person->name }}">
                                        @empty
                                            @if($task->subtasks_count > 0)
                                                <span class="work-meta" title="Assignment is through a subtask"><i class="fa fa-code-branch"></i> Subtask</span>
                                            @else
                                                <span class="work-meta" title="No employee is assigned"><i class="fa fa-user-slash"></i> Unassigned</span>
                                            @endif
                                        @endforelse
                                    @endif
                                </div>
                            </div>
                        </article>
                    @empty<div class="work-empty">No assigned issues</div>@endforelse
                </div>
            </section>
        @endforeach
    </div>
</div>

@if($canQuickAssign)
<div class="modal fade" id="workAssigneeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <div class="modal-header"><div><h5 class="modal-title">Assign issue</h5><small id="work-assignee-task-name" class="text-muted"></small></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="input-group mb-3"><span class="input-group-text"><i class="fa fa-search"></i></span><input id="work-assignee-search" type="search" class="form-control" placeholder="Search a name or enter an email to invite..." autocomplete="off"></div>
            <div id="work-assignee-list" class="list-group" style="max-height:360px;overflow-y:auto">
                <button type="button" class="list-group-item list-group-item-action work-assignee-option d-flex align-items-center gap-2" data-user-id="" data-user-name="Unassigned" data-avatar="" data-search="unassigned"><span class="work-avatar m-0 d-inline-flex align-items-center justify-content-center"><i class="fa fa-user-slash text-muted"></i></span><span>Unassigned</span><i class="work-assignee-check fa fa-check text-success ms-auto d-none"></i></button>
                @foreach($assignees as $person)
                    <button type="button" class="list-group-item list-group-item-action work-assignee-option d-flex align-items-center gap-2" data-user-id="{{ $person->id }}" data-user-name="{{ $person->name }}" data-avatar="{{ $person->profile_img ?: asset('assets/img/user-picture.png') }}" data-search="{{ strtolower($person->name.' '.$person->email) }}"><img class="work-avatar m-0" src="{{ $person->profile_img ?: asset('assets/img/user-picture.png') }}" alt=""><span><strong class="d-block">{{ $person->name }}</strong><small class="text-muted">{{ $person->email }}</small></span><i class="work-assignee-check fa fa-check text-success ms-auto d-none"></i></button>
                @endforeach
                <div id="work-assignee-invite-actions" class="list-group-item d-none align-items-center gap-2"><span class="work-avatar m-0 d-inline-flex align-items-center justify-content-center bg-label-primary"><i class="fa fa-envelope"></i></span><span class="flex-grow-1"><strong>Invite new employee</strong><small id="work-assignee-invite-email" class="d-block text-muted"></small></span><button type="button" id="work-assignee-invite" class="btn btn-sm btn-primary" title="Send invitation by email"><i class="fa fa-paper-plane me-1"></i>Send email</button><button type="button" id="work-assignee-copy" class="btn btn-sm btn-icon btn-label-primary" title="Create and copy invitation link" aria-label="Create and copy invitation link"><i class="fa fa-copy"></i></button></div>
                <button type="button" id="work-assignee-existing" class="list-group-item list-group-item-action work-assignee-option d-none align-items-center gap-2" data-user-id="" data-user-name="" data-avatar="" data-search=""><img id="work-assignee-existing-avatar" class="work-avatar m-0" src="{{ asset('assets/img/user-picture.png') }}" alt=""><span><strong id="work-assignee-existing-name"></strong><small id="work-assignee-existing-email" class="d-block text-muted"></small><small id="work-assignee-existing-help" class="d-block text-success">Existing employee — click to assign.</small></span><i class="work-assignee-check fa fa-user-plus text-primary ms-auto"></i></button>
                <div id="work-assignee-empty" class="work-empty d-none">No employees found.</div>
            </div>
        </div>
    </div></div>
</div>
@endif

<div class="modal fade" id="myWorkIssueModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered"><div class="modal-content overflow-hidden">
        <div class="modal-header"><div><div id="work-details-key" class="work-key"></div><h5 id="work-details-title" class="modal-title">Issue details</h5></div><button type="button" class="btn btn-sm btn-label-secondary work-modal-close" data-bs-dismiss="modal" aria-label="Close" title="Close"><i class="fa fa-xmark"></i></button></div>
        <div class="modal-body p-0"><div class="row g-0"><div class="col-lg-8 work-details-main">
            <div class="d-flex flex-wrap gap-2 mb-3" id="work-details-meta"></div>
            <div id="work-details-description" class="text-muted"></div>
            <div class="card bg-label-primary border-0 mb-4"><div class="card-body">
                <div class="d-flex justify-content-between align-items-center"><div><small>TIME TRACKING</small><h5 id="work-details-time" class="mb-0 mt-1">0s logged</h5></div><div class="d-flex gap-2"><button class="btn btn-primary issue-timer-action" data-action="start"><i class="fa fa-play me-1"></i>Start timer</button><button class="btn btn-success issue-timer-action d-none" data-action="stop"><i class="fa fa-stop me-1"></i>Stop timer</button></div></div>
                <div id="work-details-time-users" class="mt-3"></div>
                <div id="work-estimate-panel" class="mt-3 pt-3 border-top"></div>
            </div></div>
            <h6>Subtasks</h6><div id="work-details-subtasks" class="list-group mb-4"></div>
            <h6>Attachments</h6><div id="work-details-attachments" class="list-group mb-4"></div>
        </div><aside class="col-lg-4 work-comments-panel"><div class="p-3 bg-white border-bottom d-flex justify-content-between align-items-center"><div><h6 class="mb-0"><i class="fa fa-comments me-2 text-primary"></i>Comments</h6><small class="text-muted">Issue conversation</small></div><span id="work-comment-count" class="badge bg-label-primary">0</span></div><div id="work-details-comments" class="work-comments-list"></div></aside></div></div>
        <div class="modal-footer"><a id="work-open-project" class="btn btn-label-secondary"><i class="fa fa-arrow-up-right-from-square me-1"></i>Open project board</a><button class="btn btn-primary" data-bs-dismiss="modal">Close</button></div>
    </div></div>
</div>
@endsection

@push('page-scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const canChangeStatus = @json($canChangeStatus);
    const selectedWorkflowId = @json($selectedWorkflow?->id);
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const updateUrl = @json(route('tasks.board-update', ':id'));
    const taskInviteUrl = @json(route('tasks.invite', ':id'));
    const taskInviteCheckUrl = @json(route('tasks.invite.check', ':id'));
    const showUrl = @json(route('tasks.show', ':id'));
    const timerUrls = {start:@json(route('timer_logs.start')), stop:@json(route('timer_logs.stop'))};
    const currentUserId = @json(auth()->id());
    let activeTask = null;
    let assignmentCard = null;
    const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, character => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[character]));
    const api = async (url, method='GET', data=null) => {
        const response = await fetch(url, {method,headers:{'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':csrf},body:data ? JSON.stringify(data) : null});
        const payload = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(payload.message || 'The request failed.');
        return payload;
    };
    const renderIssue = task => {
        activeTask = task;
        document.getElementById('work-details-key').textContent = `ISSUE-${task.id}`;
        document.getElementById('work-details-title').textContent = task.title;
        document.getElementById('work-details-description').innerHTML = task.description || 'No description provided.';
        document.getElementById('work-details-meta').innerHTML = `<span class="badge bg-label-primary">${escapeHtml(task.project?.name || 'Project')}</span><span class="badge bg-label-info">${escapeHtml(task.sprint?.name || 'Backlog')}</span><span class="badge bg-label-${escapeHtml(task.workflow_column?.color || 'secondary')}">${escapeHtml(task.workflow_column?.name || task.status.replaceAll('_',' '))}</span>`;
        const tracking = task.time_tracking || {formatted:'0s',by_user:[]};
        document.getElementById('work-details-time').textContent = `${tracking.formatted} logged`;
        document.getElementById('work-details-time-users').innerHTML = tracking.by_user.length ? tracking.by_user.map(person => `<div class="d-flex justify-content-between py-1"><span>${escapeHtml(person.name)}</span><strong>${escapeHtml(person.formatted)}</strong></div>`).join('') : '<small class="text-muted">No time logged yet.</small>';
        const directlyAssigned = (task.users || []).some(user => Number(user.id) === Number(currentUserId));
        const estimateMinutes = Number(task.original_estimate_minutes || 0);
        const percent = estimateMinutes ? Math.round((Number(tracking.total_seconds || 0) / (estimateMinutes * 60)) * 100) : 0;
        const color = percent > 100 ? 'danger' : (percent >= 80 ? 'warning' : 'success');
        const canDeveloperSet = @json(auth()->user()->hasRole('developer')) && directlyAssigned && !task.developer_estimate_set_at;
        const history = (task.estimate_histories || []).slice(0, 3).map(item => `<small class="d-block text-muted mt-1">${escapeHtml(item.user?.name || 'System')} set ${Math.floor(item.new_minutes/60)}h ${item.new_minutes%60}m</small>`).join('');
        document.getElementById('work-estimate-panel').innerHTML = `<div class="d-flex justify-content-between"><span>Original estimate</span><strong class="text-${color}">${estimateMinutes ? `${Math.floor(estimateMinutes/60)}h ${estimateMinutes%60}m` : 'Not set'}${estimateMinutes ? ` (${percent}%)` : ''}</strong></div>${estimateMinutes ? `<div class="progress mt-2" style="height:7px"><div class="progress-bar bg-${color}" style="width:${Math.min(percent,100)}%"></div></div>` : ''}${history}${canDeveloperSet ? `<div class="input-group mt-3"><input id="developer-estimate-hours" type="number" min="0.25" max="8760" step="0.25" class="form-control" value="${estimateMinutes ? estimateMinutes/60 : ''}" placeholder="Estimate in hours"><button id="save-developer-estimate" class="btn btn-outline-primary">Save my one revision</button></div><small class="text-muted">You can revise the estimate once.</small>` : ''}`;
        const ownRunningTimer = (task.timer_logs || []).find(log => Number(log.user_id) === Number(currentUserId) && !log.end_time && !log.subtask_id);
        document.querySelectorAll('.issue-timer-action').forEach(button => button.classList.add('d-none'));
        if (directlyAssigned && task.status !== 'completed') {
            document.querySelector(`.issue-timer-action[data-action="${ownRunningTimer ? 'stop' : 'start'}"]`).classList.remove('d-none');
        }
        const subtasks = task.subtasks || [];
        document.getElementById('work-details-subtasks').innerHTML = subtasks.length ? subtasks.map(item => {
            const assigned = (item.users || []).some(user => Number(user.id) === Number(currentUserId));
            const running = (item.timer_logs || []).some(log => Number(log.user_id) === Number(currentUserId) && !log.end_time);
            const controls = assigned && item.status !== 'completed' ? `<button class="btn btn-sm btn-outline-${running ? 'success' : 'primary'} subtask-timer" data-id="${item.id}" data-action="${running ? 'stop' : 'start'}" title="${running ? 'Stop timer' : 'Start timer'}"><i class="fa fa-${running ? 'stop' : 'play'}"></i></button>` : '';
            return `<div class="list-group-item d-flex justify-content-between align-items-center"><div><i class="fa fa-${item.status === 'completed' ? 'circle-check text-success' : 'circle text-muted'} me-2"></i>${escapeHtml(item.title)} <span class="badge bg-label-secondary">${escapeHtml(item.status.replaceAll('_',' '))}</span></div>${controls}</div>`;
        }).join('') : '<div class="list-group-item text-muted">No subtasks.</div>';
        document.getElementById('work-details-attachments').innerHTML = (task.attachments || []).length ? task.attachments.map(file => `<a class="list-group-item list-group-item-action" href="${file.url}" target="_blank" rel="noopener"><i class="fa fa-paperclip me-2"></i>${escapeHtml(file.original_name)}</a>`).join('') : '<div class="list-group-item text-muted">No attachments.</div>';
        const comments = task.comments || [];
        document.getElementById('work-comment-count').textContent = comments.length;
        const commentsList = document.getElementById('work-details-comments');
        commentsList.innerHTML = comments.length ? comments.slice().reverse().map(comment => {
            const own = Number(comment.user_id) === Number(currentUserId);
            return `<div class="work-comment ${own ? 'own-comment' : ''}"><img class="work-comment-avatar" src="${comment.user?.profile_img || @json(asset('assets/img/user-picture.png'))}" alt=""><div class="work-comment-content"><div class="work-comment-meta"><span class="work-comment-name" title="${escapeHtml(comment.user?.name || 'Deleted user')}">${escapeHtml(comment.user?.name || 'Deleted user')}</span><span>${new Date(comment.created_at).toLocaleString([], {month:'short',day:'numeric',hour:'numeric',minute:'2-digit'})}</span></div><div class="work-comment-bubble">${escapeHtml(comment.body)}</div></div></div>`;
        }).join('') : '<div class="work-empty"><i class="fa fa-comments d-block mb-2 fs-4"></i>No comments yet.</div>';
        requestAnimationFrame(() => { commentsList.scrollTop = commentsList.scrollHeight; });
        document.getElementById('work-open-project').href = `/projects/${task.project_id}?sprint=${task.sprint_id || 'backlog'}&workflow=${task.workflow_id || ''}&issue=${task.id}`;
    };
    const openIssue = async id => { const payload = await api(showUrl.replace(':id', id)); renderIssue(payload.task); bootstrap.Modal.getOrCreateInstance(document.getElementById('myWorkIssueModal')).show(); };
    const requestedIssueId = new URLSearchParams(window.location.search).get('issue');
    if (requestedIssueId) {
        openIssue(requestedIssueId)
            .then(() => window.history.replaceState({}, '', @json(route('my-work.index'))))
            .catch(error => window.toastr ? toastr.error(error.message) : alert(error.message));
    }
    document.querySelectorAll('.work-card').forEach(card => {
        card.addEventListener('click', event => { if (!event.target.closest('.work-assignee-toggle') && !card.classList.contains('dragging')) openIssue(card.dataset.id).catch(error => window.toastr ? toastr.error(error.message) : alert(error.message)); });
        if (canChangeStatus) {
            card.addEventListener('dragstart', () => card.classList.add('dragging'));
            card.addEventListener('dragend', () => card.classList.remove('dragging'));
        }
    });
    document.addEventListener('click', async event => {
        const toggle = event.target.closest('.work-assignee-toggle');
        if (toggle) {
            assignmentCard = toggle.closest('.work-card');
            const current = new Set((assignmentCard.dataset.assigneeIds || '').split(',').filter(Boolean));
            document.getElementById('work-assignee-task-name').textContent = assignmentCard.dataset.title;
            document.getElementById('work-assignee-search').value = '';
            document.getElementById('work-assignee-invite-actions').classList.add('d-none');
            document.getElementById('work-assignee-invite-actions').classList.remove('d-flex');
            document.getElementById('work-assignee-existing').classList.add('d-none');
            document.getElementById('work-assignee-existing').classList.remove('d-flex');
            document.querySelectorAll('.work-assignee-option:not(#work-assignee-existing)').forEach(option => { option.classList.remove('d-none'); const selected = option.dataset.userId ? current.has(option.dataset.userId) : current.size === 0; option.querySelector('.work-assignee-check').classList.toggle('d-none', !selected); });
            document.getElementById('work-assignee-empty').classList.add('d-none');
            bootstrap.Modal.getOrCreateInstance(document.getElementById('workAssigneeModal')).show();
            setTimeout(() => document.getElementById('work-assignee-search').focus(), 200);
            return;
        }
        const option = event.target.closest('.work-assignee-option');
        const inviteOption = event.target.closest('#work-assignee-invite, #work-assignee-copy');
        if (inviteOption && assignmentCard) {
            const email = document.getElementById('work-assignee-search').value.trim().toLowerCase();
            inviteOption.disabled = true;
            try {
                const manual = inviteOption.id === 'work-assignee-copy';
                const result = await api(taskInviteUrl.replace(':id', assignmentCard.dataset.id), 'POST', {email, delivery_method:manual ? 'manual' : 'email'});
                bootstrap.Modal.getOrCreateInstance(document.getElementById('workAssigneeModal')).hide();
                if (manual) { await navigator.clipboard.writeText(result.invitation_url); toastr.success('Invitation link created and copied.'); }
                else await window.showInvitationDeliveryResult(result);
            } catch (error) { if (window.toastr) toastr.error(error.message); else alert(error.message); }
            finally { inviteOption.disabled = false; }
            return;
        }
        if (!option || !assignmentCard) return;
        const assigneeId = option.dataset.userId || null;
        const currentIds = new Set((assignmentCard.dataset.assigneeIds || '').split(',').filter(Boolean));
        const action = !assigneeId ? 'clear' : (currentIds.has(String(assigneeId)) ? 'remove' : 'add');
        const button = assignmentCard.querySelector('.work-assignee-toggle');
        option.disabled = true;
        try {
            const result = await api(updateUrl.replace(':id', assignmentCard.dataset.id), 'PATCH', {assignee_id:assigneeId, assignee_action:action});
            const users = result.task?.users || [];
            assignmentCard.dataset.assigneeIds = users.map(user => user.id).join(','); assignmentCard.dataset.assigneeId = users[0]?.id || '';
            button.title = users.length ? 'Manage assignees' : 'Assign employee';
            button.innerHTML = users.length ? `<span class="work-assignee-avatars d-flex align-items-center">${users.slice(0,3).map(user => `<img class="work-avatar work-assignee-avatar" src="${user.profile_img || @json(asset('assets/img/user-picture.png'))}" title="${escapeHtml(user.name)}" alt="${escapeHtml(user.name)}">`).join('')}${users.length>3 ? `<small class="ms-1">+${users.length-3}</small>` : ''}</span>` : '<span class="work-avatar m-0 d-inline-flex align-items-center justify-content-center work-assignee-avatar"><i class="fa fa-user-plus text-primary"></i></span>';
            document.querySelectorAll('.work-assignee-option').forEach(item => { const selected = item.dataset.userId ? users.some(user => String(user.id) === item.dataset.userId) : users.length === 0; item.querySelector('.work-assignee-check').classList.toggle('d-none', !selected); });
            if (window.toastr) toastr.success(action === 'add' ? `${option.dataset.userName} assigned.` : (assigneeId ? `${option.dataset.userName} unassigned.` : 'All employees unassigned.'));
        } catch (error) { if (window.toastr) toastr.error(error.message); else alert(error.message); }
        finally { option.disabled = false; }
    });
    let workInviteLookup = 0;
    document.getElementById('work-assignee-search')?.addEventListener('input', async event => {
        const query = event.currentTarget.value.trim().toLowerCase(); let visible = 0;
        const lookup = ++workInviteLookup;
        document.querySelectorAll('.work-assignee-option').forEach(option => { const match = !query || option.dataset.search.includes(query); option.classList.toggle('d-none', !match); if (match) visible++; });
        const exactEmployee = [...document.querySelectorAll('.work-assignee-option[data-user-id]')].some(option => option.dataset.userId && option.dataset.search.split(' ').includes(query));
        const validEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(query);
        const invite = document.getElementById('work-assignee-invite-actions');
        const existing = document.getElementById('work-assignee-existing');
        invite.classList.add('d-none'); invite.classList.remove('d-flex'); existing.classList.add('d-none'); existing.classList.remove('d-flex');
        document.getElementById('work-assignee-invite-email').textContent = query;
        if (validEmail && !exactEmployee && assignmentCard) {
            try {
                const result = await api(`${taskInviteCheckUrl.replace(':id', assignmentCard.dataset.id)}?email=${encodeURIComponent(query)}`);
                if (lookup !== workInviteLookup) return;
                if (result.exists) {
                    document.getElementById('work-assignee-existing-name').textContent = result.employee.name;
                    document.getElementById('work-assignee-existing-email').textContent = result.employee.email;
                    document.getElementById('work-assignee-existing-avatar').src = result.employee.profile_img || @json(asset('assets/img/user-picture.png'));
                    existing.dataset.userId = result.employee.id; existing.dataset.userName = result.employee.name; existing.dataset.avatar = result.employee.profile_img || @json(asset('assets/img/user-picture.png'));
                    existing.disabled = !result.assignable;
                    document.getElementById('work-assignee-existing-help').textContent = result.assignable ? 'Existing employee - click to assign.' : 'This employee is not currently eligible for assignment.';
                    document.getElementById('work-assignee-existing-help').className = `d-block ${result.assignable ? 'text-success' : 'text-warning'}`;
                    existing.classList.remove('d-none'); existing.classList.add('d-flex');
                } else { invite.classList.remove('d-none'); invite.classList.add('d-flex'); }
            } catch (_) {}
        }
        document.getElementById('work-assignee-empty').classList.toggle('d-none', visible > 0 || validEmail);
    });
    document.addEventListener('click', async event => {
        const button = event.target.closest('.issue-timer-action, .subtask-timer');
        const estimateButton = event.target.closest('#save-developer-estimate');
        if (estimateButton && activeTask) {
            const hours = Number(document.getElementById('developer-estimate-hours').value);
            if (!hours || hours <= 0) return window.toastr ? toastr.error('Enter a valid estimate.') : alert('Enter a valid estimate.');
            try { await api(updateUrl.replace(':id', activeTask.id), 'PATCH', {original_estimate_minutes:Math.round(hours*60)}); await openIssue(activeTask.id); if(window.toastr) toastr.success('Estimate saved.'); }
            catch(error){ if(window.toastr) toastr.error(error.message); else alert(error.message); }
            return;
        }
        if (!button || !activeTask) return;
        button.disabled = true;
        try {
            const timerAction = button.dataset.action;
            const subtaskId = button.classList.contains('subtask-timer') ? button.dataset.id : null;
            await api(timerUrls[timerAction], 'POST', {task_id:activeTask.id, subtask_id:subtaskId});
            const subtask = subtaskId ? (activeTask.subtasks || []).find(item => Number(item.id) === Number(subtaskId)) : null;
            window.dispatchEvent(new CustomEvent('task-timer-updated', {detail: {
                is_running: timerAction === 'start',
                task_id: activeTask.id,
                subtask_id: subtaskId,
                project_id: activeTask.project_id,
                project_name: activeTask.project?.name || '',
                task_title: subtask?.title || activeTask.title
            }}));
            await openIssue(activeTask.id);
            if (window.toastr) toastr.success(timerAction === 'stop' ? 'Timer stopped and tracked time saved.' : 'Timer started.');
        } catch (error) { if (window.toastr) toastr.error(error.message); else alert(error.message); }
        finally { button.disabled = false; }
    });
    if (!canChangeStatus) return;
    document.querySelectorAll('.work-column').forEach(column => {
        column.addEventListener('dragover', event => { event.preventDefault(); column.classList.add('drag-over'); });
        column.addEventListener('dragleave', () => column.classList.remove('drag-over'));
        column.addEventListener('drop', async event => {
            event.preventDefault(); column.classList.remove('drag-over');
            const card = document.querySelector('.work-card.dragging');
            if (!card || card.dataset.columnId === column.dataset.columnId) return;
            try {
                const response = await fetch(updateUrl.replace(':id', card.dataset.id), {method:'PATCH',headers:{'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':csrf},body:JSON.stringify({workflow_id:selectedWorkflowId,workflow_column_id:column.dataset.columnId})});
                const payload = await response.json().catch(() => ({}));
                if (!response.ok) throw new Error(payload.message || 'Status update failed.');
                const oldColumn = card.closest('.work-column');
                column.querySelector('.work-list').prepend(card); card.dataset.columnId = column.dataset.columnId;
                oldColumn.querySelector('.column-count').textContent = oldColumn.querySelectorAll('.work-card').length;
                column.querySelector('.column-count').textContent = column.querySelectorAll('.work-card').length;
                if (window.toastr) toastr.success('Issue status updated.');
            } catch (error) { if (window.toastr) toastr.error(error.message); else alert(error.message); }
        });
    });
});
</script>
@endpush
