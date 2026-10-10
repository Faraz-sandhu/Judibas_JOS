@extends('layouts.app')
@section('content')
    <div class="row">
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
                                    <th>Login Time</th>
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

@push('page-scripts')
    <script>
        $(document).ready(function() {
            let employeesTable = $("#employeesTable").DataTable({
                serverSide: false,
                processing: true,
                paging: true,
                responsive: true,
                ajax: {
                    url: "{{ route('pulse.index') }}",
                    type: "GET",
                    dataSrc: function(json) {
                        json.data.forEach(function(item) {
                            item.status = null;
                        });
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
                        },
                    },
                    {
                        data: 'online_history',
                        render: function(data) {
                            return data[0].is_online == 1 ?
                                `<span class="badge bg-label-success text-uppercase">Online</span>` :
                                `<span class="badge bg-label-danger text-uppercase">Offline</span>`;
                        },
                    },
                    {
                        data: "online_history",
                        render: function(data) {
                            return data ? moment(data[0].login_time).format("DD-MM-YYYY h:mm A") :
                                "N/A";
                        },
                    }

                ],
            });

            window.Echo.join("employees.online")
                .here(users => {
                    users.forEach(user => updateUserStatus(user, true));
                })
                .joining(user => {
                    updateUserStatus(user, true);
                })
                .leaving(user => {
                    updateUserStatus(user, false);
                });

            function updateUserStatus(user, isOnline) {
                employeesTable.rows().every(function() {
                    let row = this.data();

                    if (row.id == user.id) {
                        if (row.online_history && row.online_history.length > 0) {
                            row.online_history[0].is_online = isOnline ? 1 : 0;
                            row.online_history[0].login_time = isOnline ? moment().format('DD-MM-YYYY h:mm A') : 'N/A';
                        } else {
                            row.online_history = [{
                                is_online: isOnline ? 1 : 0,
                                login_time: isOnline ? moment().format('DD-MM-YYYY h:mm A') : 'N/A'
                            }];
                        }


                        this.data(row).draw(false);
                    }
                });
            }

        });
    </script>
@endpush
