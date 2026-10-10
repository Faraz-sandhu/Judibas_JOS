<aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
    <div class="app-brand demo">
        <a href="{{ route('dashboard') }}" class="app-brand-link">
            <span class="app-brand-logo demo">
                <img src="{{ $branding->assetUrl('sidebar_logo', 'assets/img/sm-logo.png') }}" alt="Sidebar logo" height="34" />
            </span>
            <span class="app-brand-text demo menu-text">
                <img src="{{ $branding->assetUrl('sidebar_logo_text', 'assets/img/logo-text.png') }}" alt="Brand name" width="150"
                    data-app-light-img="{{ $branding->assetUrl('sidebar_logo_text', 'assets/img/logo-text.png') }}"
                    data-app-dark-img="{{ $branding->variantUrl('sidebar_logo_text_dark', 'sidebar_logo_text', 'assets/img/logo-text.png') }}" />
            </span>
        </a>
        <a href="#" class="layout-menu-toggle menu-link text-large ms-auto">
            <i class="ti menu-toggle-icon d-none d-xl-block ti-sm align-middle"></i>
            <i class="ti ti-x d-block d-xl-none ti-sm align-middle"></i>
        </a>
    </div>

    <div class="menu-inner-shadow"></div>
    <ul class="menu-inner py-1">
        @can('dashboard-view')
            <li class="menu-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                <a href="{{ route('dashboard') }}" class="menu-link">
                    <i class="menu-icon tf-icons ti ti-layout-dashboard"></i>
                    <div>Dashboard</div>
                </a>
            </li>
        @endcan

        <li class="menu-header text-uppercase small fw-medium">Workspace</li>

        @can('my-work-view')
            <li class="menu-item {{ request()->routeIs('my-work.*') ? 'active' : '' }}">
                <a href="{{ route('my-work.index') }}" class="menu-link">
                    <i class="menu-icon tf-icons ti ti-layout-kanban"></i>
                    <div>My Work</div>
                </a>
            </li>
        @endcan

        @if(auth()->user()->hasRole('admin') || auth()->user()->hasRole('project_manager') || auth()->user()->hasRole('team_leader'))
            @can('project-view')
                <li class="menu-item {{ request()->routeIs('projects.*') ? 'active' : '' }}">
                    <a href="{{ route('projects.index') }}" class="menu-link">
                        <i class="menu-icon tf-icons ti ti-folders"></i>
                        <div>Projects</div>
                    </a>
                </li>
            @endcan
        @endif

        @can('project-view')
            @php
                $projectDepartments = $projectDepartments ?? collect();
                $unassignedDepartmentProjects = $unassignedDepartmentProjects ?? collect();
                $isTeamSpaceProject = request()->routeIs('projects.show') && request()->query('source') === 'team-space';
                $currentProjectId = $isTeamSpaceProject ? (int) request()->route('project') : null;
                $currentDepartmentId = request()->routeIs('team-space.departments.board') ? (int) request()->route('department')->id : null;
                $teamSpaceActive = $isTeamSpaceProject || request()->routeIs('team-space.departments.board');
            @endphp
            <li class="menu-item {{ $teamSpaceActive ? 'active open' : '' }}">
                <a href="#" class="menu-link menu-toggle">
                    <i class="menu-icon tf-icons ti ti-users"></i>
                    <div>Team Space</div>
                </a>
                <ul class="menu-sub">
                    @foreach($projectDepartments as $department)
                        @php($departmentOpen = $currentDepartmentId === $department->id || $department->projects->contains('id', $currentProjectId))
                        <li class="menu-item position-relative {{ $departmentOpen ? 'active open' : '' }}">
                            <a href="{{ $department->can_open_board ? route('team-space.departments.board', $department) : '#' }}"
                                class="menu-link team-space-department {{ $department->can_open_board ? '' : 'team-space-department-readonly' }}"
                                title="{{ $department->can_open_board ? $department->dept_name : 'Assigned work from '.$department->dept_name }}">
                                <i class="menu-icon tf-icons ti ti-building-community"></i>
                                <div class="team-space-department-name" title="{{ $department->dept_name }}">{{ $department->dept_name }}</div>
                            </a>
                            <button type="button" class="team-space-toggle" title="Show projects" aria-label="Show projects"><i class="ti ti-chevron-right"></i></button>
                            @can('project-add')
                                <button type="button" class="team-space-add sidebar-project-add" data-department-id="{{ $department->id }}"
                                    data-department-name="{{ $department->dept_name }}" title="Create project in {{ $department->dept_name }}"
                                    aria-label="Create project in {{ $department->dept_name }}"><i class="ti ti-plus"></i></button>
                            @endcan
                            <ul class="menu-sub">
                                @forelse($department->projects as $project)
                                    <li class="menu-item {{ $currentProjectId === $project->id ? 'active' : '' }}">
                                        <a href="{{ route('projects.show', ['project' => $project->id, 'source' => 'team-space']) }}" class="menu-link" title="{{ $project->name }}{{ $project->approval !== 'approved' ? ' ('.ucfirst($project->approval).')' : '' }}">
                                            <i class="menu-icon tf-icons ti ti-layout-kanban"></i><div class="text-truncate">{{ $project->name }}</div>
                                            @if($project->approval !== 'approved')<span class="badge bg-label-warning ms-auto" style="font-size:.6rem">{{ ucfirst($project->approval) }}</span>@endif
                                        </a>
                                    </li>
                                @empty
                                    <li class="team-space-empty">No projects yet</li>
                                @endforelse
                            </ul>
                        </li>
                    @endforeach
                    @if($unassignedDepartmentProjects->isNotEmpty())
                        <li class="menu-item {{ $unassignedDepartmentProjects->contains('id', $currentProjectId) ? 'active open' : '' }}">
                            <a href="#" class="menu-link menu-toggle" title="Projects without a linked department">
                                <i class="menu-icon tf-icons ti ti-folder-question"></i><div>Other projects</div>
                            </a>
                            <ul class="menu-sub">
                                @foreach($unassignedDepartmentProjects as $project)
                                    <li class="menu-item {{ $currentProjectId === $project->id ? 'active' : '' }}">
                                        <a href="{{ route('projects.show', ['project' => $project->id, 'source' => 'team-space']) }}" class="menu-link"><i class="menu-icon tf-icons ti ti-layout-kanban"></i><div class="text-truncate">{{ $project->name }}</div></a>
                                    </li>
                                @endforeach
                            </ul>
                        </li>
                    @endif
                    @if($projectDepartments->isEmpty() && $unassignedDepartmentProjects->isEmpty())<li class="team-space-empty">No accessible projects yet</li>@endif
                </ul>
            </li>
            <style>
                #layout-menu .team-space-department{height:2.75rem;padding-right:4.8rem}
                #layout-menu .team-space-department-name{min-width:0;line-height:1.2;white-space:nowrap}
                #layout-menu .team-space-toggle{position:absolute;right:2.35rem;top:.5rem;z-index:4;width:1.75rem;height:1.75rem;display:grid;place-items:center;border:0;border-radius:.45rem;background:transparent;color:inherit;padding:0}
                #layout-menu .menu-item.open>.team-space-toggle i{transform:rotate(90deg)}
                #layout-menu .team-space-toggle i{transition:transform .2s ease}
                #layout-menu .team-space-add{position:absolute;right:.45rem;top:.5rem;z-index:4;width:1.75rem;height:1.75rem;display:grid;place-items:center;border:0;border-radius:.45rem;background:transparent;color:var(--bs-primary);padding:0}
                #layout-menu .team-space-add:hover{background:rgba(var(--bs-primary-rgb),.14)}
                #layout-menu .team-space-empty{padding:.55rem 1rem .55rem 3.25rem;font-size:.75rem;opacity:.65}
                #layout-menu .menu-sub .menu-sub{max-height:15rem;overflow-y:auto}
            </style>
            <script>document.addEventListener('click',event=>{const button=event.target.closest('.team-space-toggle,.team-space-department-readonly');if(!button)return;event.preventDefault();event.stopPropagation();button.closest('.menu-item')?.classList.toggle('open');});</script>
        @endcan

        @can('summary-view')
            <li class="menu-item {{ request()->routeIs('summary.*') ? 'active' : '' }}">
                <a href="{{ route('summary.index') }}" class="menu-link">
                    <i class="menu-icon tf-icons ti ti-history"></i>
                    <div>Work Logs</div>
                </a>
            </li>
        @endcan

        @if(auth()->user()->hasAnyPermission(['task-assign', 'my-work-manage-team']) || auth()->user()->hasRole('admin') || auth()->user()->hasRole('project_manager') || auth()->user()->hasRole('team_leader'))
            @php($pendingInvitationCount = auth()->user()->sentInvitations()->pending()->count())
            <li class="menu-item {{ request()->routeIs('invitations.*') ? 'active' : '' }}">
                <a href="{{ route('invitations.index') }}" class="menu-link">
                    <i class="menu-icon tf-icons ti ti-user-plus"></i>
                    <div>Invitations</div>
                    @if($pendingInvitationCount)<span class="badge bg-primary rounded-pill ms-auto">{{ $pendingInvitationCount }}</span>@endif
                </a>
            </li>
        @endif
        {{-- Messages are currently disabled. The Chatify feature remains available for future use. --}}

        @if(Gate::any(['employee-view', 'department-view', 'role-view', 'report-view', 'time-tracking-dashboard', 'pulse-view']))
            <li class="menu-header text-uppercase small fw-medium">Administration</li>
        @endif

        @can('employee-view')
            <li class="menu-item {{ request()->routeIs('employee.*') ? 'active' : '' }}">
                <a href="{{ route('employee.index') }}" class="menu-link">
                    <i class="menu-icon tf-icons ti ti-users"></i>
                    <div>Employees</div>
                </a>
            </li>
        @endcan

        @can('department-view')
            <li class="menu-item {{ request()->routeIs('departments.*') ? 'active' : '' }}">
                <a href="{{ route('departments.index') }}" class="menu-link">
                    <i class="menu-icon tf-icons ti ti-building-community"></i>
                    <div>Departments</div>
                </a>
            </li>
        @endcan

        @if(Route::has('workflows.index'))
        @can('department-view')
            <li class="menu-item {{ request()->routeIs('workflows.*') ? 'active' : '' }}">
                <a href="{{ route('workflows.index') }}" class="menu-link"><i class="menu-icon tf-icons ti ti-route"></i><div>Workflows</div></a>
            </li>
        @endcan
        @endif

        @can('role-view')
            <li class="menu-item {{ request()->routeIs('roles.*') ? 'active' : '' }}">
                <a href="{{ route('roles.index') }}" class="menu-link">
                    <i class="menu-icon tf-icons ti ti-shield"></i>
                    <div>Roles &amp; Permissions</div>
                </a>
            </li>
        @endcan

        @can('report-view')
            <li class="menu-item {{ request()->routeIs('reports.*') ? 'active' : '' }}">
                <a href="{{ route('reports.index') }}" class="menu-link">
                    <i class="menu-icon tf-icons ti ti-chart-pie"></i>
                    <div>Reports</div>
                </a>
            </li>
        @endcan

        @can('time-tracking-dashboard')
            <li class="menu-item {{ request()->routeIs('time-tracking-dashboard') ? 'active' : '' }}">
                <a href="{{ route('time-tracking-dashboard') }}" class="menu-link">
                    <i class="menu-icon tf-icons ti ti-clock-2"></i>
                    <div>Team Activity</div>
                </a>
            </li>
        @endcan

        @can('pulse-view')
            <li class="menu-item">
                <a href="{{ url('pulse') }}" target="_blank" rel="noopener" class="menu-link">
                    <i class="menu-icon tf-icons ti ti-activity-heartbeat"></i>
                    <div>System Health</div>
                </a>
            </li>
        @endcan

    </ul>
</aside>

@can('project-add')
<div class="modal fade" id="sidebarProjectCreateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content project-form-modal sidebar-project-modal">
            <div class="modal-header">
                <div><h5 class="modal-title mb-1">Create project</h5><small class="text-muted">Create directly inside <strong id="sidebar-project-department-label"></strong>.</small></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="sidebar-project-create-form" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="project-form-section">
                        <div class="project-form-section-title">Project information</div>
                        <div class="row g-3">
                            <div class="col-lg-6"><label class="form-label">Name <span class="text-danger">*</span></label><input name="name" class="form-control" maxlength="255" required placeholder="Enter project name"></div>
                            <div class="col-lg-6"><label class="form-label">Project logo</label><div class="form-text mb-1">JPG, JPEG, PNG, WebP or SVG · Maximum 2 MB.</div><input type="file" name="image" class="form-control" accept=".jpg,.jpeg,.png,.webp,.svg"></div>
                            <div class="col-lg-6"><label class="form-label">Company / Client</label><input name="company_name" class="form-control" list="sidebar-company-options" placeholder="Select or enter a company"><datalist id="sidebar-company-options">@foreach($sidebarCompanies as $company)<option value="{{ $company->name }}"></option>@endforeach</datalist></div>
                            <div class="col-lg-6 d-none" id="sidebar-project-department-field"><label class="form-label">Departments</label><select name="department_ids[]" id="sidebar-project-departments" class="form-select" multiple>@foreach($projectDepartments as $department)<option value="{{ $department->id }}">{{ $department->dept_name }}</option>@endforeach</select><div class="form-text">Search and select participating departments.</div></div>
                            <div class="col-lg-6"><label class="form-label">Attachment</label><div class="form-text mb-1">PDF, Word, PowerPoint, Excel, CSV, TXT or image · Maximum 10 MB.</div><input type="file" name="attachment" class="form-control" accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.csv,.jpg,.jpeg,.png,.txt"></div>
                            <div class="col-lg-6"><label class="form-label">URL</label><input type="url" name="url" class="form-control" placeholder="https://example.com"></div>
                        </div>
                    </div>
                    <div class="project-form-section"><div class="project-form-section-title">Project brief</div><label class="form-label">Description</label><textarea name="description" class="form-control" rows="3" placeholder="Describe the scope, objectives, and delivery notes"></textarea></div>
                    <div class="project-form-section mb-0">
                        <div class="project-form-section-title">Schedule and workflow</div>
                        <div class="row g-3">
                            <div class="col-md-6"><label class="form-label">Start date <span class="text-danger">*</span></label><input type="date" name="start_date" id="sidebar-project-start-date" class="form-control" required></div>
                            <div class="col-md-6"><label class="form-label">End date</label><input type="date" name="end_date" id="sidebar-project-end-date" class="form-control"></div>
                            <div class="col-12"><label class="form-label">Enabled workflows <span class="text-danger">*</span></label><select name="workflow_ids[]" id="sidebar-project-workflows" class="form-select" multiple required>@foreach($sidebarWorkflows as $workflow)<option value="{{ $workflow->id }}" @selected($workflow->is_default)>{{ $workflow->name }}</option>@endforeach</select></div>
                        </div>
                    </div>
                    <div class="alert alert-danger mt-3 mb-0 d-none" id="sidebar-project-error"></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary" id="sidebar-project-submit"><i class="ti ti-plus me-1"></i>Create project</button></div>
            </form>
        </div>
    </div>
</div>
<style>
    #sidebarProjectCreateModal .modal-dialog{max-width:900px;margin-top:1rem;margin-bottom:1rem}
    #sidebarProjectCreateModal .modal-content{height:auto;max-height:calc(100vh - 2rem);overflow:hidden}
    #sidebarProjectCreateModal #sidebar-project-create-form{display:flex;flex:1 1 auto;flex-direction:column;min-height:0;overflow:hidden}
    #sidebarProjectCreateModal .modal-body{flex:1 1 auto;min-height:0;overflow-y:auto;padding:1rem}
    #sidebarProjectCreateModal .modal-footer{flex:0 0 auto;background:var(--bs-body-bg);border-top:1px solid var(--bs-border-color);padding:.8rem 1rem}
    #sidebarProjectCreateModal .project-form-section{padding:1rem;margin-bottom:.8rem}
    #sidebarProjectCreateModal .project-form-section-title{margin-bottom:.75rem}
    @media(max-width:991.98px){#sidebarProjectCreateModal .modal-dialog{max-width:calc(100% - 1.5rem)}}
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const modalElement = document.getElementById('sidebarProjectCreateModal');
    const form = document.getElementById('sidebar-project-create-form');
    if (!modalElement || !form) return;
    const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
    $('#sidebar-project-departments').select2({dropdownParent: $('#sidebarProjectCreateModal'), width:'100%', placeholder:'Search and select departments', closeOnSelect:false});
    $('#sidebar-project-workflows').select2({dropdownParent: $('#sidebarProjectCreateModal'), width:'100%', placeholder:'Search and select workflows', closeOnSelect:false});
    document.querySelectorAll('.sidebar-project-add').forEach(button => button.addEventListener('click', function (event) {
        event.preventDefault(); event.stopPropagation();
        form.reset();
        $('#sidebar-project-departments').val([this.dataset.departmentId]).trigger('change');
        const defaultWorkflows = @json($sidebarWorkflows->where('is_default', true)->pluck('id')->map(fn($id) => (string) $id)->values());
        $('#sidebar-project-workflows').val(defaultWorkflows).trigger('change');
        document.getElementById('sidebar-project-department-label').textContent = this.dataset.departmentName;
        document.getElementById('sidebar-project-error').classList.add('d-none'); modal.show();
    }));
    document.getElementById('sidebar-project-start-date').addEventListener('change', function () {
        document.getElementById('sidebar-project-end-date').min = this.value;
    });
    form.addEventListener('submit', async function (event) {
        event.preventDefault();
        const submit = document.getElementById('sidebar-project-submit');
        const error = document.getElementById('sidebar-project-error');
        const start = document.getElementById('sidebar-project-start-date').value;
        const end = document.getElementById('sidebar-project-end-date').value;
        if (end && end < start) { error.textContent = 'End date must be the same as or later than the start date.'; error.classList.remove('d-none'); return; }
        submit.disabled = true; submit.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Creating...'; error.classList.add('d-none');
        try {
            const response = await fetch(@json(route('projects.store')), {method:'POST', headers:{'X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content,'Accept':'application/json'}, body:new FormData(form)});
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || Object.values(data.errors || {}).flat()[0] || 'Unable to create project.');
            window.location.assign(data.redirect_url || @json(route('projects.index')));
        } catch (exception) { error.textContent = exception.message; error.classList.remove('d-none'); submit.disabled = false; submit.innerHTML = '<i class="ti ti-plus me-1"></i>Create project'; }
    });
});
</script>
@endcan
