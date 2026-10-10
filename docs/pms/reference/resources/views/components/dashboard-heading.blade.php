<section class="dashboard-heading mb-4">
    <div>
        <span class="dashboard-eyebrow">{{ now()->format('l, F j') }}</span>
        <h2 class="dashboard-title">Welcome back, {{ auth()->user()->name }}</h2>
        <p class="dashboard-subtitle mb-0">Here is what is happening across your workspace today.</p>
    </div>
    <div class="dashboard-actions">
        @can('my-work-view')
            <a href="{{ route('my-work.index') }}" class="btn btn-label-primary">
                <i class="ti ti-layout-kanban"></i> My work
            </a>
        @endcan
        @can('project-view')
            <a href="{{ route('projects.index') }}" class="btn btn-primary">
                <i class="ti ti-folder"></i> View projects
            </a>
        @endcan
    </div>
</section>
