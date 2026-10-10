@props(['eyebrow' => 'Workspace', 'title', 'subtitle'])

<div class="dashboard-table-heading">
    <div>
        <span class="dashboard-chart-kicker">{{ $eyebrow }}</span>
        <h5 class="mb-1">{{ $title }}</h5>
        <p class="text-muted small mb-0">{{ $subtitle }}</p>
    </div>
    <span class="dashboard-chart-icon"><i class="ti ti-table"></i></span>
</div>
