@extends('layouts.app')
@section('content')
    <div class="container-xxl flex-grow-1 container-p-y py-4">
        <x-dashboard-heading />
        <div class="row g-4">
            
            <div class="col-xl-2 col-sm-6">
                <div class="card h-100 main-card text-center border-1 p-3">
                    <i class="fas fa-tasks card-icon text-primary"></i>
                    <h5 class="mb-2 card-title">Ongoing Projects</h5>
                    <h4 class="mb-0">{{ $ongoingProjectCount ?? $activeProjectCount ?? 0 }}</h4>
                </div>
            </div>
            <div class="col-xl-2 col-sm-6">
                <div class="card h-100 main-card text-center border-2 p-3">
                    <i class="fas fa-circle-check card-icon text-success"></i>
                    <h5 class="mb-2 card-title">Completed Projects</h5>
                    <h4 class="mb-0">{{ $completedProjectCount ?? $inactiveProjectCount ?? 0 }}</h4>
                </div>
            </div>
            <div class="col-xl-2 col-sm-6">
                <div class="card h-100 main-card text-center border-2 p-3">
                    <i class="fas fa-circle-xmark card-icon text-danger"></i>
                    <h5 class="mb-2 card-title">Cancelled Projects</h5>
                    <h4 class="mb-0">{{ $cancelledProjectCount ?? 0 }}</h4>
                </div>
            </div>

            <div class="col-xl-2 col-sm-6">
                <div class="card h-100 main-card text-center border-2 p-3">
                    <i class="fas fa-tasks card-icon text-info"></i>
                    <h5 class="mb-2 card-title">Total Tasks</h5>
                    <h4 class="mb-0">{{ $totalTasksCount ?? 0 }}</h4>
                </div>
            </div>

            <div class="col-xl-2 col-sm-6">
                <div class="card h-100 main-card text-center border-2 p-3">
                    <i class="fas fa-tasks card-icon text-secondary"></i>
                    <h5 class="mb-2 card-title">Pending Tasks</h5>
                    <h4 class="mb-0">{{ $pendingTasksCount ?? 0 }}</h4>
                </div>
            </div>

            <div class="col-xl-2 col-sm-6">
                <div class="card h-100 main-card text-center border-2 p-3">
                    <i class="fas fa-tasks card-icon text-primary"></i>
                    <h5 class="mb-2 card-title">In Progress Tasks</h5>
                    <h4 class="mb-0">{{ $inProgressTasksCount ?? 0 }}</h4>
                </div>
            </div>

            <div class="col-xl-2 col-sm-6">
                <div class="card h-100 main-card text-center border-2 p-3">
                    <i class="fas fa-tasks card-icon text-success"></i>
                    <h5 class="mb-2 card-title">Completed Tasks</h5>
                    <h4 class="mb-0">{{ $completedTasksCount ?? 0 }}</h4>
                </div>
            </div>

        </div>
        <x-dashboard-charts :chart-data="$dashboardCharts" />
        <x-dashboard-insights :insights="$dashboardInsights" />
    <div class="row mt-4 dashboard-table-section">
        <div class="col-12">
            <div class="card dashboard-table-card">
                <div class="card-body">
                    <x-dashboard-table-heading title="My projects" subtitle="Projects connected to your assigned tasks and current delivery progress." />
                    <div class="table-responsive">
                        <table class="table" id="table" style="width: 100%">
                            <thead>
                                <tr>
                                    <th>Project</th>
                                    <th>Durations</th>
                                    <th>Leader</th>
                                    <th>Team</th>
                                    <th>Progress</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div>
@endsection
@push('css-after')
    <style>
        .main-card {
            transform: scale(0.95);
            opacity: 0;
            transition: all 0.4s ease-in-out;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            color: #000;
            border-radius: 0px;
        }

        .border-1 {
            border: 0px;
            border-left: 2px solid #7367f0 !important;
        }

        .border-2 {
            border: 0px;
            border-left: 2px solid #808390 !important;
        }

        .border-3 {
            border: 0px;
            border-left: 2px solid #28c76f !important;
        }

        .border-4 {
            border: 0px;
            border-left: 2px solid #ff9f43 !important;
        }

        .card.show {
            transform: scale(1);
            opacity: 1;
        }

        .main-card:hover {
            transform: scale(1.05);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }

        .card-icon {
            font-size: 25px;
        }
    </style>
@endpush
@push('page-scripts')
<script>
    let datatable;
    window.baseUrl = "{{ url('/') }}";
    $(document).ready(function() {
        datatable = $("#table").DataTable({
            serverSide: false,
            processing: true,
            paging: true,
            responsive: true,
            ajax: {
                url: "{{ route('dashboard.index') }}",
                type: "GET",
                dataSrc: "data",
            },
            columns: [{
                    data: "name",
                    render: function(data, type, row) {
                        return `<a href="${window.baseUrl}/projects/${row.id}">
                                    <img src="${row.image}" alt="${row.name}" class="dashboard-project-avatar" onerror="this.onerror=null;this.src='{{ asset('assets/img/sm-logo.png') }}';">
                                    <strong class="dashboard-project-name">${row.name}</strong>
                                </a>`
                    }
                },
                {
                    data: "start_date",
                    render: function(data, type, row) {
                        const overdue = row.is_overdue ? '<span class="badge bg-danger ms-1">Overdue</span>' : '';
                        return `<span class="badge bg-primary">${row.start_date || 'Not set'}</span>
                        <span class="badge bg-warning">${row.end_date || 'No deadline'}</span>${overdue}`
                    }
                },
                {
                    data: "image",
                    render: function(data, type, row) {
                        if (!row.users || row.users.length === 0) {
                            return '';
                        }

                        let userHtml ='<ul class="list-unstyled m-0 avatar-group d-flex align-items-center">';

                        row.users.forEach(user => {
                            let roles = Array.isArray(user.roles) ? user.roles : [];
                            if (roles.length && (roles[0].role_key == "team_leader" || roles[0].role_key == "project_manager" || roles[0].role_key == "admin")) {
                                userHtml += `
                                    <li data-bs-toggle="tooltip" data-popup="tooltip-custom"
                                        data-bs-placement="top" class="avatar avatar-xs pull-up"
                                        title="${user.name}">
                                        <img src="${user.profile_img}" alt="${user.name}" class="rounded-circle">
                                    </li>
                                `;
                            }
                        });

                        userHtml += '</ul>';
                        return userHtml;
                    }


                },
                {
                    data: null,
                    render: function(data, type, row) {
                        if (!row.users || row.users.length === 0) {
                            return '';
                        }

                        let userHtml ='<ul class="list-unstyled m-0 avatar-group d-flex align-items-center">';

                        row.users.forEach(user => {
                            let roles = Array.isArray(user.roles) ? user.roles : [];
                            if (roles.length && roles[0].role_key != "team_leader" && roles[0].role_key != "project_manager" && roles[0].role_key != "admin") {
                                userHtml += `
                                    <li data-bs-toggle="tooltip" data-popup="tooltip-custom"
                                        data-bs-placement="top" class="avatar avatar-xs pull-up"
                                        title="${user.name}">
                                        <img src="${user.profile_img}" alt="${user.name}" class="rounded-circle">
                                    </li>
                                `;
                            }
                        });

                        userHtml += '</ul>';
                        return userHtml;
                    }


                },
                {
                    data: null,
                    render: function(data, type, row) {
                        return `<div class="progress" style="height: 16px;">
                                            <div class="progress-bar" role="progressbar"
                                                style="width: ${row.completion_percentage}%;"
                                                aria-valuenow="${row.completion_percentage}" aria-valuemin="0"
                                                aria-valuemax="100">
                                                ${row.completion_percentage}%
                                            </div>
                                        </div>`;
                    }
                },
            ],
            language: {
                search: "_INPUT_",
                searchPlaceholder: "Search...",
            },
            lengthMenu: [
                [10, 25, 50, 100, -1],
                [10, 25, 50, 100, "All"],
            ],
            pageLength: 10,
        });
    });
    document.addEventListener("DOMContentLoaded", function() {
        const cards = document.querySelectorAll(".main-card");
        cards.forEach((card, index) => {
            setTimeout(() => {
                card.classList.add("show");
            }, index * 200);
        });
    });
</script>
@endpush
