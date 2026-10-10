<nav class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme"
    id="layout-navbar">
    <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0 d-xl-none">
        <a class="nav-item nav-link px-0 me-xl-4" href="javascript:void(0)">
            <i class="ti ti-menu-2 ti-sm"></i>
        </a>
    </div>
    <div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">
        <div id="current-task-header"
            data-stop-url="{{ route('timer_logs.stop') }}">
            @if ($currentyRunningTask)
                <div class="current-task-summary">
                    <h6 class="mb-0">
                        <a href="{{ url('projects/' . $currentyRunningTask->task->project->id) }}" class="text-dark">
                            Currently Running:
                            <span class="badge bg-label-primary"> Project </span>
                            {{ $currentyRunningTask->task->project->name ?? '' }}
                            <span class="text-muted"><i class="ti ti-arrow-right"></i></span>
                            <span class="badge bg-label-primary"> Task </span>
                            {{ $currentyRunningTask->subtask->title ?? $currentyRunningTask->task->title ?? '' }}
                        </a>
                        <span class="blinking-dot-header" title="currently task is running"></span>
                    </h6>
                    <div class="current-task-actions" aria-label="Running task timer controls">
                        <button type="button" class="btn btn-sm btn-success current-task-timer-action"
                            data-action="stop" data-task-id="{{ $currentyRunningTask->task_id }}"
                            data-subtask-id="{{ $currentyRunningTask->subtask_id }}" title="Stop timer">
                            <i class="ti ti-player-stop"></i><span>Stop timer</span>
                        </button>
                    </div>
                </div>
            @endif
        </div>
        <ul class="navbar-nav flex-row align-items-center ms-auto">
            <li class="nav-item dropdown me-2 me-xl-1">
                <a class="nav-link dropdown-toggle hide-arrow pms-theme-toggle" href="javascript:void(0);"
                    data-bs-toggle="dropdown" aria-expanded="false" aria-label="Change color theme" title="Theme">
                    <i class="ti ti-sun-moon ti-md" id="pms-theme-icon"></i>
                </a>
                <div class="dropdown-menu dropdown-menu-end pms-theme-menu">
                    <h6 class="dropdown-header">Appearance</h6>
                    <button type="button" class="dropdown-item pms-theme-choice" data-theme-choice="light">
                        <i class="ti ti-sun me-2"></i><span>Light</span><i class="ti ti-check ms-auto theme-check"></i>
                    </button>
                    <button type="button" class="dropdown-item pms-theme-choice" data-theme-choice="dark">
                        <i class="ti ti-moon-stars me-2"></i><span>Dark</span><i class="ti ti-check ms-auto theme-check"></i>
                    </button>
                    <button type="button" class="dropdown-item pms-theme-choice" data-theme-choice="system">
                        <i class="ti ti-device-desktop me-2"></i><span>System</span><i class="ti ti-check ms-auto theme-check"></i>
                    </button>
                </div>
            </li>
            <li class="nav-item dropdown-notifications navbar-dropdown dropdown me-3 me-xl-1">
                <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);" data-bs-toggle="dropdown"
                    data-bs-auto-close="outside" aria-expanded="false">
                    <i class="ti ti-bell ti-md"></i>
                    @if ($unreadCount)
                        <span class="badge bg-danger rounded-pill badge-notifications">{{ $unreadCount > 99 ? '99+' : $unreadCount }}</span>
                    @endif
                </a>
                <ul class="dropdown-menu dropdown-menu-end py-0">
                    <li class="dropdown-menu-header border-bottom">
                        <div class="dropdown-header d-flex align-items-center py-3">
                            <h5 class="text-body mb-0 me-auto">Notifications</h5>
                            <a href="javascript:void(0)" class="dropdown-notifications-all text-body"
                                data-bs-toggle="tooltip" data-bs-placement="top" title="Mark all as read"><i
                                    class="ti ti-mail-opened fs-4"></i></a>
                        </div>
                    </li>
                    <li class="border-bottom px-3 py-2">
                        <div class="btn-group btn-group-sm w-100" role="group" aria-label="Filter notifications">
                            <button type="button" class="btn btn-primary notification-filter" data-filter="all"
                                onclick="filterHeaderNotifications('all', this, event)">All</button>
                            <button type="button" class="btn btn-outline-primary notification-filter" data-filter="unread"
                                onclick="filterHeaderNotifications('unread', this, event)">Unread ({{ $unreadCount }})</button>
                            <button type="button" class="btn btn-outline-primary notification-filter" data-filter="read"
                                onclick="filterHeaderNotifications('read', this, event)">Read</button>
                        </div>
                    </li>
                    <li class="dropdown-notifications-list notification-scroll-area">
                        <ul class="list-group list-group-flush">
                            @forelse ($notifications as $notify)
                                @php
                                    $data = $notify->data ?? [];
                                @endphp
                                <li class="list-group-item list-group-item-action dropdown-notifications-item notification-row {{ $notify->read_at ? '' : 'bg-label-primary' }}"
                                    data-state="{{ $notify->read_at ? 'read' : 'unread' }}"
                                    data-url="{{ route('notifications.open', $notify->id) }}" role="link" tabindex="0">
                                    <a href="{{ route('notifications.open', $notify->id) }}" class="d-flex w-100 text-body text-decoration-none notification-open-link">
                                        <div class="flex-shrink-0 me-3">
                                            <span class="avatar-initial rounded-circle bg-label-{{ ($data['type'] ?? '') === 'comment' ? 'info' : 'primary' }} avatar">
                                                <i class="ti {{ ($data['type'] ?? '') === 'comment' ? 'ti-message-circle' : 'ti-clipboard-check' }}"></i>
                                            </span>
                                        </div>
                                        <div class="flex-grow-1 notification-content">
                                            <h6 class="mb-1 notification-subject">{{ $data['subject'] ?? 'Issue notification' }}</h6>
                                            <p class="mb-1 small notification-message">{{ $data['message'] ?? 'You have a new notification.' }}</p>
                                            <small class="text-muted">{{ $notify->created_at->diffForHumans() }}</small>
                                        </div>
                                        @unless($notify->read_at)<span class="badge badge-dot bg-primary ms-2 mt-2"></span>@endunless
                                    </a>
                                </li>
                            @empty
                                <li class="list-group-item text-center text-muted py-4">No notifications yet.</li>
                            @endforelse
                            <li id="notification-filter-empty" class="list-group-item text-center text-muted py-4 d-none">No notifications in this view.</li>
                        </ul>
                    </li>


                </ul>
            </li>
            <li class="nav-item navbar-dropdown dropdown-user dropdown">
                <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);" data-bs-toggle="dropdown">
                    <div class="avatar avatar-online">
                        <img src="{{ auth()->user()->profile_img ?: asset('assets/img/user-picture.png') }}" alt="{{ auth()->user()->name }}"
                            onerror="this.onerror=null;this.src='{{ asset('assets/img/user-picture.png') }}';" class="h-auto rounded-circle" />
                    </div>
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                        <a class="dropdown-item" href="javascript:void(0);">
                            <div class="d-flex">
                                <div class="flex-shrink-0 me-3">
                                    <div class="avatar avatar-online">
                                        <img src="{{ auth()->user()->profile_img ?: asset('assets/img/user-picture.png') }}" alt="{{ auth()->user()->name }}"
                                            onerror="this.onerror=null;this.src='{{ asset('assets/img/user-picture.png') }}';" class="h-auto rounded-circle" />
                                    </div>
                                </div>
                                <div class="flex-grow-1">
                                    <span class="fw-semibold d-block">{{ auth()->user()->name }}</span>
                                    <small
                                        class="text-muted">{{ auth()->user()->roles->first()?->role_name ?? 'No Role' }}</small>
                                </div>
                            </div>
                        </a>
                    </li>
                    <li>
                        <div class="dropdown-divider"></div>
                    </li>
                    <li>
                        <a class="dropdown-item" href="{{ route('profile.index') }}">
                            <i class="ti ti-user-check me-2 ti-sm"></i>
                            <span class="align-middle">My Profile</span>
                        </a>
                    </li>
                    @if(Route::has('settings.branding.edit'))
                    @can('branding-settings')
                    <li>
                        <a class="dropdown-item" href="{{ route('settings.branding.edit') }}">
                            <i class="ti ti-palette me-2 ti-sm"></i>
                            <span class="align-middle">Branding Settings</span>
                        </a>
                    </li>
                    @endcan
                    @endif
                    @if(Route::has('settings.mail.edit'))
                    @can('mail-settings')
                    <li>
                        <a class="dropdown-item" href="{{ route('settings.mail.edit') }}">
                            <i class="fa-solid fa-envelope-open-text me-2"></i>
                            <span class="align-middle">Email Settings</span>
                        </a>
                    </li>
                    @endcan
                    @endif
                    @if(Route::has('settings.realtime.edit'))
                    @can('realtime-settings')
                    <li>
                        <a class="dropdown-item" href="{{ route('settings.realtime.edit') }}">
                            <i class="ti ti-broadcast me-2"></i>
                            <span class="align-middle">Realtime Settings</span>
                        </a>
                    </li>
                    @endcan
                    @endif
                    <li>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <a class="dropdown-item pointer" href="javascript:void(0);"
                                onclick="event.preventDefault();this.closest('form').submit();">
                                <i class="ti ti-logout me-2 ti-sm"></i>
                                <span class="align-middle">Logout</span>
                            </a>
                        </form>
                    </li>
                </ul>
            </li>
            <!--/ User -->
        </ul>
    </div>
</nav>
@push('page-scripts')
<script>
window.filterHeaderNotifications = function (filter, button, event) {
    event?.preventDefault();
    event?.stopPropagation();
    let visible = 0;
    document.querySelectorAll('.notification-filter').forEach(item => {
        item.classList.toggle('btn-primary', item === button);
        item.classList.toggle('btn-outline-primary', item !== button);
    });
    document.querySelectorAll('.notification-row').forEach(row => {
        const show = filter === 'all' || row.dataset.state === filter;
        row.classList.toggle('d-none', !show);
        if (show) visible++;
    });
    const notificationList = document.querySelector('.notification-scroll-area');
    if (notificationList) notificationList.scrollTop = 0;
    document.getElementById('notification-filter-empty')?.classList.toggle('d-none', visible !== 0);
};

document.addEventListener('DOMContentLoaded', () => {
    const syncThemeControl = () => {
        const selected = window.PmsTheme?.selected || 'system';
        const dark = document.documentElement.classList.contains('dark-style');
        const icon = document.getElementById('pms-theme-icon');
        if (icon) icon.className = `ti ${selected === 'system' ? 'ti-device-desktop' : (dark ? 'ti-moon-stars' : 'ti-sun')} ti-md`;
        document.querySelectorAll('.pms-theme-choice').forEach(choice => {
            choice.classList.toggle('active', choice.dataset.themeChoice === selected);
            choice.querySelector('.theme-check')?.classList.toggle('invisible', choice.dataset.themeChoice !== selected);
        });
    };
    document.querySelectorAll('.pms-theme-choice').forEach(choice => {
        choice.addEventListener('click', () => {
            window.PmsTheme?.apply(choice.dataset.themeChoice);
            syncThemeControl();
        });
    });
    window.addEventListener('pms-theme-changed', syncThemeControl);
    syncThemeControl();

    document.querySelectorAll('.notification-row').forEach(row => {
        const openNotification = () => window.location.assign(row.dataset.url);
        row.addEventListener('click', event => {
            event.preventDefault();
            openNotification();
        });
        row.addEventListener('keydown', event => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                openNotification();
            }
        });
    });
});
</script>
@endpush
