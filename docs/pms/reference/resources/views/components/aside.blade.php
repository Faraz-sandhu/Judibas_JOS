<aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
    <div class="app-brand demo">
        <a href="{{ route('dashboard') }}" class="app-brand-link">
            <span class="app-brand-logo demo">
                <img src="{{ asset('assets/img/sm-logo.png') }}" alt="Test Logo" height="34" />
            </span>
            <span class="app-brand-text demo menu-text">
                <img src="{{ asset('assets/img/logo-text.png') }}" alt="Test Logo" width="150" />
            </span>
        </a>
        <a href="#" class="layout-menu-toggle menu-link text-large ms-auto">
            <i class="ti menu-toggle-icon d-none d-xl-block ti-sm align-middle"></i>
            <i class="ti ti-x d-block d-xl-none ti-sm align-middle"></i>
        </a>
    </div>

    <div class="menu-inner-shadow"></div>
    <ul class="menu-inner py-1">
        <li class="menu-item">
            @can('dashboard-view')
                <a href="{{ route('dashboard') }}" class="menu-link menu-toggle">
                    <i class="menu-icon tf-icons ti ti-smart-home"></i>
                    <div>Dashboard</div>
                </a>
            @endcan
            <ul class="menu-sub">
                @can('simple-dashboard-view')
                    <li class="menu-item">
                        <a href="#" class="menu-link">
                            <div>Simple Dashboard</div>
                        </a>
                    </li>
                @endcan
                @can('team-reporting-dashboard')
                    <li class="menu-item">
                        <a href="#" class="menu-link">
                            <div>Team Reporting</div>
                        </a>
                    </li>
                @endcan
                @can('time-tracking-dashboard')
                    <li class="menu-item">
                        <a href="#" class="menu-link">
                            <div>Time Tracking</div>
                        </a>
                    </li>
                @endcan
            </ul>
        </li>
        @can('employee-view')
            <li class="menu-item">
                <a href="#" class="menu-link menu-toggle">
                    <i class="menu-icon tf-icons ti ti-smart-home"></i>
                    <div>Employees</div>
                </a>
                <ul class="menu-sub">
                    <li class="menu-item">
                        <a href="{{ route('employee.index') }}" class="menu-link">
                            <div>List</div>
                        </a>
                    </li>
                </ul>
            </li>
        @endcan
        @can('department-view')
            <li class="menu-item">
                <a href="#" class="menu-link menu-toggle">
                    <i class="menu-icon tf-icons ti ti-smart-home"></i>
                    <div>Departments</div>
                </a>
                <ul class="menu-sub">
                    <li class="menu-item">
                        <a href="{{ route('departments.index') }}" class="menu-link">
                            <div>List</div>
                        </a>
                    </li>
                </ul>
            </li>
        @endcan
        @can('role-view')
            <li class="menu-item">
                <a href="#" class="menu-link menu-toggle">
                    <i class="menu-icon tf-icons ti ti-smart-home"></i>
                    <div>Roles</div>
                </a>
                <ul class="menu-sub">
                    <li class="menu-item">
                        <a href="{{ route('roles.index') }}" class="menu-link">
                            <div>List</div>
                        </a>
                    </li>
                </ul>
            </li>
        @endcan
        @can('permission-view')
            <li class="menu-item">
                <a href="#" class="menu-link menu-toggle">
                    <i class="menu-icon tf-icons ti ti-smart-home"></i>
                    <div>Permissions</div>
                </a>
                <ul class="menu-sub">
                    <li class="menu-item">
                        <a href="{{ route('permissions.index') }}" class="menu-link">
                            <div>List</div>
                        </a>
                    </li>
                </ul>
            </li>
        @endcan
        <li class="menu-item">
            <a href="#" class="menu-link menu-toggle">
                <i class="menu-icon tf-icons ti ti-smart-home"></i>
                <div>Roles & Permissions</div>
            </a>
            <ul class="menu-sub">
                @can('permission-assign-role')
                    <li class="menu-item">
                        <a href="{{ route('role-permission.permission-assign') }}" class="menu-link">
                            <div>Permission Assign</div>
                        </a>
                    </li>
                @endcan
                @can('role-assign-user')
                    <li class="menu-item">
                        <a href="{{ route('role-permission.role-assign') }}" class="menu-link">
                            <div>Role Assign</div>
                        </a>
                    </li>
                @endcan
            </ul>
        </li>
        <li class="menu-header text-uppercase small fw-medium">Spaces</li>
        <li class="menu-item">
            <a href="#" class="menu-link menu-toggle">
                <i class="menu-icon tf-icons ti ti-smart-home"></i>
                <div>Team Space</div>
            </a>
            <ul class="menu-sub">
                <li class="menu-header text-uppercase small fw-medium">Projects</li>
                @if(isset($assignedProjects) && $assignedProjects->count() > 0)
                @foreach($assignedProjects as $project)
                    <li class="menu-item">
                        <a href="{{ route('projects.show', $project->id) }}" class="menu-link">
                            <i class="menu-icon tf-icons ti ti-clipboard"></i>
                            <div>{{ $project->name }}</div>
                        </a>
                    </li>
                @endforeach
            @else
                <li aria-disabled="true" class="menu-item">
                    <a  href="#" class="menu-link">
                        <div  class="text-danger">No tasks assigned</div>
                    </a>
                </li>
            @endif
            </ul>
        </li>
    </ul>
</aside>
