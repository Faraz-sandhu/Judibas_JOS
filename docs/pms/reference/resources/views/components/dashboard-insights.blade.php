@props(['insights'])

<section class="row g-4 mt-1 dashboard-insights-grid">
    @if($insights['show_workload'])
    <div class="col-xl-7">
        <div class="card h-100 dashboard-chart-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <span class="dashboard-chart-kicker">Team capacity</span>
                        <h5 class="mb-1">Workload by employee</h5>
                        <p class="text-muted small mb-0">Open tasks currently assigned to each person</p>
                    </div>
                    <span class="dashboard-chart-icon"><i class="ti ti-users-group"></i></span>
                </div>
                <div id="dashboard-workload-chart" class="dashboard-chart"></div>
            </div>
        </div>
    </div>
    @endif
    <div class="{{ $insights['show_workload'] ? 'col-xl-5' : 'col-12' }}">
        <div class="card h-100 dashboard-deadline-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <span class="dashboard-chart-kicker">Next 7 days</span>
                        <h5 class="mb-1">Upcoming deadlines</h5>
                        <p class="text-muted small mb-0">Tasks requiring attention soon</p>
                    </div>
                    <span class="dashboard-chart-icon"><i class="ti ti-calendar-due"></i></span>
                </div>
                <div class="dashboard-deadline-list">
                    @forelse($insights['deadlines'] as $deadline)
                        <a href="{{ url('projects/'.$deadline['project_id']) }}?issue={{ $deadline['id'] }}" class="dashboard-deadline-item">
                            <span class="dashboard-deadline-date {{ $deadline['due_label'] === 'Today' ? 'is-today' : '' }}">
                                {{ $deadline['due_label'] }}
                            </span>
                            <span class="dashboard-deadline-content">
                                <strong>{{ $deadline['title'] }}</strong>
                                <small>{{ $deadline['project'] }}</small>
                            </span>
                            <span class="avatar-group d-flex flex-row-reverse justify-content-end">
                                @foreach($deadline['assignees'] as $assignee)
                                    <img src="{{ $assignee['avatar'] }}" alt="{{ $assignee['name'] }}" title="{{ $assignee['name'] }}"
                                        onerror="this.onerror=null;this.src='{{ asset('assets/img/user-picture.png') }}';" class="dashboard-deadline-avatar">
                                @endforeach
                            </span>
                            <i class="ti ti-chevron-right text-muted"></i>
                        </a>
                    @empty
                        <div class="dashboard-insight-empty">
                            <span><i class="ti ti-calendar-check"></i></span>
                            <strong>No deadlines this week</strong>
                            <small>Your upcoming seven days are clear.</small>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</section>

@if($insights['show_workload'])
@once
@push('page-scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const workload = @json($insights['workload']);
    let chart = null;
    const renderWorkload = () => {
        chart?.destroy();
        const target = document.querySelector('#dashboard-workload-chart');
        if (!target || !window.ApexCharts) return;
        const styles = getComputedStyle(document.documentElement);
        const text = styles.getPropertyValue('--pms-text').trim();
        const muted = styles.getPropertyValue('--pms-muted').trim();
        const border = styles.getPropertyValue('--pms-border').trim();
        const dark = document.documentElement.classList.contains('dark-style');
        chart = new ApexCharts(target, {
            chart:{type:'bar',height:285,background:'transparent',toolbar:{show:false}},
            series:[{name:'Open tasks',data:workload.series}],
            colors:['#0ea5e9'],
            plotOptions:{bar:{horizontal:true,borderRadius:6,barHeight:'52%',dataLabels:{position:'top'}}},
            dataLabels:{enabled:true,offsetX:8,style:{colors:[text],fontWeight:700},formatter:value=>value},
            xaxis:{categories:workload.labels,min:0,forceNiceScale:true,labels:{style:{colors:workload.labels.map(()=>muted)}},axisBorder:{color:border},axisTicks:{show:false}},
            yaxis:{labels:{style:{colors:[muted],fontSize:'12px'},maxWidth:130}},
            grid:{borderColor:border,strokeDashArray:4},
            tooltip:{theme:dark?'dark':'light'},legend:{show:false},
            noData:{text:'No assigned open tasks',style:{color:muted}}
        });
        chart.render();
    };
    renderWorkload();
    window.addEventListener('pms-theme-changed', () => setTimeout(renderWorkload, 80));
});
</script>
@endpush
@endonce
@endif
