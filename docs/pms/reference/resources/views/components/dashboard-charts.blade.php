@props(['chartData'])

<section class="row g-4 mt-1 dashboard-chart-grid">
    <div class="col-xl-5">
        <div class="card h-100 dashboard-chart-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <span class="dashboard-chart-kicker">Portfolio</span>
                        <h5 class="mb-1">Project health</h5>
                        <p class="text-muted small mb-0">Projects grouped by their current status</p>
                    </div>
                    <span class="dashboard-chart-icon"><i class="ti ti-chart-donut-3"></i></span>
                </div>
                <div id="dashboard-project-chart" class="dashboard-chart"></div>
            </div>
        </div>
    </div>
    <div class="col-xl-7">
        <div class="card h-100 dashboard-chart-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <span class="dashboard-chart-kicker">Delivery flow</span>
                        <h5 class="mb-1">Tasks by stage</h5>
                        <p class="text-muted small mb-0">Current workload across the delivery pipeline</p>
                    </div>
                    <span class="dashboard-chart-icon"><i class="ti ti-chart-bar"></i></span>
                </div>
                <div id="dashboard-task-chart" class="dashboard-chart"></div>
            </div>
        </div>
    </div>
</section>

@once
@push('page-scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const chartData = @json($chartData);
    const charts = [];
    const palette = ['#f59e0b', '#635bff', '#16a86b', '#0ea5e9', '#ef4444'];
    const themeColors = () => {
        const styles = getComputedStyle(document.documentElement);
        return {
            text: styles.getPropertyValue('--pms-text').trim() || '#202334',
            muted: styles.getPropertyValue('--pms-muted').trim() || '#71768a',
            border: styles.getPropertyValue('--pms-border').trim() || '#e7e9f0',
            dark: document.documentElement.classList.contains('dark-style')
        };
    };
    const renderCharts = () => {
        charts.splice(0).forEach(chart => chart.destroy());
        if (!window.ApexCharts) return;
        const colors = themeColors();
        const projectChart = new ApexCharts(document.querySelector('#dashboard-project-chart'), {
            chart:{type:'donut',height:285,background:'transparent',toolbar:{show:false}},
            series:chartData.projects.series,
            labels:chartData.projects.labels,
            colors:palette,
            stroke:{width:3,colors:[colors.dark ? '#171a24' : '#fff']},
            legend:{position:'bottom',fontSize:'12px',labels:{colors:colors.muted},markers:{radius:10}},
            dataLabels:{enabled:false},
            plotOptions:{pie:{donut:{size:'72%',labels:{show:true,name:{color:colors.muted},value:{color:colors.text,fontSize:'24px',fontWeight:700},total:{show:true,label:'Total projects',color:colors.muted,formatter:w=>w.globals.seriesTotals.reduce((a,b)=>a+b,0)}}}}},
            tooltip:{theme:colors.dark ? 'dark' : 'light'},
            noData:{text:'No project data',style:{color:colors.muted}}
        });
        const taskChart = new ApexCharts(document.querySelector('#dashboard-task-chart'), {
            chart:{type:'bar',height:285,background:'transparent',toolbar:{show:false}},
            series:[{name:'Tasks',data:chartData.tasks.series}],
            colors:['#635bff'],
            plotOptions:{bar:{borderRadius:7,columnWidth:'48%',distributed:true}},
            dataLabels:{enabled:false},
            legend:{show:false},
            grid:{borderColor:colors.border,strokeDashArray:4},
            xaxis:{categories:chartData.tasks.labels,labels:{style:{colors:chartData.tasks.labels.map(()=>colors.muted),fontSize:'12px'}},axisBorder:{color:colors.border},axisTicks:{show:false}},
            yaxis:{min:0,forceNiceScale:true,labels:{style:{colors:[colors.muted]},formatter:value=>Number.isInteger(value)?value:''}},
            tooltip:{theme:colors.dark ? 'dark' : 'light'},
            noData:{text:'No task data',style:{color:colors.muted}}
        });
        projectChart.render(); taskChart.render(); charts.push(projectChart, taskChart);
    };
    renderCharts();
    window.addEventListener('pms-theme-changed', () => setTimeout(renderCharts, 80));
});
</script>
@endpush
@endonce
