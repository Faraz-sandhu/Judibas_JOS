@php
    $checkRole = false;
    if (
        @auth()->user()->hasRole('admin') ||
        @auth()->user()->hasRole('project_manager') ||
        @auth()->user()->hasRole('team_leader')
    ) {
        $checkRole = true;
    } else {
        $checkRole = false;
    }
    $isAdmin = false;
    if (@auth()->user()->hasRole('admin')) {
        $isAdmin = true;
    }
@endphp
<script>
    const BASE_URL = window.location.origin + '/';
    let datatable;
    let isEdit = false;
    window.baseUrl = "{{ url('/') }}";
    $(document).ready(function() {
        $('.selectpicker.role-select, .selectpicker.assign-project').selectpicker();
        $('.project-department-select').select2({
            dropdownParent: $('#basicModal'),
            width: '100%',
            placeholder: 'Search and select departments',
            closeOnSelect: false
        });
        datatable = $("#table").DataTable({
            serverSide: false,
            processing: true,
            paging: true,
            responsive: true,
            ajax: {
                url: "{{ route('projects.index') }}",
                type: "GET",
                dataSrc: "data",
                data: function(d) {
                    d.status = $('#statusFilter').val();
                    d.approval = $('#approvalFilter').val();
                },
            },
            columns: [{
                    data: "id",
                },
                {
                    data: "image",
                    render: function(data) {
                        return `<img src="${data}" class="img-fluid" width="50" height="50">`;
                    }
                },
                {
                    data: "name",
                    render: function(data, type, row) {
                        return `
                        <a class="modal-view-btn text-muted" href="javascript:void(0)" data-id="${row.id}"> <i class="fa fa-eye me-1"></i></a>
                         <a href="javascript:void(0)" class="open-chat position-relative " data-project-id="${row.id}">
                        <span class="text-dark ">
                            <i class="fa fa-comments"></i>
                        ${row.unseen_comments_count ? '<span class="position-absolute top-0 start-100 translate-middle badge bg-danger rounded-circle p-1 small comment-unseen-badge" style="font-size: 0.5rem; min-width: 1.0rem; height: 1.0rem;">' + row.unseen_comments_count + '</span>' : ''}
                    </span>
                        </span>
                        </a>
                        <a  class="ms-2" href="${window.baseUrl}/projects/${row.id}?source=projects">${row.name}</a>
                        `
                    }
                },
                {
                    data: "users",
                    render: function(data, type, row) {
                        let html =
                            `<ul class="list-unstyled m-0 avatar-group d-flex align-items-center">`;
                        for (let i = 0; i < data.length; i++) {
                            if (data[i].roles[0].role_key == 'project_manager' || data[i].roles[
                                    0].role_key == 'team_leader') {
                                html += `<li data-bs-toggle="tooltip" data-popup="tooltip-custom" data-bs-placement="top"  data-user-id="${data[i].id}" data-project-id=${row.id} 
                            class="@if ($checkRole) assign-remove-btn @endif avatar avatar-xs pull-up" title="${data[i].name}">
                                    <img src="${data[i].profile_img}" alt="${data[i].name}" class="rounded-circle">
                                </li>`;
                            }
                        }
                        @if ($checkRole)
                            html += `
                        <li class="avatar avatar-sm assign-toggle visible  modal-assign-btn" data-id="${row.id}">
                        <span class="avatar-initial rounded-circle pull-up" data-bs-toggle="tooltip" data-bs-placement="top" title="assign more">+</span>
                        </li>
                        `;
                        @endif
                        html += `</ul>`;
                        return html;
                    }
                },

                {
                    data: null,
                    title: "Tasks",
                    render: function(data, type, row) {
                        let pending = row.pending_tasks_count || 0;
                        let inProgress = row.in_progress_tasks_count || 0;
                        let completed = row.completed_tasks_count || 0;

                        return `<a href="javaScript:void(0)" class="taskview" data-id="${row.id}">
                    <div class="progress" style="height: 20px;">
                        <div class="progress-bar bg-secondary" role="progressbar" style="width: auto; padding: 2px 5px;"
                            aria-valuenow="${pending}" aria-valuemin="0" aria-valuemax="100"
                            data-bs-toggle="tooltip" data-bs-placement="top" data-bs-custom-class="tooltip-secondary"
                            title="Pending Tasks: ${pending}">
                            ${pending}
                        </div>
                        <div class="progress-bar bg-primary" role="progressbar" style="width: auto; padding: 2px 5px;"
                            aria-valuenow="${inProgress}" aria-valuemin="0" aria-valuemax="100"
                            data-bs-toggle="tooltip" data-bs-placement="top" data-bs-custom-class="tooltip-primary"
                            title="In Progress Tasks: ${inProgress}">
                            ${inProgress}
                        </div>
                        <div class="progress-bar bg-success" role="progressbar" style="width: auto; padding: 2px 5px;"
                            aria-valuenow="${completed}" aria-valuemin="0" aria-valuemax="100"
                            data-bs-toggle="tooltip" data-bs-placement="top" data-bs-custom-class="tooltip-success"
                            title="Completed Tasks: ${completed}">
                            ${completed}
                        </div>
                    </div> </a>`;

                    }
                },
                {
                    data: "unassigned_tasks_count",
                    render: function(data, type, row) {
                        return `<a href="javaScript:void(0)"><span class="badge badge-center rounded-pill text-bg-warning" data-id="${row.id}">${data}</span></a>`;
                    }
                },
                {
                    data: "status",
                    render: function(data, type, row) {
                        let badgeClass = "bg-label-secondary"; // Default badge color
                        let statusText = data ? data.toUpperCase() :
                            "UNKNOWN"; // Handle null or undefined values

                        switch (data) {
                            case "pending":
                                badgeClass = "bg-label-dark";
                                break;
                            case "completed":
                                badgeClass = "bg-label-success";
                                break;
                            case "progress":
                                badgeClass = "bg-label-primary";
                                break;
                            case "cancelled":
                                badgeClass = "bg-label-danger";
                                break;
                            case "delivered":
                                badgeClass = "bg-label-info";
                                break;
                            default:
                                badgeClass = "bg-label-warning";
                                break;
                        }

                        const overdueBadge = row.is_overdue
                            ? '<span class="badge bg-label-danger ms-1"><i class="fa fa-clock me-1"></i>OVERDUE</span>'
                            : '';

                        return `
                            <div class="d-flex align-items-center flex-wrap gap-1">
                            <div class="dropdown">
                                <span class="badge ${badgeClass} text-uppercase dropdown-toggle"
                                    data-bs-toggle="dropdown" 
                                    aria-expanded="false"
                                    style="cursor: pointer;">
                                    ${statusText}
                                </span>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item change-status" href="javascript:void(0)" data-id="${row.id}" data-status="pending">Pending</a></li>
                                    <li><a class="dropdown-item change-status" href="javascript:void(0)" data-id="${row.id}" data-status="progress">In Progress</a></li>
                                    <li><a class="dropdown-item change-status" href="javascript:void(0)" data-id="${row.id}" data-status="completed">Completed</a></li>
                                    <li><a class="dropdown-item change-status" href="javascript:void(0)" data-id="${row.id}" data-status="delivered">Delivered</a></li>
                                    <li><a class="dropdown-item change-status" href="javascript:void(0)" data-id="${row.id}" data-status="cancelled">Cancelled</a></li>
                                </ul>
                            </div>
                            ${overdueBadge}
                            </div>
                        `;
                    }
                },
                {
                    data: "approval",
                    render: function(data, type, row) {
                        let iconHtml = '';
                        let textClass = '';

                        switch (row.approval) {
                            case 'pending':
                                iconHtml = '<i class="fa-regular me-1 fa-hourglass-half"></i>';
                                break;
                            case 'approved':
                                iconHtml =
                                    '<i class="fa-regular me-1 fa-circle-check text-success"></i>';
                                textClass = 'text-success';
                                break;
                            case 'rejected':
                                iconHtml =
                                    '<i class="fa-regular me-1 fa-circle-xmark text-danger"></i>';
                                textClass = 'text-danger';
                                break;
                            default:
                                iconHtml = '';
                        }
                        return `
                    <div class="dropdown">
                        <a class="btn btn-sm dropdown-toggle hide-arrow ${textClass}" href="#" role="button"
                        @if($isAdmin) data-bs-toggle="dropdown" @endif
                         " aria-expanded="false">
                            ${iconHtml} ${row.approval}
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item change-approval" data-id="${row.id}" data-status="approved">✅ Approve</a></li>
                            <li><a class="dropdown-item change-approval" data-id="${row.id}" data-status="rejected">❌ Reject</a></li>
                            <li><a class="dropdown-item change-approval" data-id="${row.id}" data-status="pending">⏳ Pending</a></li>
                        </ul>
                    </div>`;
                    }
                },

                {
                    data: null,
                    className: "text-center no-export",
                    orderable: false,
                    render: function(data, type, row) {
                        return `
                        <div class="d-inline-flex align-items-center gap-1">
                            <button type="button" class="btn btn-sm btn-icon btn-label-primary modal-edit-btn" data-id="${row.id}" title="Edit project" aria-label="Edit project"><i class="fa fa-pen-to-square"></i></button>
                            <button type="button" class="btn btn-sm btn-icon btn-label-danger delete-btn" data-id="${row.id}" title="Delete project" aria-label="Delete project"><i class="fa fa-trash"></i></button>
                            <button type="button" class="btn btn-sm btn-icon btn-label-success submit-project" data-id="${row.id}" title="Submit project" aria-label="Submit project"><i class="fa fa-paper-plane"></i></button>
                        </div>
                    `;
                    },
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
            @if ($checkRole)
                dom: '<"card-header d-flex justify-content-between align-items-center"<"head-label text-center"><"dt-action-buttons text-end"B>>' +
                    '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6 d-flex justify-content-end align-items-center" <"#statusFilterWrapper"> f>>' +
                    "tr" +
                    '<"row"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',
                buttons: [{
                    text: '<i class="fa fa-plus-circle"></i> <span class="ms-1">Create</span>',
                    className: "btn btn-primary btn-sm modal-add-btn",
                    attr: {
                        type: 'button',
                        'data-bs-toggle': 'modal',
                        'data-bs-target': '#basicModal'
                    },
                    action: function(e) {
                        e.preventDefault();
                        isEdit = false;
                        resetProjectModal();
                        bootstrap.Modal.getOrCreateInstance(document.getElementById('basicModal')).show();
                    }
                }],
            @endif
        });
        toastr.options = {
            "progressBar": true,
            "closeButton": true,
        }

        $('#statusFilterWrapper').html(`
       <div class="d-flex gap-2">
        <div>
        <select id="statusFilter" class="form-select form-select-sm ms-2">
        <option value="">Status</option>
        <option value="completed">Completed</option>
        <option value="pending">Pending</option>
        <option value="progress">In Progress</option>
        <option value="cancelled">Cancelled</option>
        </select></div>

        <div><select id="approvalFilter" class="form-select form-select-sm ms-2">
        <option value="">Approval</option>
        <option value="approved">Approved</option>
        <option value="pending">Pending</option>
        <option value="rejected">Rejected</option>
        </select></div> 
        </div>
        `);


        @if ($checkRole)
            $(document).on('click', '.taskview', function() {
                const projectId = $(this).data('id');
                $.ajax({
                    url: "{{ route('get-all-tasks', ':id') }}".replace(':id', projectId),
                    type: "GET",
                    success: function(response) {
                        let tasks = response.project.tasks;
                        console.log(tasks);
                        // Destroy and reinitialize DataTable
                        if ($.fn.DataTable.isDataTable('#taskTable')) {
                            $('#taskTable').DataTable().destroy();
                        }
                        $('#taskTable').DataTable({
                            data: tasks,
                            columns: [{
                                    data: "id",
                                    title: "ID"
                                },
                                {
                                    data: "title",
                                    title: "Task Title"
                                },
                                // { data: "start_time", title:"Start Time" },
                                {
                                    data: "invest_time",
                                    title: "Invetestment Time"
                                },
                                {
                                    data: "users",
                                    title: "Assignees",
                                    orderable: false,
                                    render: function(data, type, row) {
                                        if (!Array.isArray(data) || data
                                            .length === 0) {
                                            return `<span class="badge bg-warning">No Assignees</span>`;
                                        }
                                        return `
                                <div class="col-12 assign-main position-relative">
                                    <ul class="list-unstyled d-flex align-items-center avatar-group mb-0 z-2">
                                        ${data.map(user => `
                                            <li data-bs-toggle="tooltip" data-popup="tooltip-custom" 
                                                data-bs-placement="top" title="${user.name}" 
                                                class="avatar avatar-sm pull-up" data-user-id="${user.id}" data-task-id="${row.id}">
                                                <img class="rounded-circle" src="${user.profile_img}" alt="Avatar">
                                            </li>
                                        `).join('')}
                                    </ul>
                                    <!-- Assign User List -->
                                    <div class="assign-user-list" style="display: none;">
                                        <ul>
                                            ${data.map(user => `
                                                <li data-task-id="${row.id}" data-user-id="${user.id}">
                                                    <img src="${user.profile_img}" alt="${user.name}" />
                                                    <span>${user.name}</span>
                                                </li>
                                            `).join('')}
                                        </ul>
                                    </div>
                                </div>
                            `;
                                    }
                                },
                                {
                                    data: "status",
                                    title: "Status",
                                    render: function(data, type, row) {
                                        let badgeClass =
                                            "bg-secondary"; // Default (Pending)

                                        switch (data) {
                                            case "completed":
                                                badgeClass = "bg-success";
                                                break;
                                            case "in_progress":
                                                badgeClass = "bg-primary";
                                                break;
                                            case "pending":
                                                badgeClass = "bg-secondary";
                                                break;
                                            default:
                                                badgeClass =
                                                    "bg-warning"; // Default for unknown statuses
                                                break;
                                        }

                                        return `<span class="badge ${badgeClass} text-uppercase">${data.replace('_', ' ')}</span>`;
                                    }
                                },

                            ]
                        });
                        // Open the modal
                        $('#taskModal').modal('show');
                    },
                    error: function(error) {
                        console.log("Error fetching tasks:", error);
                    }
                });
            });
        @endif


        $('#statusFilter').on('change', function() {
            datatable.ajax.reload(); // Reload the table with new filter
        });

        $('#approvalFilter').on('change', function() {
            datatable.ajax.reload(); // Reload the table with new filter
        });

        function updateTable() {
            datatable.ajax.reload(null, false);
        }

        let selectedProjectID = null;
        $("#table").on("click", ".modal-assign-btn", function() {
            selectedProjectID = $(this).data("id");
            $("#assignModal").modal("show");

        });

        if (window.Echo && window.Laravel?.user) {
            window.Echo.private("project-notification." + window.Laravel.user)
                .listen(".ProjectNotification", () => {
                    updateTable();
                });
        }

        $("#assignModal").on("click", ".btn-primary", function() {
            if (!selectedProjectID) {
                toastr.error("Project ID is missing!");
                return;
            }

            const userIDs = $("#selectTeamLeader").val();

            if (!userIDs || userIDs.length === 0) {
                toastr.error("Please select at least one employee.");
                return;
            }

            const userParam = Array.isArray(userIDs) ? userIDs.join(",") :
                userIDs;

            const url = "{{ route('projects.assign-project') }}"
            $.ajax({
                url: url,
                type: "POST",
                data: {
                    projectId: selectedProjectID,
                    userId: userParam,
                    _token: "{{ csrf_token() }}",
                },
                success: function(response) {
                    $("#assignModal").modal("hide");
                    toastr.success(response.message);
                    updateTable();
                    $("#selectTeamLeader").selectpicker('deselectAll');
                },
                error: function(xhr) {
                    $("#assignModal").modal("hide");
                    toastr.error(xhr.responseJSON.message);
                    updateTable();
                    $("#assignModal input #assignModal select").val("");
                }
            })

        });


        // Event delegation for dynamically generated Edit buttons
        $("#table").on("click", ".modal-edit-btn", function() {
            isEdit = true;
            const id = $(this).data("id");
            const url = "{{ route('projects.edit', ':id') }}".replace(":id", id);
            localStorage.setItem('recordID', id);
            $.ajax({
                url: url,
                type: "GET",
                data: {
                    id: id,
                    _token: "{{ csrf_token() }}",
                },
                success: function(response) {
                    $('#projectModalTitle').text('Edit project');
                    $('#projectModalSubtitle').text('Update the client, schedule, workflow, departments, and project details.');
                    $('#project-department-field').removeClass('d-none');
                    $('#basicModal .submitBtn').text('Save changes');
                    $("#basicModal").modal("show");
                    $("#basicModal #name").val(response.data.name);
                    $("#basicModal #company_name").val(response.data.company?.name || '');
                    $("#basicModal #url").val(response.data.url);
                    $("#basicModal #description").val(response.data.description);
                    $("#basicModal #start_date").val(response.data.start_date);
                    $("#basicModal #end_date").val(response.data.end_date);
                    validateProjectDateRange(false);
                    window.tinymce?.get('description')?.setContent(response.data.description || '');
                    const enabled = (response.workflow_ids || []).map(Number);
                    document.querySelectorAll('.project-workflow').forEach(input => input.checked = enabled.includes(Number(input.value)));
                    $('#department_ids').val((response.department_ids || []).map(String)).trigger('change');
                },
                error: function(xhr) {
                    toastr.error(xhr.responseJSON.message);
                }
            })
        });

        $("#table").on("click", ".modal-view-btn", function() {
            const id = $(this).data("id");
            const url = "{{ route('projects.edit', ':id') }}".replace(":id", id);
            $('#viewProjectOffcanvas #taskTitle').text('');
            $('#viewProjectOffcanvas .status').text('');
            $('#viewProjectOffcanvas .attachment').text('');
            $('#viewProjectOffcanvas .link').text('');
            $('#viewProjectOffcanvas .start_date').text('');
            $('#viewProjectOffcanvas .due_date').text('');
            $('#viewProjectOffcanvas #description').html('');
            $('#viewProjectOffcanvas .task-images').html('');
            $('#viewProjectOffcanvas #project_id').val('');
            $.ajax({
                url: url,
                type: "GET",
                data: {
                    id: id,
                    _token: "{{ csrf_token() }}",
                },
                success: function(response) {
                    const offcanvas = new bootstrap.Offcanvas(document.getElementById(
                        'viewProjectOffcanvas'));
                    $('#viewProjectOffcanvas #taskTitle').text(response.data.name);
                    $('#viewProjectOffcanvas .status').text(response.data.status);
                    $('#viewProjectOffcanvas .start_date').text(response.data.start_date);
                    $('#viewProjectOffcanvas .due_date').text(response.data.end_date);
                    $('#viewProjectOffcanvas .description').text(stripHtml(response.data
                        .description || ''));
                    $('#viewProjectOffcanvas #project_id').val(response.data.id);
                    $('#viewProjectOffcanvas .attachment').html('<a href="' + response.data
                        .attachment +
                        '" target="_blank"><i class="fas fa-file fa-lg"></i></a>');
                    $('#viewProjectOffcanvas .link').html('<a href="' + response.data.url +
                        '" target="_blank">' + response.data.url + '</a>');

                    if (response.data.image) {
                        $('#viewProjectOffcanvas .task-images').html('<img src="' + response
                            .data.image +
                            '" class="img-fluid" width="100%" height="auto">');
                    } else {
                        $('#viewProjectOffcanvas .task-images').html('');
                    }

                    offcanvas.show();
                },
                error: function(xhr) {
                    toastr.error(xhr.responseJSON.message);
                }
            });
        });

        $('#table').on('click', '.submit-project', function() {
            var projectId = $(this).data('id');
            var type = 'project';
            const offcanvas = new bootstrap.Offcanvas(document.getElementById(
                'submissionOffcanvas'));
            url = "{{ route('submissions.getDetails') }}";
            $.ajax({
                url: url,
                type: "get",
                data: {
                    id: projectId,
                    type: type,
                    _token: "{{ csrf_token() }}",
                },
                success: function(response) {
                    if (response.status === true) {
                        $('#submissionOffcanvas #main_id').val(projectId);
                        $('#submissionOffcanvas #type').val(type);
                        $('#submissionOffcanvas #messages').html(response.message);
                        $('#submissionOffcanvas #submissionsCount').html(response.submission_count);
                        offcanvas.show();
                    } else {
                        toastr.error(response.message);
                    }
                },
                error: function(xhr) {
                    toastr.error(xhr.responseJSON.message);
                }
            })
        });

       

    //    $(document).on('click', '.viewSubmissions', function() {
    //     var projectId = $('#main_id').val();
    //     var type = $('#type').val();
    //     // Initialize the modal using Bootstrap's Modal class
    //     const viewSubmissionModal = new bootstrap.Modal(document.getElementById('viewSubmissionModal'), {
    //         backdrop: 'static', // Optional: Prevent closing by clicking outside
    //         keyboard: true // Optional: Allow closing with ESC key
    //     });
    //     $.ajax({
    //         url: "{{ route('submissions.getSubmission') }}",
    //         type: "get",
    //         data: {
    //             id: projectId,
    //             type: type,
    //             _token: "{{ csrf_token() }}",
    //         },
    //         success: function(response) {
    //             if (response.status === true) {
    //                 $('#viewSubmissionModal .modal-body').html(response.data);
    //                 viewSubmissionModal.show();
    //             } else {
    //                 toastr.error(response.message);
    //             }
    //         },
    //         error: function(xhr) {
    //             toastr.error(xhr.responseJSON.message);
    //         }
    //     });
        
    // });

    $(document).on('click', '.viewSubmissions', function() {
    var projectId = $('#main_id').val();
    var type = $('#type').val();
    const viewSubmissionModal = new bootstrap.Modal(document.getElementById('viewSubmissionModal'), {
        backdrop: 'static',
        keyboard: true
    });
    $.ajax({
        url: "{{ route('submissions.getSubmission') }}",
        type: "get",
        data: {
            id: projectId,
            type: type,
            _token: "{{ csrf_token() }}",
        },
        success: function(response) {
            if (response.status === true) {
                $('#viewSubmissionModal .modal-body').html(response.data);
                viewSubmissionModal.show();
                // Check if user is admin to enable status change
                @if(auth()->user()->hasRole('admin'))
                enableStatusChange();
                @endif
            } else {
                toastr.error(response.message);
            }
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON.message);
        }
    });
});

// Function to handle status change
function enableStatusChange() {
    // Target only modal-specific badges
    $('#viewSubmissionModal .submission-status-badge').off('click').css('cursor', 'pointer').on('click', function () {
        var submissionId = $(this).closest('tr').find('td:first').text();
        var currentStatus = $(this).text().toLowerCase();
        var dropdownHtml = `
            <select class="form-select status-dropdown" data-submission-id="${submissionId}">
                <option value="pending" ${currentStatus === 'pending' ? 'selected' : ''}>Pending</option>
                <option value="approved" ${currentStatus === 'approved' ? 'selected' : ''}>Approved</option>
                <option value="rejected" ${currentStatus === 'rejected' ? 'selected' : ''}>Rejected</option>
            </select>
        `;
        $(this).replaceWith(dropdownHtml);
    });

    // Same for dropdowns
    $(document).off('change', '.status-dropdown').on('change', '.status-dropdown', function () {
        var submissionId = $(this).data('submission-id');
        var newStatus = $(this).val();
        var projectId = $('#main_id').val();
        var type = $('#type').val();

        $.ajax({
            url: "{{ route('submissions.updateStatus') }}",
            type: "post",
            data: {
                id: submissionId,
                status: newStatus,
                _token: "{{ csrf_token() }}"
            },
            success: function (response) {
                if (response.status === true) {
                    toastr.success(response.message);

                    $.ajax({
                        url: "{{ route('submissions.getSubmission') }}",
                        type: "get",
                        data: {
                            id: projectId,
                            type: type,
                            _token: "{{ csrf_token() }}"
                        },
                        success: function (tableResponse) {
                            if (tableResponse.status === true) {
                                $('#viewSubmissionModal .modal-body').html(tableResponse.data);

                                @if(auth()->user()->hasRole('admin'))
                                enableStatusChange();
                                @endif
                            } else {
                                toastr.error(tableResponse.message);
                            }
                        },
                        error: function (xhr) {
                            toastr.error(xhr.responseJSON.message);
                        }
                    });
                } else {
                    toastr.error(response.message);
                }
            },
            error: function (xhr) {
                toastr.error(xhr.responseJSON.message);
            }
        });
    });
}


$(document).on('click', '.delete-submission', function() {
    var submissionId = $(this).data('submission-id');
    var projectId = $('#main_id').val();
    var type = $('#type').val();

    Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: "{{ route('submissions.destroy', ':id') }}".replace(':id', submissionId),
                type: "DELETE",
                data: {
                    id: submissionId,
                    _token: "{{ csrf_token() }}"
                },
                success: function(response) {
                    if (response.status === true) {
                        toastr.success(response.message);
                        // Refresh table content without closing modal
                        $.ajax({
                            url: "{{ route('submissions.getSubmission') }}",
                            type: "get",
                            data: {
                                id: projectId,
                                type: type,
                                _token: "{{ csrf_token() }}"
                            },
                            success: function(tableResponse) {
                                if (tableResponse.status === true) {
                                    $('#viewSubmissionModal .modal-body').html(tableResponse.data);
                                    // Re-enable status change for admins
                                    @if(auth()->user()->hasRole('admin'))
                                    enableStatusChange();
                                    @endif
                                } else {
                                    toastr.error(tableResponse.message);
                                }
                            },
                            error: function(xhr) {
                                toastr.error(xhr.responseJSON.message);
                            }
                        });
                    } else {
                        toastr.error(response.message);
                    }
                },
                error: function(xhr) {
                    toastr.error(xhr.responseJSON.message);
                }
            });
        }
    });
});

        $('#submissionform').on('submit', function(e) {
            e.preventDefault();

            // Get form data
            const form = $(this);
            const formData = new FormData(this);
            const offcanvasElement = document.getElementById('submissionOffcanvas');
            const offcanvas = bootstrap.Offcanvas.getOrCreateInstance(offcanvasElement);

            // Disable submit button to prevent multiple submissions
            const submitButton = form.find('button[type="submit"]');
            submitButton.prop('disabled', true);

            // Perform AJAX request
            $.ajax({
                url: "{{ route('submissions.store') }}",
                type: "POST",
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.status === true) {
                        // Show success message
                        toastr.success(response.message ||
                            'Submission created successfully');

                        // Update table (assuming updateTable is defined)
                        if (typeof updateTable === 'function') {
                            updateTable();
                        }

                        // Hide off-canvas modal
                        offcanvas.hide();

                        // Reset form
                        form[0].reset();

                        // Optionally clear file input UI (if using custom file input)
                        form.find('input[type="file"]').val('');
                    } else {
                        toastr.error(response.message || 'Unexpected response from server');
                    }
                },
                error: function(xhr) {
                    // Handle error response
                    let errorMessage = 'An error occurred';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMessage = xhr.responseJSON.message;
                    } else if (xhr.status === 404) {
                        errorMessage = 'Submission endpoint not found (404)';
                    } else if (xhr.status === 419) {
                        errorMessage = 'CSRF token mismatch. Please refresh the page.';
                    }
                    toastr.error(errorMessage);
                },
                complete: function() {
                    // Re-enable submit button
                    submitButton.prop('disabled', false);
                }
            });
        });

        // Ensure modal is cleaned up when hidden
        $('#submissionOffcanvas').on('hidden.bs.offcanvas', function() {
            // Reset form when modal is closed (optional, if not already reset)
            $('#submissionform')[0].reset();
            // Clear any custom file input UI
            $('#submissionform').find('input[type="file"]').val('');
        });

        function stripHtml(html) {
            var div = document.createElement("div");
            div.innerHTML = html;
            return div.textContent || div.innerText || "";
        }

        function resetProjectModal() {
            $('#basicModal').find('input:not([type="checkbox"]), textarea').val('');
            $('#basicModal #department_ids').val(null).trigger('change');
            document.querySelectorAll('#basicModal .project-workflow').forEach(input => {
                input.checked = input.dataset.default === '1';
            });
            $('#projectModalTitle').text('Create project');
            $('#projectModalSubtitle').text('Set up the client, schedule, workflow, and project details.');
            $('#project-department-field').removeClass('d-none');
            $('#basicModal .submitBtn').text('Create project');
            $('#basicModal .is-invalid').removeClass('is-invalid');
            $('#basicModal .invalid-feedback').remove();
            $('#basicModal #end_date').removeAttr('min');
            window.tinymce?.get('description')?.setContent('');
        }

        $(document).off("click.projectCreate", ".modal-add-btn")
            .on("click.projectCreate", ".modal-add-btn", function(e) {
            e.preventDefault();
            isEdit = false;
            resetProjectModal();
            const modalElement = document.getElementById('basicModal');
            bootstrap.Modal.getOrCreateInstance(modalElement).show();
        });
        const projectPageParams = new URLSearchParams(window.location.search);
        if (projectPageParams.get('create') === '1') {
            isEdit = false;
            resetProjectModal();
            const departmentId = projectPageParams.get('department_id');
            if (departmentId) $('#department_ids').val([departmentId]).trigger('change');
            bootstrap.Modal.getOrCreateInstance(document.getElementById('basicModal')).show();
        }
        // Event delegation for dynamically generated Delete buttons
        $("#table").on("click", ".delete-btn", function() {
            const id = $(this).data("id");
            Swal.fire({
                title: 'Are you sure?',
                text: 'You won\'t be able to revert this!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        type: 'DELETE',
                        url: '/projects/' + id,
                        data: {
                            _token: '{{ csrf_token() }}'
                        },
                        success: function(data) {
                            Swal.fire('Deleted!', 'Your record has been deleted.',
                                'success');

                            updateTable();
                        },
                        error: function(error) {
                            Swal.fire('Error!', error.responseJSON.message,
                                'error');
                            console.log('Error:', error);
                        }
                    });
                }
            });
        });
        // Event delegation for dynamically generated Delete buttons
        $("#table").on("click", ".assign-remove-btn", function() {
            const project_id = $(this).data("project-id");
            const user_id = $(this).data("user-id");
            Swal.fire({
                title: 'Are you sure?',
                text: 'You won\'t be able to revert this!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, remove it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        type: 'POST',
                        url: '/remove-assign',
                        data: {
                            project_id: project_id,
                            user_id: user_id,
                            _token: '{{ csrf_token() }}'
                        },
                        success: function(data) {
                            Swal.fire('Removed!', 'Remove the assigned employee',
                                'success');
                            updateTable();
                        },
                        error: function(error) {
                            Swal.fire('Error!', 'Something went wrong.', 'error');
                            console.log('Error:', error);
                        }
                    });
                }
            });
        });

        $(".submitBtn").on("click", function() {
            window.TaskDescriptionEditor?.sync();
            if (isEdit == true) {
                updateProject();
            } else {
                createProject();
            }
        });

        function createProject() {
            let formData = new FormData();
            formData.append("name", $("#name").val().trim());
            formData.append("company_name", $("#company_name").val().trim());
            formData.append("description", $("#description").val().trim());
            formData.append("start_date", $("#start_date").val().trim());
            formData.append("end_date", $("#end_date").val().trim());
            const image = $("#image")[0].files[0];
            const attachment = $("#attachment")[0].files[0];
            if (image) formData.append("image", image);
            if (attachment) formData.append("attachment", attachment);
            formData.append("url", $("#url").val().trim());
            formData.append("_token", "{{ csrf_token() }}");
            document.querySelectorAll('.project-workflow:checked').forEach(input => formData.append('workflow_ids[]', input.value));
            ($('#department_ids').val() || []).forEach(id => formData.append('department_ids[]', id));

            $(".form-control, .form-select").removeClass("is-invalid");
            $(".invalid-feedback").fadeOut(200, function() {
                $(this).remove();
            });

            let hasError = false;
            if ($("#name").val().trim() === "") {
                showError("#name", "Name is required.");
                hasError = true;
            }
            if ($("#start_date").val().trim() === "") {
                showError("#start_date", "Start Date is required.");
                hasError = true;
            }
            if (!validateProjectDateRange()) {
                hasError = true;
            }
            // if ($("#end_date").val().trim() === "") {
            //     showError("#end_date", "End Date is required.");
            //     hasError = true;
            // }

            if (hasError) return;

            $.ajax({
                url: "{{ route('projects.store') }}",
                type: "POST",
                data: formData,
                processData: false, // Prevent jQuery from processing the data
                contentType: false, // Ensure proper content type for FormData
                success: function(response) {
                    $("#basicModal").modal("hide");
                    toastr.success(response.message);
                    updateTable();
                    resetProjectModal();
                },
                error: function(xhr) {
                    const response = xhr.responseJSON || {};
                    if (xhr.status === 422 && response.errors) {
                        let errors = response.errors;
                        $.each(errors, function(key, value) {
                            showError(`#${key}`, value[0]);
                        });
                    } else {
                        toastr.error(response.message || 'Unable to create project.');
                    }
                },
            });
        }


        function updateProject() {
            const RecordId = localStorage.getItem('recordID');
            const name = $("#name").val().trim();
            const company_name = $("#company_name").val().trim();
            const description = $("#description").val().trim();
            const start_date = $("#start_date").val().trim();
            const end_date = $("#end_date").val().trim();
            const _token = "{{ csrf_token() }}";
            const image = $("#image")[0].files[0];
            const attachment = $("#attachment")[0].files[0];
            const project_url = $("#url").val().trim();
            $(".form-control, .form-select").removeClass("is-invalid");
            $(".invalid-feedback").fadeOut(200, function() {
                $(this).remove();
            });

            let hasError = false;
            if (name === "") {
                showError("#name", "Name is required.");
                hasError = true;
            }
            // if (description === "") {
            //     showError("#description", "Description is required.");
            //     hasError = true;
            // }
            if (start_date === "") {
                showError("#start_date", "Start Date is required.");
                hasError = true;
            }
            if (!validateProjectDateRange()) {
                hasError = true;
            }
            // if (end_date === "") {
            //     showError("#end_date", "End Date is required.");
            //     hasError = true;
            // }

            if (hasError) return;

            const url = "{{ route('projects.update', ':id') }}".replace(":id", RecordId);

            let formData = new FormData();
            formData.append("_method", "POST"); // Laravel will handle this as a PUT request
            formData.append("name", name);
            formData.append("company_name", company_name);
            formData.append("description", description);
            formData.append("start_date", start_date);
            formData.append("end_date", end_date);
            formData.append("record_id", RecordId);
            formData.append("url", project_url);
            document.querySelectorAll('.project-workflow:checked').forEach(input => formData.append('workflow_ids[]', input.value));
            ($('#department_ids').val() || []).forEach(id => formData.append('department_ids[]', id));
            formData.append('sync_departments', '1');
            if (image) {
                formData.append("image", image);
            }
            if (attachment) {
                formData.append("attachment", attachment);
            }

            $.ajax({
                url: url,
                type: "POST", // Use POST since FormData does not work with PUT
                data: formData,
                headers: {
                    "X-CSRF-TOKEN": _token
                },
                processData: false, // Important for file uploads
                contentType: false, // Important for file uploads
                success: function(response) {
                    $("#basicModal").modal("hide");
                    toastr.success(response.message);
                    updateTable();
                    resetProjectModal();
                },
                error: function(xhr) {
                    if (xhr.status === 422) {
                        let errors = xhr.responseJSON.errors;
                        $.each(errors, function(key, value) {
                            showError(`#${key}`, value[0]);
                        });
                    } else {
                        toastr.error("Unable to update project.");
                    }
                },
            });
        }


        function showError(selector, message) {
            $(selector).addClass("is-invalid").next(".invalid-feedback").remove();
            $('<div class="invalid-feedback" style="display: none;">' + message + "</div>")
                .insertAfter(selector)
                .fadeIn(300);
        }

        function validateProjectDateRange(showMessage = true) {
            const startDate = $("#start_date").val();
            const endDate = $("#end_date").val();
            $("#end_date").attr("min", startDate || null);

            if (!startDate || !endDate || endDate >= startDate) {
                $("#end_date").removeClass("is-invalid").next(".invalid-feedback").remove();
                return true;
            }

            if (showMessage) {
                showError("#end_date", "End Date must be the same as or later than the Start Date.");
            }
            return false;
        }

        $("#start_date, #end_date").on("change input", function() {
            validateProjectDateRange(true);
        });
        $(".form-control, .form-select").on("input", function() {
            $(this).removeClass("is-invalid");
            $(this)
                .next(".validation-error")
                .fadeOut(200, function() {
                    $(this).remove();
                });
        });
    });

    $(document).on("click", ".change-status", function(e) {
        e.preventDefault();
        let projectId = $(this).data("id");
        let newStatus = $(this).data("status");
        $.ajax({
            url: "{{ route('projects.update-status') }}",
            type: "POST",
            data: {
                _token: $('meta[name="csrf-token"]').attr('content'), // CSRF token
                id: projectId,
                status: newStatus
            },
            success: function(response) {
                if (response.status === true) {
                    toastr.success(response.message);
                    datatable.ajax.reload(null, false);
                } else {
                    toastr.error(response.message);
                }
            },
            error: function() {
                toastr.error("Unable to change status.");
            }
        });
    });
</script>

<script>
    function getFileIcon(filePath) {
        let ext = filePath.split('.').pop().toLowerCase(); // Extract file extension

        switch (ext) {
            case 'pdf':
                return 'fa-file-pdf text-danger';
            case 'doc':
            case 'docx':
                return 'fa-file-word text-primary';
            case 'xls':
            case 'xlsx':
                return 'fa-file-excel text-success';
            case 'ppt':
            case 'pptx':
                return 'fa-file-powerpoint text-warning';
            case 'jpg':
            case 'jpeg':
            case 'png':
                return 'fa-file-image text-info';
            case 'txt':
                return 'fa-file-alt text-secondary';
            default:
                return 'fa-file text-dark'; // Generic file icon
        }
    }

    $(document).on('click', '.change-approval', function(e) {
        e.preventDefault();
        let id = $(this).data('id');
        let status = $(this).data('status');
        Swal.fire({
            title: 'Are you sure?',
            text: `You are about to change the status to "${status}".`,
            icon: 'warning',
            confirmButtonText: 'Yes, change it!',
            showConfirmButton: true,
            reverseButtons: false
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: `/update-project-approval-status/${id}`,
                    method: 'POST',
                    data: {
                        _token: $('meta[name="csrf-token"]').attr('content'),
                        status: status
                    },
                    success: function(response) {
                        toastr.success('Status updated!');
                        $('#table').DataTable().ajax.reload(null, false);
                    },
                    error: function() {
                        toastr.error('Failed to update status.');
                    }
                });
            }
        });
    });

    
</script>
