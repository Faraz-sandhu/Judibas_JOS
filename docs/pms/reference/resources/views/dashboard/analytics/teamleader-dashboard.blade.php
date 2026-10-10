@extends('layouts.app')
@section('content')
    <div class="container-xxl flex-grow-1 container-p-y py-4">
        <x-dashboard-heading />
        <div class="row g-4">
            
            <div class="col-xl-2 col-sm-6">
                <div class="card h-100 main-card text-center border-4 p-3">
                    <i class="fas fa-users-cog card-icon text-warning"></i>
                    <h5 class="mb-2 card-title">Team Members</h5>
                    <h4 class="mb-0">{{ $teamMembersCount ?? 0 }}</h4>
                </div>
            </div>
            
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
                    <i class="fas fa-tasks card-icon text-danger"></i>
                    <h5 class="mb-2 card-title">Unassigned Tasks</h5>
                    <h4 class="mb-0">{{ $unassignedTasksCount ?? 0 }}</h4>
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
        </div>
        <x-dashboard-charts :chart-data="$dashboardCharts" />
        <x-dashboard-insights :insights="$dashboardInsights" />
    <div class="row mt-4 dashboard-table-section">
        <div class="col-12">
            <div class="card dashboard-table-card">
                <div class="card-body">
                    <x-dashboard-table-heading eyebrow="Team capacity" title="Team members" subtitle="Assignment distribution and delivery status across your team." />
                    <div class="table-responsive">
                        <table class="table" id="table" style="width: 100%">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Designation</th>
                                    <th>Assign Projects</th>
                                    <th>Assigned Tasks</th>
                                    <th>Pending Tasks</th>
                                    <th>in Progress Tasks</th>
                                    <th>Completed Tasks</th>
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
                columns: [
                    { data: 'name' },
                    { data: 'role' },
                    { data: 'assigned_projects',
                    render: function (data){
                            return `<span class="badge bg-label-success text-uppercase">${data}</span>`;
                        }
                     },
                    { data: 'assigned_tasks',
                        render: function (data){
                            return `<span class="badge bg-label-warning text-uppercase">${data}</span>`;
                        }
                    },
                    { data: 'pending_tasks',
                    render: function (data){
                            return `<span class="badge bg-label-secondary text-uppercase">${data}</span>`;
                        }
                    },
                    { data: 'in_progress_tasks',
                    render: function (data){
                            return `<span class="badge bg-label-primary text-uppercase">${data}</span>`;
                        }
                    },
                    { data: 'completed_tasks',
                    render: function (data){
                            return `<span class="badge bg-label-success text-uppercase">${data}</span>`;
                        }
                    }
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
