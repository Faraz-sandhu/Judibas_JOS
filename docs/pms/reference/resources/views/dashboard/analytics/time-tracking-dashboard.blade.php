@extends('layouts.app')
@section('content')
    <div class="row">
        <div class="col-12 col-md-4 my-5">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center pb-3">
                    <h5 class="card-title mb-1">Online Users</h5>
                    <div class="d-flex align-items-center">
                        <span class="badge bg-label-success">Online</span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <h1 class="display-4 mb-0" id="onlineUsersCount">0</h1>

                    </div>
                </div>
            </div>
        </div>
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table" id="employeesTable" style="width: 100%">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Last Seen</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
@push('time-tracking-dashboard-scripts')
    <script>
        let employeesTable = $("#employeesTable").DataTable({
            dom: 'Bfrtip',
            serverSide: false,
            processing: true,
            paging: true,
            responsive: true,
            ajax: {
                url: "{{ route('time-tracking-dashboard') }}",
                type: "GET",
                dataSrc: function(json) {
                    const onlineUsers = json.data.filter(item => item.is_online).length;
                    $("#onlineUsersCount").text(onlineUsers);
                    return json.data;
                },
            },
            columns: [{
                    data: "id"
                },
                {
                    data: "name"
                },
                {
                    data: "email"
                },
                {
                    data: "roles",
                    render: function(data) {
                        return `<span class="badge bg-label-primary text-uppercase">${data[0].role_name}</span>`;
                    }
                },
                {
                    data: "is_online",
                    render: function(data, type, row) {
                        return data ?
                            `<span class="badge bg-label-success">Online</span>` :
                            `<span class="badge bg-label-danger">Offline</span>`;
                    }
                },
                {
                    data: "last_seen_at",
                    render: function(data, type, row) {
                        if (row.is_online) {
                            return '<span class="text-success">Active now</span>';
                        }

                        return data ? moment(data).format("DD-MM-YYYY h:mm A") : 'Never';
                    }
                }
            ],
            buttons: [
            {
                extend: 'collection',
                className: 'btn btn-label-primary dropdown-toggle me-2',
                text: '<i class="icon-base ti tabler-show me-1"></i>Export',
                buttons: [
                    {
                        extend: 'print',
                        text: '<i class="icon-base ti tabler-printer me-1"></i> Print',
                        className: 'dropdown-item',
                        exportOptions: { columns: ':visible' }
                    },
                    {
                        extend: 'csv',
                        text: '<i class="icon-base ti tabler-file me-1"></i> CSV',
                        className: 'dropdown-item',
                        exportOptions: { columns: ':visible' }
                    },
                    {
                        extend: 'excel',
                        text: '<i class="icon-base ti tabler-file me-1"></i> Excel',
                        className: 'dropdown-item',
                        exportOptions: { columns: ':visible' }
                    },
                    {
                        extend: 'pdf',
                        text: '<i class="icon-base ti tabler-file-text me-1"></i> PDF',
                        className: 'dropdown-item',
                        exportOptions: { columns: ':visible' }
                    },
                    {
                        extend: 'copy',
                        text: '<i class="icon-base ti tabler-copy me-1"></i> Copy',
                        className: 'dropdown-item',
                        exportOptions: { columns: ':visible' }
                    }
                ]
            }
        ]
        });

        function updateTable() {
            employeesTable.ajax.reload(null, false);
        }

        // Keep the dashboard current even when real-time broadcasting is unavailable.
        setInterval(updateTable, 60000);
    </script>
@endpush
