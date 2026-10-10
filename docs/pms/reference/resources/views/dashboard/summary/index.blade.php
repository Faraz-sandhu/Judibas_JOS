@extends('layouts.app')
@push('css-after')
    <style>
        #summaryTable td:nth-child(3) {
            max-width: 200px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        #summaryTable td:nth-child(3):hover {
            cursor: pointer;
        }

        /* DataTables' legacy clipboard panel is replaced by the app toast. */
        .dt-button-info { display: none !important; }

        #summaryTable_wrapper .work-log-top-row,
        #summaryTable_wrapper .work-log-bottom-row { margin-left: 0; margin-right: 0; }

        #summaryTable_wrapper .dataTables_length label,
        #summaryTable_wrapper .dataTables_filter label {
            margin-bottom: 0;
            color: #5d596c;
            font-size: .875rem;
            font-weight: 500;
        }

        #summaryTable_wrapper .dataTables_length select {
            min-width: 72px;
            margin: 0 .35rem;
            border-color: #dbdade;
            border-radius: .5rem;
            box-shadow: none;
        }

        #summaryTable_wrapper .dataTables_filter { text-align: right; }
        #summaryTable_wrapper .dataTables_filter input {
            min-width: 220px;
            margin-left: .5rem;
            padding: .45rem .75rem;
            border: 1px solid #dbdade;
            border-radius: .5rem;
            background: #fff;
            outline: none;
        }

        #summaryTable_wrapper .dataTables_filter input:focus {
            border-color: #7367f0;
            box-shadow: 0 0 0 .2rem rgba(115, 103, 240, .12);
        }

        #summaryTable_wrapper .work-log-export-row {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            margin: .15rem 0 1rem;
        }

        #summaryTable_wrapper .dt-buttons { display: inline-flex !important; width: auto !important; margin: 0 !important; }
        #summaryTable_wrapper .dt-buttons > .report-export-trigger {
            display: inline-flex !important;
            align-items: center;
            justify-content: center;
            min-width: 108px;
            margin: 0 !important;
            padding: .52rem .9rem !important;
            border: 1px solid #7367f0 !important;
            border-radius: .5rem !important;
            background: #7367f0 !important;
            box-shadow: 0 2px 6px rgba(115, 103, 240, .22) !important;
            color: #fff !important;
            font-size: .8125rem;
            font-weight: 600;
        }

        #summaryTable_wrapper .dt-buttons > .report-export-trigger:hover {
            border-color: #685dd8 !important;
            background: #685dd8 !important;
            box-shadow: 0 4px 10px rgba(115, 103, 240, .28) !important;
            transform: translateY(-1px);
        }

        #summaryTable_wrapper .dt-buttons > .report-export-trigger > span {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .42rem;
            line-height: 1;
        }

        #summaryTable_wrapper .report-export-trigger.dropdown-toggle::after {
            align-self: center;
            margin-top: 0;
            margin-left: .1rem;
            vertical-align: middle;
        }

        #summaryTable_wrapper .work-log-table-scroll { width: 100%; overflow-x: auto; overflow-y: hidden; }
        #summaryTable_wrapper .work-log-table-scroll table { min-width: 1000px; width: 100% !important; }

        .export-trigger-icon,
        .export-option-icon { display: inline-flex; align-items: center; justify-content: center; flex: 0 0 auto; line-height: 1; }
        .export-trigger-icon svg,
        .export-option-icon svg { display: block; width: 16px; height: 16px; stroke: currentColor; }

        div.dt-button-collection {
            min-width: 250px !important;
            padding: .6rem !important;
            z-index: 1090 !important;
        }

        div.dt-button-collection .dt-button,
        div.dt-button-collection .btn {
            display: flex !important;
            align-items: center;
            width: 100%;
            padding: .82rem .95rem !important;
            gap: .85rem !important;
            border-radius: .6rem !important;
            line-height: 1.35 !important;
            text-align: left !important;
        }

        div.dt-button-collection .dt-button + .dt-button,
        div.dt-button-collection .btn + .btn { margin-top: .38rem !important; }
        div.dt-button-collection .dt-button > span:last-child,
        div.dt-button-collection .btn > span:last-child { flex: 1 1 auto; text-align: left !important; }

        .export-option-icon { width: 32px; height: 32px; flex-basis: 32px; border-radius: .6rem; }
        .export-copy-icon { color: #7367f0; background: rgba(115, 103, 240, .12); }
        .export-csv-icon { color: #00a6a6; background: rgba(0, 166, 166, .12); }
        .export-excel-icon { color: #28a745; background: rgba(40, 167, 69, .12); }
        .export-pdf-icon { color: #ea5455; background: rgba(234, 84, 85, .12); }

        @media (max-width: 767.98px) {
            #summaryTable_wrapper .dataTables_filter { margin-top: .75rem; text-align: left; }
            #summaryTable_wrapper .dataTables_filter label { width: 100%; }
            #summaryTable_wrapper .dataTables_filter input { width: 100%; min-width: 0; margin: .4rem 0 0; }
            #summaryTable_wrapper .work-log-export-row { justify-content: flex-start; margin-top: .75rem; }
        }
    </style>
@endpush
@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
        <div>
            <h4 class="mb-1">Work Logs</h4>
            <p class="text-muted mb-0">Review completed work and tracked time by employee, project and date.</p>
        </div>
        <span class="badge bg-label-primary"><i class="ti ti-clock me-1"></i>Time and activity report</span>
    </div>
    {{-- <div class="row">
        @can('filter-date-summary')
            <div class="col-md-4 mb-5">
                <label for="filterDate" class="form-label">Filter by Date:</label>
                <input type="date" id="filterDate" class="form-control">
            </div>
        @endcan
        @can('filter-user-summary')
            <div class="col-md-4 mb-5">
                <label for="filterUser" class="form-label">Filter by Employee:</label>
                <select id="filterUser" class="selectpicker w-100" data-style="btn-default" data-live-search="true">
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}">{{ $user->name }}</option>
                    @endforeach
                </select>
            </div>
        @endcan
    </div>
    <div class="row" id="summaryContainer">
        @include('dashboard.summary.summaryCard', ['summary' => $summary , 'summaryPending' => $summaryPending])
    </div> --}}


    <form id="filterForm">
        <div class="row g-2">
            @can('view-all-users-summary')
                <div class="col-md-3">
                    <label class="form-label">User</label>
                    <select name="user_id" class="form-select select2 user-select" data-style="btn-default" data-live-search="true">
                        <option value="" selected>All</option>
                        @foreach ($users as $u)
                            <option value="{{ $u->id }}">{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endcan

            <div class="col-md-3">
                <label class="form-label">Project</label>
                <select name="project_id" class="form-select select2 project-select" data-style="btn-default"
                    data-live-search="true">
                    <option value="" selected>All</option>
                    @foreach ($projects as $project)
                        <option value="{{ $project->id }}">{{ $project->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label">From</label>
                <input type="date" name="start_date" class="form-control" />
            </div>

            <div class="col-md-3">
                <label class="form-label">To</label>
                <input type="date" name="end_date" class="form-control" />
            </div>

            <div class="col-md-12 align-items-end text-end">
                <button type="button" id="resetFilters" class="btn btn-danger">Reset</button>
                <button type="submit" class="btn btn-primary">Apply Filter</button>
            </div>
        </div>
    </form>
    <div class="row mt-2">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <table id="summaryTable" class="table">
                        <thead>
                            <tr>
                                <th class="text-nowrap">Project</th>
                                <th class="text-nowrap">Type</th>
                                <th class="text-nowrap title-column">Title</th>
                                <th class="text-nowrap">Status</th>
                                <th class="text-nowrap">User</th>
                                <th class="text-nowrap">Invested Time</th>
                                <th class="text-nowrap">Start Date</th>
                                <th class="text-nowrap">Due Date</th>
                                <th class="text-nowrap">Assigned By</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
@push('page-scripts')
    {{-- @include('dashboard.summary.script') --}}

    <script>
        window.exportWorkLogs = function(e, dt, button, config) {
            const context = this;
            const exportLabels = {
                copyHtml5: 'Copying work logs...',
                csvHtml5: 'Preparing CSV export...',
                excelHtml5: 'Preparing Excel export...',
                pdfHtml5: 'Preparing PDF export...'
            };
            window.AppLoader?.show(exportLabels[config.exportAction] || 'Preparing export...');

            setTimeout(function() {
                try {
                    $.fn.dataTable.ext.buttons[config.exportAction].action.call(
                        context, e, dt, button, config
                    );
                    if (config.exportAction === 'copyHtml5') {
                        document.querySelectorAll('.dt-button-info').forEach(notice => notice.remove());
                        const copiedRows = dt.rows({search: 'applied'}).count();
                        if (window.toastr) {
                            toastr.success(`${copiedRows} work log ${copiedRows === 1 ? 'row' : 'rows'} copied to clipboard.`);
                        }
                    }
                } finally {
                    // Keep the global loader visible long enough to be painted before
                    // DataTables displays its copy notice or starts the file download.
                    setTimeout(() => window.AppLoader?.hide(), 300);
                }
            }, 175);
        };

        const workLogExportFilename = () => {
            const date = new Date();
            const localDate = [date.getFullYear(), String(date.getMonth() + 1).padStart(2, '0'), String(date.getDate()).padStart(2, '0')].join('-');
            return `PMS_Work_Logs_${localDate}`;
        };

        $(document).ready(function() {

            $('.select2').select2({
                placeholder: function() {
                    return $(this).find('option[value=""]')
                        .text(); // Use the default option text as placeholder
                },
                allowClear: false,
                width: '100%'
            });

            $('#resetFilters').on('click', function() {
                $('input[name="start_date"]').val('');
                $('input[name="end_date"]').val('');
                $('.user-select').select2().val('').trigger('change');
                $('.project-select').select2().val('').trigger('change');
                $('#filterForm')[0].reset();
                $('#summaryTable').DataTable().ajax.reload();
            });
        });

        $('#filterForm').on('submit', function(e) {
            e.preventDefault();
            $('#summaryTable').DataTable().ajax.reload();
        });



        $('#summaryTable').DataTable({
            processing: true,
            serverSide: false,
            dom:
                '<"row work-log-top-row align-items-center mb-2"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
                '<"work-log-export-row"B>' +
                '<"work-log-table-scroll"rt>' +
                '<"row work-log-bottom-row align-items-center"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
            buttons: [{
                extend: 'collection',
                text: '<span class="export-trigger-icon"><svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 3v10m0 0-4-4m4 4 4-4M5 15v3a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-3" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span><span>Export</span>',
                className: 'btn report-export-trigger',
                autoClose: true,
                align: 'button-right',
                buttons: [{
                        extend: 'copyHtml5',
                        exportAction: 'copyHtml5',
                        text: '<span class="export-option-icon export-copy-icon"><svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="9" y="9" width="10" height="10" rx="2" stroke-width="1.8"/><path d="M15 9V7a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span><span>Copy to clipboard</span>',
                        className: 'btn',
                        action: window.exportWorkLogs,
                        copySuccess: false,
                        exportOptions: { modifier: { search: 'applied' } }
                    },
                    {
                        extend: 'csvHtml5',
                        filename: workLogExportFilename,
                        title: 'PMS Work Logs',
                        exportAction: 'csvHtml5',
                        text: '<span class="export-option-icon export-csv-icon"><svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8zm0 0v5h5" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M8 13h8M8 17h5" stroke-width="1.8" stroke-linecap="round"/></svg></span><span>Export as CSV</span>',
                        className: 'btn',
                        action: window.exportWorkLogs,
                        exportOptions: { modifier: { search: 'applied' } }
                    },
                    {
                        extend: 'excelHtml5',
                        filename: workLogExportFilename,
                        title: 'PMS Work Logs',
                        exportAction: 'excelHtml5',
                        text: '<span class="export-option-icon export-excel-icon"><svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="4" y="4" width="16" height="16" rx="2" stroke-width="1.8"/><path d="M9 4v16M15 4v16M4 9h16M4 15h16" stroke-width="1.8" stroke-linecap="round"/></svg></span><span>Export as Excel</span>',
                        className: 'btn',
                        action: window.exportWorkLogs,
                        exportOptions: { modifier: { search: 'applied' } }
                    },
                    {
                        extend: 'pdfHtml5',
                        filename: workLogExportFilename,
                        title: 'PMS Work Logs',
                        exportAction: 'pdfHtml5',
                        text: '<span class="export-option-icon export-pdf-icon"><svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8zm0 0v5h5" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M8 16h1.5a1.5 1.5 0 0 0 0-3H8zm0 0v-3m4 3v-3h1.5a1.5 1.5 0 0 1 0 3H12Zm0 0h2m3-3v3m0-3h1.5" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg></span><span>Export as PDF</span>',
                        className: 'btn',
                        action: window.exportWorkLogs,
                        orientation: 'landscape',
                        pageSize: 'A4',
                        exportOptions: { modifier: { search: 'applied' } },
                        customize: function(doc) {
                            doc.defaultStyle.fontSize = 8;
                            doc.styles.tableHeader.fontSize = 8;
                        }
                    }
                ]
            }],
            ajax: {
                url: '{{ route('summary.index') }}',
                data: function(d) {
                    d.user_id = $('select[name=user_id]').val();
                    d.project_id = $('select[name=project_id]').val();
                    d.start_date = $('input[name=start_date]').val();
                    d.end_date = $('input[name=end_date]').val();
                }
            },
            columns: [{
                    data: 'project',
                    render: function(data, type, row) {
                        return `<a href="/projects/${row.project_id}" class="fw-bold">${data}</a>`;
                    }
                },
                {
                    data: 'type',
                    render: function(data, type, row) {
                        if (row.type === 'Task') {
                            return '<span class="badge bg-label-primary">Task</span>';
                        }
                        if (row.type === 'Subtask') {
                            return '<span class="badge bg-label-danger">Subtask</span>';
                        }
                        return '-';
                    }
                },
                {
                    data: 'title',
                    render: function(data, type, row) {
                        return `<span class="d-inline-block text-truncate" style="max-width: 150px;" title="${data}">${data}</span>`;
                    }
                },
                {
                    data: 'status',
                    render: function(data, type, row) {
                        if (row.status === 'pending') {
                            return '<span class="badge bg-label-warning">Pending</span>';
                        }
                        if (row.status === 'in_progress') {
                            return '<span class="badge bg-label-info">In Progress</span>';
                        }
                        if (row.status === 'completed') {
                            return '<span class="badge bg-label-success">Completed</span>';
                        }
                        return '-';
                    }
                },
                {
                    data: 'user'
                },
                {
                    data: 'time_spent',
                    render: function(data, type, row) {
                        return '<span class="badge bg-label-primary">' + data + '</span>';
                    }
                },
                {
                    data: 'work_date'
                },
                {
                    data: 'due_date'
                },
                {
                    data: 'assigned_by'
                }
            ]
        });
    </script>
@endpush
