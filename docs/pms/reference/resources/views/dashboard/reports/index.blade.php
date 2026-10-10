@extends('layouts.app')

@push('css-before')
<style>
    /* =========================================================
       Project Reports - Professional DataTable Toolbar
    ========================================================== */

    #reportTable_wrapper .report-top-row,
    #reportTable_wrapper .report-export-row,
    #reportTable_wrapper .report-bottom-row {
        margin-left: 0;
        margin-right: 0;
    }

    /* Length selector */
    #reportTable_wrapper .dataTables_length label,
    #reportTable_wrapper .dataTables_filter label {
        margin-bottom: 0;
        color: #5d596c;
        font-size: .875rem;
        font-weight: 500;
    }

    #reportTable_wrapper .dataTables_length select {
        min-width: 72px;
        margin: 0 .35rem;
        border-color: #dbdade;
        border-radius: .5rem;
        box-shadow: none;
    }

    /* Search */
    #reportTable_wrapper .dataTables_filter {
        text-align: right;
    }

    #reportTable_wrapper .dataTables_filter input {
        min-width: 220px;
        margin-left: .5rem;
        border: 1px solid #dbdade;
        border-radius: .5rem;
        padding: .45rem .75rem;
        background: #fff;
        color: #5d596c;
        outline: none;
        transition: border-color .15s ease, box-shadow .15s ease;
    }

    #reportTable_wrapper .dataTables_filter input:focus {
        border-color: #7367f0;
        box-shadow: 0 0 0 .2rem rgba(115, 103, 240, .12);
    }

    /* Export row */
    #reportTable_wrapper .report-export-row {
        display: flex;
        justify-content: flex-end;
        align-items: center;
        margin-top: .15rem;
        margin-bottom: 1rem;
    }

    #reportTable_wrapper .dt-buttons {
        display: inline-flex !important;
        width: auto !important;
        margin: 0 !important;
    }

    /* Main Export button */
    #reportTable_wrapper .dt-buttons > .btn,
    #reportTable_wrapper .dt-buttons > .dt-button {
        width: auto !important;
        min-width: 108px;
        margin: 0 !important;
        padding: .52rem .9rem !important;
        display: inline-flex !important;
        align-items: center;
        justify-content: center;
        gap: .4rem;
        border: 1px solid #7367f0 !important;
        border-radius: .5rem !important;
        background: #7367f0 !important;
        color: #fff !important;
        box-shadow: 0 2px 6px rgba(115, 103, 240, .22) !important;
        font-size: .8125rem;
        font-weight: 600;
        line-height: 1.25;
        transition: transform .15s ease, background-color .15s ease,
            border-color .15s ease, box-shadow .15s ease;
    }

    #reportTable_wrapper .dt-buttons > .btn:hover,
    #reportTable_wrapper .dt-buttons > .dt-button:hover {
        background: #685dd8 !important;
        border-color: #685dd8 !important;
        color: #fff !important;
        transform: translateY(-1px);
        box-shadow: 0 4px 10px rgba(115, 103, 240, .28) !important;
    }

    #reportTable_wrapper .dt-buttons > .btn:focus,
    #reportTable_wrapper .dt-buttons > .btn:active,
    #reportTable_wrapper .dt-buttons > .dt-button:focus,
    #reportTable_wrapper .dt-buttons > .dt-button:active {
        background: #6257c9 !important;
        border-color: #6257c9 !important;
        color: #fff !important;
        box-shadow: 0 0 0 .2rem rgba(115, 103, 240, .18) !important;
    }

    #reportTable_wrapper .dt-buttons i {
        font-size: 1rem;
        line-height: 1;
    }

    /* Export dropdown / collection */
    div.dt-button-collection {
        min-width: 210px !important;
        z-index: 1090 !important;
        padding: .45rem !important;
        margin-top: .45rem !important;
        border: 1px solid #e6e6e9 !important;
        border-radius: .65rem !important;
        background: #fff !important;
        box-shadow: 0 8px 24px rgba(34, 48, 74, .14) !important;
        overflow: hidden;
    }

    div.dt-button-collection .dt-button,
    div.dt-button-collection .btn {
        width: 100% !important;
        min-width: 0 !important;
        margin: 0 !important;
        padding: .62rem .75rem !important;
        display: flex !important;
        align-items: center !important;
        justify-content: flex-start !important;
        gap: .65rem;
        border: 0 !important;
        border-radius: .45rem !important;
        background: transparent !important;
        box-shadow: none !important;
        color: #5d596c !important;
        font-size: .8125rem;
        font-weight: 500;
        text-align: left !important;
        white-space: nowrap;
        transition: background-color .15s ease, color .15s ease;
    }

    /* Keep every export option perfectly aligned */
    div.dt-button-collection .dt-button > i,
    div.dt-button-collection .btn > i {
        flex: 0 0 22px;
    }

    div.dt-button-collection .dt-button > span,
    div.dt-button-collection .btn > span {
        flex: 1 1 auto;
        display: block;
        min-width: 0;
        text-align: left !important;
        margin: 0 !important;
    }

    div.dt-button-collection .dt-button + .dt-button,
    div.dt-button-collection .btn + .btn {
        margin-top: .2rem !important;
    }

    div.dt-button-collection .dt-button:hover,
    div.dt-button-collection .btn:hover {
        background: #f5f3ff !important;
        color: #7367f0 !important;
    }

    div.dt-button-collection .dt-button:focus,
    div.dt-button-collection .dt-button:active,
    div.dt-button-collection .btn:focus,
    div.dt-button-collection .btn:active {
        background: #efedff !important;
        color: #6257c9 !important;
        box-shadow: none !important;
    }

    div.dt-button-collection .dt-button i,
    div.dt-button-collection .btn i {
        width: 22px;
        height: 22px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: .35rem;
        font-size: 1rem;
    }

    div.dt-button-collection .buttons-copy i {
        color: #7367f0;
        background: rgba(115, 103, 240, .10);
    }

    div.dt-button-collection .buttons-csv i {
        color: #00a6a6;
        background: rgba(0, 166, 166, .10);
    }

    div.dt-button-collection .buttons-excel i {
        color: #28a745;
        background: rgba(40, 167, 69, .10);
    }

    div.dt-button-collection .buttons-pdf i {
        color: #ea5455;
        background: rgba(234, 84, 85, .10);
    }

    /* Keep toolbar fixed inside the card.
       Only the table itself is horizontally scrollable. */
    #reportTable_wrapper {
        width: 100% !important;
        max-width: 100% !important;
        overflow: visible !important;
    }

    #reportTable_wrapper .report-table-scroll {
        width: 100%;
        max-width: 100%;
        overflow-x: auto;
        overflow-y: hidden;
        -webkit-overflow-scrolling: touch;
    }

    #reportTable_wrapper .report-table-scroll table.dataTable {
        width: 100% !important;
        min-width: 1050px;
        margin-top: 0 !important;
        margin-bottom: 0 !important;
    }

    #reportTable_wrapper .dataTables_info {
        padding-top: .85rem;
        color: #6f6b7d;
        font-size: .8125rem;
    }

    #reportTable_wrapper .dataTables_paginate {
        padding-top: .6rem;
    }

    /* Responsive */
    @media (max-width: 767.98px) {
        #reportTable_wrapper .dataTables_filter {
            margin-top: .75rem;
            text-align: left;
        }

        #reportTable_wrapper .dataTables_filter label {
            width: 100%;
        }

        #reportTable_wrapper .dataTables_filter input {
            width: 100%;
            min-width: 0;
            margin: .4rem 0 0;
        }

        #reportTable_wrapper .report-export-row {
            justify-content: flex-start;
            margin-top: .75rem;
        }
    }

    @media (max-width: 575.98px) {
        #reportTable_wrapper .dt-buttons > .btn,
        #reportTable_wrapper .dt-buttons > .dt-button {
            min-width: 104px;
        }
    }

    /* Refined export dropdown spacing + guaranteed visible SVG icons */
    #reportTable_wrapper .dt-buttons > .report-export-trigger {
        gap: .55rem !important;
    }

    #reportTable_wrapper .dt-buttons > .report-export-trigger > span {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: .42rem;
        line-height: 1;
    }

    #reportTable_wrapper .dt-buttons > .report-export-trigger.dropdown-toggle::after {
        align-self: center;
        margin-top: 0;
        margin-left: .1rem;
        vertical-align: middle;
    }

    .export-trigger-icon,
    .export-trigger-caret {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        line-height: 1;
        flex: 0 0 auto;
    }

    .export-trigger-icon svg,
    .export-trigger-caret svg,
    .export-option-icon svg {
        width: 16px;
        height: 16px;
        display: block;
        stroke: currentColor;
    }

    div.dt-button-collection {
        min-width: 250px !important;
        padding: .6rem !important;
    }

    div.dt-button-collection .dt-button,
    div.dt-button-collection .btn {
        padding: .82rem .95rem !important;
        gap: .85rem !important;
        border-radius: .6rem !important;
        line-height: 1.35 !important;
    }

    div.dt-button-collection .dt-button + .dt-button,
    div.dt-button-collection .btn + .btn {
        margin-top: .38rem !important;
    }

    .export-option-icon {
        width: 32px;
        height: 32px;
        flex: 0 0 32px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: .6rem;
        line-height: 1;
    }

    div.dt-button-collection .dt-button > span:last-child,
    div.dt-button-collection .btn > span:last-child {
        flex: 1 1 auto;
        display: block;
        text-align: left !important;
    }

    .export-copy-icon {
        color: #7367f0;
        background: rgba(115, 103, 240, .12);
    }

    .export-csv-icon {
        color: #00a6a6;
        background: rgba(0, 166, 166, .12);
    }

    .export-excel-icon {
        color: #28a745;
        background: rgba(40, 167, 69, .12);
    }

    .export-pdf-icon {
        color: #ea5455;
        background: rgba(234, 84, 85, .12);
    }

    /* Match the Work Logs export menu and use its toast confirmation. */
    .dt-button-info { display: none !important; }

    div.dt-button-collection {
        min-width: 250px !important;
        padding: .6rem !important;
        border: 1px solid var(--pms-border) !important;
        border-radius: .65rem !important;
        background: var(--pms-surface) !important;
        box-shadow: var(--pms-shadow) !important;
    }

    div.dt-button-collection .dt-button,
    div.dt-button-collection .btn {
        display: flex !important;
        align-items: center !important;
        width: 100% !important;
        margin: 0 !important;
        padding: .82rem .95rem !important;
        gap: .85rem !important;
        border: 0 !important;
        border-radius: .6rem !important;
        background: transparent !important;
        box-shadow: none !important;
        color: var(--pms-text) !important;
        line-height: 1.35 !important;
        text-align: left !important;
    }

    div.dt-button-collection .dt-button + .dt-button,
    div.dt-button-collection .btn + .btn { margin-top: .38rem !important; }

    div.dt-button-collection .dt-button:hover,
    div.dt-button-collection .btn:hover {
        background: var(--pms-primary-soft) !important;
        color: var(--pms-primary) !important;
    }

</style>
@endpush

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title">Project Reports</h4>
            </div>

            <div class="card-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <label for="company_filter">Company</label>
                        <select id="company_filter" class="form-select select2">
                            <option value="">All Companies</option>
                            @foreach ($companies as $company)<option value="{{ $company->id }}">{{ $company->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="project_filter">Project</label>
                        <select id="project_filter" class="form-select select2" style="width: 100%;">
                            <option value="">All Projects</option>
                            @foreach ($projects as $project)
                                <option value="{{ $project->id }}" data-company-id="{{ $project->company_id }}">{{ $project->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="department_filter">Department</label>
                        <select id="department_filter" class="form-select select2">
                            <option value="">All Departments</option>
                            @foreach ($departments as $department)<option value="{{ $department->id }}">{{ $department->dept_name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="user_filter">Employee</label>
                        <select id="user_filter" class="form-select select2">
                            <option value="">All Employees</option>
                            @foreach ($users as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="task_filter">Task</label>
                        <select id="task_filter" class="form-select select2" disabled style="width: 100%;">
                            <option value="">All Tasks</option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label for="subtask_filter">Subtask</label>
                        <select id="subtask_filter" class="form-select select2" disabled style="width: 100%;">
                            <option value="">All Subtasks</option>
                        </select>
                    </div>
                    <div class="col-md-2"><label for="from_date">Work from</label><input id="from_date" type="date" class="form-control"></div>
                    <div class="col-md-2"><label for="to_date">Work to</label><input id="to_date" type="date" class="form-control"></div>
                    <div class="col-md-2">
                        <label for="deadline_filter">Deadline</label>
                        <select id="deadline_filter" class="form-select">
                            <option value="">All deadlines</option><option value="on_track">On track</option><option value="overdue">Overdue</option>
                            <option value="met">Met</option><option value="late">Completed late</option><option value="no_deadline">No deadline</option>
                        </select>
                    </div>
                    <div class="col-12 d-flex justify-content-end gap-2">
                        <button id="reset_report_filters" type="button" class="btn btn-outline-secondary">Reset</button>
                        <button id="apply_report_filters" type="button" class="btn btn-primary">Apply filters</button>
                    </div>
                </div>

                <div class="row g-3 mb-4" id="report_summary">
                    @foreach (['Total work items' => 'total', 'Completed' => 'completed', 'Overdue' => 'overdue', 'Due next 7 days' => 'dueSoon', 'Hours logged' => 'hours'] as $label => $key)
                    <div class="col-6 col-lg"><div class="border rounded p-3 h-100"><small class="text-muted">{{ $label }}</small><div class="fs-4 fw-semibold mt-1" data-summary="{{ $key }}">0</div></div></div>
                    @endforeach
                </div>

                <div class="border rounded p-3 mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-2"><div><h5 class="mb-0">Department performance</h5><small class="text-muted">Progress and missed deadlines for the selected period</small></div><strong id="overall_progress">0%</strong></div>
                    <div id="department_report_rows"><div class="text-muted py-3">Loading department report...</div></div>
                </div>

                <table id="reportTable" class="table table-striped w-100">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Company</th>
                            <th>Project</th>
                            <th>Department</th>
                            <th>Title</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Deadline</th>
                            <th class="text-nowrap">Due Date</th>
                            <th>Assignee</th>
                            <th>Worked By</th>
                            <th class="text-center">Logged Time</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('page-scripts')
<script>
    $(document).ready(function() {
        const reportExportFilename = () => {
            const date = new Date();
            const localDate = [date.getFullYear(), String(date.getMonth() + 1).padStart(2, '0'), String(date.getDate()).padStart(2, '0')].join('-');
            return `PMS_Project_Report_${localDate}`;
        };
        const exportActions = {
            copyHtml5: $.fn.dataTable.ext.buttons.copyHtml5.action,
            csvHtml5: $.fn.dataTable.ext.buttons.csvHtml5.action,
            excelHtml5: $.fn.dataTable.ext.buttons.excelHtml5.action,
            pdfHtml5: $.fn.dataTable.ext.buttons.pdfHtml5.action
        };

        function exportAllRows(e, dt, button, config) {
            const buttonContext = this;
            const settings = dt.settings()[0];
            const originalStart = settings._iDisplayStart;

            dt.one('preXhr.dt.reportExport', function(event, requestSettings, requestData) {
                requestData.start = 0;
                requestData.length = 2147483647;

                dt.one('preDraw.dt.reportExport', function() {
                    exportActions[config.exportAction].call(
                        buttonContext,
                        e,
                        dt,
                        button,
                        config
                    );

                    if (config.exportAction === 'copyHtml5') {
                        document.querySelectorAll('.dt-button-info').forEach(notice => notice.remove());
                        const copiedRows = dt.rows({search: 'applied'}).count();
                        if (window.toastr) {
                            toastr.success(`${copiedRows} report ${copiedRows === 1 ? 'row' : 'rows'} copied to clipboard.`);
                        }
                    }

                    dt.one('preXhr.dt.reportExportRestore', function(
                        restoreEvent,
                        restoreSettings,
                        restoreData
                    ) {
                        settings._iDisplayStart = originalStart;
                        restoreData.start = originalStart;
                    });

                    setTimeout(function() {
                        dt.ajax.reload(null, false);
                    }, 0);

                    return false;
                });
            });

            dt.ajax.reload();
        }

        // Initialize Select2 for all dropdowns
        $('#company_filter, #project_filter, #department_filter, #user_filter, #task_filter, #subtask_filter').select2({
            placeholder: function() {
                return $(this).find('option[value=""]').text();
            },
            allowClear: true,
            width: '100%'
        });

        // Initialize DataTable
        var table = $('#reportTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: '{{ route('reports.data') }}',
                type: 'GET',
                data: function(d) {
                    d.project_id = $('#project_filter').val() || '';
                    d.task_id = $('#task_filter').val() || '';
                    d.subtask_id = $('#subtask_filter').val() || '';
                    d.company_id = $('#company_filter').val() || '';
                    d.department_id = $('#department_filter').val() || '';
                    d.user_id = $('#user_filter').val() || '';
                    d.from_date = $('#from_date').val() || '';
                    d.to_date = $('#to_date').val() || '';
                    d.deadline_health = $('#deadline_filter').val() || '';
                    d.export = false;
                }
            },
            columns: [
                {
                    data: null,
                    render: function(data, type, row, meta) {
                        return meta.row + meta.settings._iDisplayStart + 1;
                    }
                },
                { data: 'company_name' },
                { data: 'project_name' },
                { data: 'department_name' },
                { data: 'title' },
                {
                    data: null,
                    render: function(data, type, row) {
                        if (row.type == 'Task') {
                            return '<span class="badge bg-label-primary">Task</span>';
                        }

                        if (row.type == 'Subtask') {
                            return '<span class="badge bg-label-danger">Subtask</span>';
                        }

                        return '-';
                    }
                },
                {
                    data: null,
                    render: function(data, type, row) {
                        if (row.status == 'pending') {
                            return '<span class="badge bg-label-warning">Pending</span>';
                        }

                        if (row.status == 'in_progress') {
                            return '<span class="badge bg-label-info">In Progress</span>';
                        }

                        if (row.status == 'completed') {
                            return '<span class="badge bg-label-success">Completed</span>';
                        }

                        return '-';
                    }
                },
                { data: 'deadline_health', render: function(value) {
                    const styles = {'Overdue':'danger','Late':'danger','Met':'success','On track':'info','No deadline':'secondary'};
                    return '<span class="badge bg-label-' + (styles[value] || 'secondary') + '">' + value + '</span>';
                }},
                { data: 'due_date' },
                { data: 'assignee' },
                { data: 'worked_by' },
                { data: 'invest_time' }
            ],

            /*
             * Layout:
             * Row 1 => Show entries + Search
             * Row 2 => Compact Export dropdown aligned right
             * Row 3 => Table
             * Row 4 => Info + Pagination
             */
            dom:
                '<"row report-top-row align-items-center mb-2"' +
                    '<"col-sm-12 col-md-6"l>' +
                    '<"col-sm-12 col-md-6"f>' +
                '>' +
                '<"report-export-row"B>' +
                '<"report-table-scroll"rt>' +
                '<"row report-bottom-row align-items-center"' +
                    '<"col-sm-12 col-md-5"i>' +
                    '<"col-sm-12 col-md-7"p>' +
                '>',

            buttons: [
                {
                    extend: 'collection',
                    text: '<span class="export-trigger-icon"><svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 3v10m0 0-4-4m4 4 4-4M5 15v3a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-3" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span><span>Export</span>',
                    className: 'btn report-export-trigger',
                    autoClose: true,
                    align: 'button-right',
                    buttons: [
                        {
                            extend: 'copyHtml5',
                            text: '<span class="export-option-icon export-copy-icon"><svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="9" y="9" width="10" height="10" rx="2" stroke-width="1.8"/><path d="M15 9V7a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span><span>Copy to clipboard</span>',
                            exportAction: 'copyHtml5',
                            className: 'btn',
                            action: exportAllRows,
                            copySuccess: false,
                            exportOptions: {
                                modifier: {
                                    search: 'applied'
                                }
                            }
                        },
                        {
                            extend: 'csvHtml5',
                            filename: reportExportFilename,
                            title: 'PMS Project Report',
                            text: '<span class="export-option-icon export-csv-icon"><svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8zm0 0v5h5" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M8 13h8M8 17h5" stroke-width="1.8" stroke-linecap="round"/></svg></span><span>Export as CSV</span>',
                            exportAction: 'csvHtml5',
                            className: 'btn',
                            action: exportAllRows,
                            exportOptions: {
                                modifier: {
                                    search: 'applied'
                                }
                            }
                        },
                        {
                            extend: 'excelHtml5',
                            filename: reportExportFilename,
                            title: 'PMS Project Report',
                            text: '<span class="export-option-icon export-excel-icon"><svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="4" y="4" width="16" height="16" rx="2" stroke-width="1.8"/><path d="M9 4v16M15 4v16M4 9h16M4 15h16" stroke-width="1.8" stroke-linecap="round"/></svg></span><span>Export as Excel</span>',
                            exportAction: 'excelHtml5',
                            className: 'btn',
                            action: exportAllRows,
                            exportOptions: {
                                modifier: {
                                    search: 'applied'
                                }
                            }
                        },
                        {
                            extend: 'pdfHtml5',
                            filename: reportExportFilename,
                            title: 'PMS Project Report',
                            text: '<span class="export-option-icon export-pdf-icon"><svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8zm0 0v5h5" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M8 16h1.5a1.5 1.5 0 0 0 0-3H8zm0 0v-3m4 3v-3h1.5a1.5 1.5 0 0 1 0 3H12Zm0 0h2m3-3v3m0-3h1.5" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg></span><span>Export as PDF</span>',
                            exportAction: 'pdfHtml5',
                            className: 'btn',
                            orientation: 'landscape',
                            pageSize: 'A4',
                            action: exportAllRows,
                            exportOptions: {
                                modifier: {
                                    search: 'applied'
                                }
                            },
                            customize: function(doc) {
                                doc.defaultStyle.fontSize = 8;
                                doc.styles.tableHeader.fontSize = 8;
                            }
                        }
                    ]
                }
            ],

            responsive: false,
            autoWidth: false
        });

        const reportFilterData = function() {
            return {
                company_id: $('#company_filter').val() || '', project_id: $('#project_filter').val() || '',
                department_id: $('#department_filter').val() || '', user_id: $('#user_filter').val() || '',
                from_date: $('#from_date').val() || '', to_date: $('#to_date').val() || '',
                deadline_health: $('#deadline_filter').val() || ''
            };
        };

        function loadOverview() {
            $.get('{{ route('reports.overview') }}', reportFilterData()).done(function(response) {
                const summary = response.summary;
                $('[data-summary="total"]').text(summary.total);
                $('[data-summary="completed"]').text(summary.completed);
                $('[data-summary="overdue"]').text(summary.overdue);
                $('[data-summary="dueSoon"]').text(summary.dueSoon);
                $('[data-summary="hours"]').text((summary.loggedSeconds / 3600).toFixed(1));
                $('#overall_progress').text(summary.progress + '% complete');
                const rows = response.departments.map(function(row) {
                    return '<div class="py-2 border-top"><div class="d-flex justify-content-between mb-1"><span class="fw-semibold">' +
                        $('<div>').text(row.department).html() + '</span><span>' + row.completed + '/' + row.total +
                        ' completed · ' + row.overdue + ' overdue</span></div><div class="progress" style="height:7px"><div class="progress-bar" style="width:' +
                        row.progress + '%"></div></div></div>';
                }).join('');
                $('#department_report_rows').html(rows || '<div class="text-muted py-3">No work matches these filters.</div>');
            }).fail(function(xhr) {
                const message = xhr.responseJSON?.message || 'Unable to load report summary.';
                if (window.toastr) toastr.error(message);
            });
        }

        const originalProjects = $('#project_filter option').clone();
        $('#company_filter').on('change', function() {
            const companyId = $(this).val();
            $('#project_filter').empty().append(originalProjects.filter(function() {
                return !this.value || !companyId || String($(this).data('company-id')) === String(companyId);
            }).clone()).val('').trigger('change.select2');
        });

        $('#apply_report_filters').on('click', function() { table.ajax.reload(); loadOverview(); });
        $('#reset_report_filters').on('click', function() {
            $('#company_filter, #project_filter, #department_filter, #user_filter, #task_filter, #subtask_filter, #deadline_filter').val('').trigger('change.select2');
            $('#from_date, #to_date').val('');
            table.ajax.reload(); loadOverview();
        });
        loadOverview();

        // Project filter change
        $('#project_filter').on('change', function() {
            var project_id = $(this).val() || '';

            $('#task_filter')
                .prop('disabled', !project_id)
                .empty()
                .append('<option value="">All Tasks</option>');

            $('#subtask_filter')
                .prop('disabled', true)
                .empty()
                .append('<option value="">All Subtasks</option>');

            $('#task_filter').select2({
                placeholder: 'All Tasks',
                allowClear: true,
                width: '100%'
            });

            $('#subtask_filter').select2({
                placeholder: 'All Subtasks',
                allowClear: true,
                width: '100%'
            });

            if (project_id) {
                $.ajax({
                    url: '{{ route('reports.tasks') }}',
                    data: {
                        project_id: project_id
                    },
                    success: function(data) {
                        $.each(data, function(i, task) {
                            $('#task_filter').append(
                                '<option value="' + task.id + '">' +
                                    task.title +
                                '</option>'
                            );
                        });

                        $('#task_filter').trigger('change.select2');
                    },
                    error: function() {
                        console.log('Failed to load tasks.');
                    }
                });
            }

        });

        // Task filter change
        $('#task_filter').on('change', function() {
            var task_id = $(this).val() || '';

            $('#subtask_filter')
                .prop('disabled', !task_id)
                .empty()
                .append('<option value="">All Subtasks</option>');

            $('#subtask_filter').select2({
                placeholder: 'All Subtasks',
                allowClear: true,
                width: '100%'
            });

            if (task_id) {
                $.ajax({
                    url: '{{ route('reports.subtasks') }}',
                    data: {
                        task_id: task_id
                    },
                    success: function(data) {
                        $.each(data, function(i, subtask) {
                            $('#subtask_filter').append(
                                '<option value="' + subtask.id + '">' +
                                    subtask.title +
                                '</option>'
                            );
                        });

                        $('#subtask_filter').trigger('change.select2');
                    },
                    error: function() {
                        console.log('Failed to load subtasks.');
                    }
                });
            }

        });

        // Subtask filter change
        // Filters are deliberately applied together to avoid repeated requests while selecting options.
    });
</script>
@endpush
