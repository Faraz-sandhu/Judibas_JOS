@extends('layouts.app')

@push('css-before')
    <link rel="stylesheet" href="{{ asset('assets/css/task.css') }}?v={{ time() }}">
    <style>
        .custom-badge {
            position: relative;
            transition: transform 0.3s ease;
        }

        .custom-badge:hover {
            z-index: 99999 !important;
            transform: scale(1.1);
        }
    </style>
@endpush
@section('content')
    @php
        $checkRole = false;
        $user = Auth::user();
        $AuthId = $user->id;
        if ($user->hasRole('admin') || $user->hasRole('project_manager') || $user->hasRole('team_leader')) {
            $checkRole = true;
        }
    @endphp
    <div class="card p-3">

        @foreach (['completed' => 'Complete', 'in_progress' => 'In Progress', 'pending' => 'Pending'] as $status => $label)
            <div class="{{ $status }} mt-5">
                <div class="row">
                    <div class="controls">
                        <img src="{{ asset('assets/task/down-arrow.png') }}" class="down-arrow" alt="down-arrow">
                        <button
                            class="btn btn-sm btn-{{ $status === 'completed' ? 'success' : ($status === 'in_progress' ? 'info' : 'dark') }}">
                            <img src="{{ asset('assets/task/success-check.png') }}" width="15" height="15"
                                alt="complete-check">
                            <span class="ms-2">{{ $label }}</span>
                        </button>
                        <span class="task-count simple-text">{{ $project->tasks->where('status', $status)->count() }}</span>
                        <button class="btn btn-sm btn-light add_parent_task" data-project-id="{{ $project->id }}"
                            data-status="{{ $status }}">
                            <img src="{{ asset('assets/task/add-icon.png') }}" width="15" height="15"
                                alt="add-task">
                            <span class="ms-2">Add Task</span>
                        </button>
                    </div>
                    <div class="task-list mt-3">
                        <div class="row">
                            <div class="col-md-7 simple-text">Name</div>
                            <div class="col-md-1 simple-text">Assignee</div>
                            <div class="col-md-1 simple-text">Due Date</div>
                            <div class="col-md-1 simple-text">Priority</div>
                            <div class="col-md-1 simple-text">Status</div>
                            {{-- <div class="col-md-1 simple-text">Approval</div> --}}
                        </div>
                        <hr>
                        <div class="task_area"></div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
    @include('dashboard.projects.task-modal')
    @include('dashboard.projects.image-modal')
@endsection
@push('page-scripts')
    <script>
        const allProjects = @json($project);
        const assignUser = @json($project->users);
        const Tasks = @json($project->tasks);
        const checkRole = @json($checkRole);
        const BASE_URL = window.location.origin + '/';
        const toUser = @json($asignee) ?? [];
        const AuthId = @json($AuthId);
        const canChangeTaskStatus = @json(Gate::allows('task-status'));
        let isSubTaskEdit = false;
        let isEdit = false;
        const buttonClass = `${checkRole ? 'assign-remove-btn' : 'no-class'}`;

        // task notification
        window.Echo.private("task-approval-notification." + window.Laravel.user)
            .listen(".TaskApprovalNotification", (data) => {
                console.log(data);
                isEdit = true;
                if (data.project_id != null) {
                    fetchLatestTask(data.project_id, data.status, isEdit);
                    fetchLatestSubTasks(data.project_id, data.task_id, isSubTaskEdit);
                    taskDateUpdate(data.project_id, data.task_id)
                } else {
                    subTaskDateUpdate(data.task.sub_task_id, data.task.task_id, data.task.status)
                }
            });
        window.Echo.private("task-notification." + window.Laravel.user)
            .listen(".TaskNotification", (data) => {
                isEdit = true;
                fetchLatestTask(data.task.project_id, data.task.status, isEdit);
            });
        window.Echo.private("global-update." + window.Laravel.user)
            .listen(".globalUpdate", (data) => {
                if (data.project_id != null) {
                    fetchLatestTask(data.project_id, data.status, isEdit);
                    fetchLatestSubTasks(data.project_id, data.task_id, isSubTaskEdit);
                    taskDateUpdate(data.project_id, data.task_id)
                } else {
                    subTaskDateUpdate(data.sub_task_id, data.task_id, data.status)
                }
            });


        function taskDateUpdate(projectId, taskId) {
            var selector = $('.task-datetime[data-project-id="' + projectId + '"][data-task-id="' + taskId + '"]');
            var dateTime = selector.data('value');
            formatDueDate(dateTime);
        }

        function subTaskDateUpdate(subTaskId, taskId, dueDate) {
            var selector = $('.sub-task-datetime[data-task-id="' + taskId + '"][data-sub-task-id="' + subTaskId + '"]');
            if (selector.length) {
                if (selector.is("img")) {
                    selector.replaceWith(
                        `<span class="badge custom-badge bg-info sub-task-datetime" data-task-id="${taskId}" data-sub-task-id="${subTaskId}" data-value="${dueDate}">${formatDueDate(dueDate)}</span>`
                    );
                } else {
                    selector.text(formatDueDate(dueDate));
                }
            }
        }
        $(document).ready(function() {
            $(".down-arrow").click(function() {
                $(this).toggleClass("rotate");
                $(this).closest(".controls").next(".task-list").stop().slideToggle(300);
            });
            if (Array.isArray(Tasks) && Tasks.length > 0) {
                GetTask(Tasks, Tasks, assignUser, toUser);
            } else {
                console.error("No tasks found or incorrect data format");
            }
            initializeFlatpickr();
        });

        // function initializeFlatpickr() {
        //     $('.task-datetime').flatpickr({
        //         enableTime: true,
        //         dateFormat: "Y-m-d H:i",
        //         onChange: function(selectedDates, dateStr, instance) {
        //             let taskId = $(instance.element).data('task-id');
        //             let status = $(instance.element).data('status');
        //             let projectId = $(instance.element).data('project-id');

        //             if (dateStr) {
        //                 $.ajax({
        //                     url: `${BASE_URL}update-task-due-date`,
        //                     method: 'POST',
        //                     data: {
        //                         task_id: taskId,
        //                         due_date: dateStr,
        //                         _token: $('meta[name="csrf-token"]').attr('content')
        //                     },
        //                     success: function(response) {
        //                         toastr.success(response.message);
        //                         isEdit = false;
        //                         fetchLatestTask(projectId, status, isEdit);
        //                         initializeFlatpickr(); // Reinitialize after update
        //                     },
        //                     error: function(xhr) {
        //                         toastr.error('Error updating due date!');
        //                     }
        //                 });
        //             }
        //         }
        //     });

        //     $('.sub-task-datetime').flatpickr({
        //         enableTime: true,
        //         dateFormat: "Y-m-d H:i",
        //         onChange: function(selectedDates, dateStr, instance) {
        //             let taskId = $(instance.element).data('task-id');
        //             let subTaskId = $(instance.element).data('sub-task-id');
        //             var projectId = $('.task_row').find('.task-datetime').data('project-id');

        //             if (dateStr) {
        //                 $.ajax({
        //                     url: `${BASE_URL}update-sub-task-due-date`,
        //                     method: 'POST',
        //                     data: {
        //                         sub_task_id: subTaskId,
        //                         due_date: dateStr,
        //                         _token: $('meta[name="csrf-token"]').attr('content')
        //                     },
        //                     success: function(response) {
        //                         toastr.success(response.message);
        //                         isSubTaskEdit = false;
        //                         fetchLatestSubTasks(projectId, taskId, isSubTaskEdit);
        //                         initializeFlatpickr(); // Reinitialize after update
        //                     },
        //                     error: function(xhr) {
        //                         toastr.error('Error updating due date!');
        //                     }
        //                 });
        //             }
        //         }
        //     });
        // }

        function initializeFlatpickr() {
            $('.task-datetime').each(function() {
                // Destroy previous instance if exists
                if ($(this).hasClass('flatpickr-input')) {
                    $(this).flatpickr().destroy();
                }

                $(this).flatpickr({
                    enableTime: true,
                    dateFormat: "Y-m-d H:i",
                    time_24hr: true, // Optional: Use 24-hour format
                    defaultHour: "18",
                    onClose: function(selectedDates, dateStr, instance) {
                        let taskId = $(instance.element).data('task-id');
                        let status = $(instance.element).data('status');
                        let projectId = $(instance.element).data('project-id');

                        if (dateStr) {
                            $.ajax({
                                url: `${BASE_URL}update-task-due-date`,
                                method: 'POST',
                                data: {
                                    task_id: taskId,
                                    due_date: dateStr,
                                    _token: $('meta[name="csrf-token"]').attr('content')
                                },
                                success: function(response) {
                                    toastr.success(response.message);
                                    fetchLatestTask(projectId, status, false);
                                },
                                error: function(xhr) {
                                    toastr.error('Error updating due date!');
                                }
                            });
                        }
                    }
                });
            });

            $('.sub-task-datetime').each(function() {
                // Destroy previous instance if exists
                if ($(this).hasClass('flatpickr-input')) {
                    $(this).flatpickr().destroy();
                }

                $(this).flatpickr({
                    enableTime: true,
                    dateFormat: "Y-m-d H:i",
                    time_24hr: true, // Optional: Use 24-hour format
                    onClose: function(selectedDates, dateStr, instance) {
                        let taskId = $(instance.element).data('task-id');
                        let subTaskId = $(instance.element).data('sub-task-id');
                        var projectId = $('.task_row').find('.task-datetime').data('project-id');

                        if (dateStr) {
                            $.ajax({
                                url: `${BASE_URL}update-sub-task-due-date`,
                                method: 'POST',
                                data: {
                                    sub_task_id: subTaskId,
                                    due_date: dateStr,
                                    _token: $('meta[name="csrf-token"]').attr('content')
                                },
                                success: function(response) {
                                    toastr.success(response.message);
                                    fetchLatestSubTasks(projectId, taskId, false);
                                },
                                error: function(xhr) {
                                    toastr.error('Error updating due date!');
                                }
                            });
                        }
                    }
                });
            });
        }


        function formatDueDate(dueDate, endDate) {
            const due = new Date(dueDate);
            const oneDay = 24 * 60 * 60 * 1000;
            const now = new Date();

            // If endDate is provided, check if task was completed on time or late
            if (endDate) {
                const end = new Date(endDate);
                return end <= due ? "Completed" : "Late";
            }

            const workingDaysLeft = getWorkingDays(now, due);

            // If the due date has already passed and no end date is provided, return "Overdue"
            if (due < now) {
                return "Overdue";
            }

            if (workingDaysLeft === 0 && due.toDateString() === now.toDateString()) {
                return getTimeLeft(due);
            } else if (workingDaysLeft === 1) {
                return getTimeLeft(due);
            } else if (workingDaysLeft < 7) {
                return `${workingDaysLeft} days left`;
            } else if (workingDaysLeft <= 14) {
                return "Weekly";
            } else if (workingDaysLeft <= 30) {
                return "Half a month";
            } else if (workingDaysLeft <= 90) {
                return "Monthly";
            } else if (workingDaysLeft <= 180) {
                return "Quarter year";
            } else if (workingDaysLeft <= 365) {
                return "Half a year";
            } else {
                return "Next year";
            }

            function getTimeLeft(due) {
                let diffSeconds = Math.floor((due - now) / 1000);
                let hours = Math.floor(diffSeconds / 3600);
                let minutes = Math.floor((diffSeconds % 3600) / 60);
                let seconds = diffSeconds % 60;
                return `${padZero(hours)}:${padZero(minutes)}:${padZero(seconds)} left`;
            }

            function padZero(num) {
                return num.toString().padStart(2, '0');
            }

            function getWorkingDays(startDate, endDate) {
                let count = 0;
                let current = new Date(startDate);

                while (current < endDate) {
                    let day = current.getDay();
                    if (day !== 0 && day !== 6) {
                        count++;
                    }
                    current.setDate(current.getDate() + 1);
                }
                return count;
            }
        }

        // Function to start live countdown
        function startLiveTimer(elementId, dueDate) {
            function update() {
                document.getElementsByClassName(elementId).innerText = formatDueDate(dueDate);
            }
            update(); // Run immediately
            return setInterval(update, 1000); // Update every second
        }
        // Example Usage
        document.addEventListener("DOMContentLoaded", function() {
            const subtaskId = "real-timer"; // Change this ID based on your actual HTML element
            const dueDate = "2025-03-01T12:30:00"; // Replace with your actual due date value
            startLiveTimer(subtaskId, dueDate);
        });
        const GetTask = (Tasks) => {
            let taskHtml = {
                pending: "",
                in_progress: "",
                completed: ""
            };

            $.each(Tasks, function(index, task) {
                    let status = task.status || 'pending';
                    var TaskCreatedBy = task.created_by;
                    var TaskEditAllow = false;
                    if (TaskCreatedBy == AuthId) {
                        TaskEditAllow = true;
                    }
                    let statusIcon = (status === "completed") ?
                        `<img src="/assets/task/task-check.png" alt="Completed Task" width="18" height="18">` :
                        (status === "in_progress") ?
                        `<input class="form-check-input in_progress mt-0" type="radio" checked />` :
                        `<input class="form-check-input mt-0" type="radio" checked />`;

                    let subTaskHtml = "";

                    if (task.subtasks && task.subtasks.length > 0) {
                        $.each(task.subtasks, function(i, subtask) {
                                var CreatedBy = subtask.created_by;
                                var SubTaskEditAllow = false;
                                if (CreatedBy == AuthId) {
                                    SubTaskEditAllow = true;
                                }
                                let subTaskStatus = subtask.status || 'pending';
                                let statusIcon = (subTaskStatus === "completed") ?
                                    `<img src="/assets/task/task-check.png" alt="Completed Task" width="18" height="18">` :
                                    (subTaskStatus === "in_progress") ?
                                    `<input class="form-check-input in_progress mt-0" type="radio" checked />` :
                                    `<input class="form-check-input mt-0" type="radio" checked />`;
                                subTaskHtml += `
                        <div class="sub_task row_sub_task" data-sub-task-id=${subtask.id}>
                            <div class="row ms-5 align-items-center custom-padding">
                                <div class="col-md-7 d-flex align-items-center gap-2 p-0">
                                    ${statusIcon}
                                    <input type="text" class="create_new_sub_task ${subtask.approval == "rejected" ? 'text-danger' : subtask.approval == "approved" ? 'text-success' : ''}" value="${subtask.title}" placeholder="New Subtask" readonly>
                                    <div class="task_actions me-2">
                                        
                                        ${(checkRole || SubTaskEditAllow) ? `<div class="edit_sub_task task_action" data-sub-task-id="${subtask.id}" data-task-id="${subtask.task_id}">
                                                        <img src="${BASE_URL}assets/task/edit-icon.svg" alt="edit">
                                                    </div>` : ''}
                                        <div class="view_sub_task task_action viewSubTaskModal" data-sub-task-id="${subtask.id}" data-task-id="${subtask.task_id}">
                                            <img src="${BASE_URL}assets/task/view.svg" alt="view">
                                        </div>

                                        ${checkRole ? (subtask.status == "completed" ? `
                                            <div class="col-md-1 subtask-approval-change">
                                                <img src="${BASE_URL}assets/img/approval.svg" alt="approval" width="15" height="15" />
                                                <div class="subtask-approval-list">
                                                    <ul>
                                                        <li data-sub-task-id="${subtask.id}" data-task-id="${subtask.task_id}" data-status="pending">
                                                            Pending
                                                        </li>
                                                        <li data-sub-task-id="${subtask.id}" data-task-id="${subtask.task_id}" data-status="approved">
                                                            Approved
                                                        </li>
                                                        <li data-sub-task-id="${subtask.id}" data-task-id="${subtask.task_id}" data-status="rejected">
                                                            Rejected
                                                        </li>
                                                    </ul>
                                                </div>
                                            </div>` : '') : ''}
                                     </div>
                                </div>
                                <div class="col-md-1 assign-main  vis ps-0 invisible position-relative">
                                    <img id="assign-toggle" data-task-id="${task.id}" src="${BASE_URL}assets/task/assignee.png" alt="assignee" width="15" height="15"  />
                                    <div class="assign-user-list">
                                     ${Array.isArray(toUser) && toUser.length > 0 ? ` 
                                          ${toUser.map((user) => { return ` <li data-task-id="${task.id}" data-user-id="${user.id}"> <img src="${user.profile_img}"
                                    alt="${user.name}"/> <span>${user.name}</span> </li>`; }).join('')} </ul>` : ''}
                                    </div>
                                </div>
                                <div class="col-md-1 p-0">
                            ${checkRole ?
                            (subtask.due_date ?
                            (() => {
                            let dueStatus = formatDueDate(subtask.due_date, subtask.end_time);
                            let badgeClass = "bg-info"; // Default color

                            if (dueStatus.includes("Overdue")) {
                            badgeClass = "bg-warning"; // Red for overdue
                            } else if (dueStatus.includes("Late")) {
                            badgeClass = "bg-danger"; // Yellow for late completion
                            } else if (dueStatus.includes("Completed completed")) {
                            badgeClass = "bg-success"; // Green for on-time completion
                            }

                            return ` < span class = "badge custom-badge ${badgeClass} sub-task-datetime real-timer"
                                data - task - id = "${subtask.task_id}"
                                data - sub - task - id = "${subtask.id}"
                                data - bs - toggle = "tooltip"
                                title = "${dueStatus}"
                                data - value = "${subtask.due_date}" >
                                    $ {
                                        new Date(subtask.due_date).toISOString().split('T')[0]
                                    } <
                                    /span>`;
                        })():
                        `<img src="${BASE_URL}assets/task/date.png" class="sub-task-datetime" alt="due date" width="15" height="15" 
                                data-task-id="${subtask.task_id}" data-sub-task-id="${subtask.id}">`
                ):
                (subtask.due_date ?
                    (() => {
                        let dueStatus = formatDueDate(subtask.due_date, subtask.end_time);
                        let badgeClass = "bg-info"; // Default color

                        if (dueStatus.includes("Overdue")) {
                            badgeClass = "bg-warning"; // Red for overdue
                        } else if (dueStatus.includes("Late")) {
                            badgeClass = "bg-danger"; // Yellow for late completion
                        } else if (dueStatus.includes("Completed on")) {
                            badgeClass = "bg-success"; // Green for on-time completion
                        }

                        return `<span class="badge custom-badge ${badgeClass}" 
                                data-task-id="${subtask.task_id}" 
                                data-sub-task-id="${subtask.id}" 
                                data-value="${subtask.due_date}">
                                ${dueStatus}
                                </span>`;
                    })() :
                    `<img src="${BASE_URL}assets/task/date.png" alt="due date" width="15" height="15" 
                                data-task-id="${subtask.task_id}" data-sub-task-id="${subtask.id}">`
                )
            }


            <
            /div>
            $ {
                checkRole ?
                    `<div class="col-md-1 p-0 sub-task-priority">
                                        <span class="badge custom-badge
                                            ${(subtask.priority === 0) ? 'bg-secondary' :
                                            (subtask.priority === 1) ? 'bg-info' :
                                            (subtask.priority === 2) ? 'bg-warning' :
                                            'bg-danger'}">
                                            ${subtask.priority === 0 ? 'Low' :
                                            subtask.priority === 1 ? 'Normal' :
                                            subtask.priority === 2 ? 'High' : 'Urgent'}
                                        </span>

                                        <div class="sub-task-priority-list">
                                            <ul>
                                                <li data-sub-task-id="${subtask.id}" data-task-id="${subtask.task_id}" data-value="0">Low</li>
                                                <li data-sub-task-id="${subtask.id}" data-task-id="${subtask.task_id}" data-value="1">Normal</li>
                                                <li data-sub-task-id="${subtask.id}" data-task-id="${subtask.task_id}" data-value="2">High</li>
                                                <li data-sub-task-id="${subtask.id}" data-task-id="${subtask.task_id}" data-value="3">Urgent</li>
                                            </ul>
                                        </div>
                                    </div>` :
                    `<div class="col-md-1 p-0">
                                        <span class="badge custom-badge
                                            ${(subtask.priority === 0) ? 'bg-secondary' :
                                            (subtask.priority === 1) ? 'bg-info' :
                                            (subtask.priority === 2) ? 'bg-warning' :
                                            'bg-danger'}">
                                            ${subtask.priority === 0 ? 'Low' :
                                            subtask.priority === 1 ? 'Normal' :
                                            subtask.priority === 2 ? 'High' : 'Urgent'}
                                        </span>
                                    </div>`
            } <
            div class = "col-md-1 sub-task-status-change" >
            <
            span class =
            "badge custom-badge bg-${subtask.status === 'pending' ? 'dark' : subtask.status === 'in_progress' ? 'info' : 'success'}" >
            $ {
                subtask.status === 'pending' ? 'Pending' : subtask.status === 'in_progress' ? 'In Progress' :
                    'Completed'
            } <
            /span>

            <
            div class = "sub-task-status-list" >
            <
            ul >
            <
            li data - sub - task - id = "${subtask.id}"
            data - task - id = "${subtask.task_id}"
            data - status = "pending" > Pending < /li> <
            li data - sub - task - id = "${subtask.id}"
            data - task - id = "${subtask.task_id}"
            data - status = "in_progress" > In Progress < /li> <
            li data - sub - task - id = "${subtask.id}"
            data - task - id = "${subtask.task_id}"
            data - status = "completed" > Completed < /li> <
            /ul> <
            /div> <
            /div> <
            /div> <
            hr >
            <
            /div>`;
            });
        }
        let taskImages = '';

        if (task.images) {
            try {
                let imagesArray = JSON.parse(task.images);
                if (Array.isArray(imagesArray) && imagesArray.length > 0) {
                    taskImages = imagesArray.map(imageUrl =>
                        `<li data-bs-toggle="tooltip" data-popup="tooltip-custom" data-bs-placement="top" class="avatar avatar-xs pull-up" title="image">
                        <img src="${imageUrl}" alt="taskImg" class="rounded-circle task-image-preview">
                        </li>`).join('');
                }
            } catch (e) {
                console.error("Error parsing task images:", e);
            }
        }

        let taskRow = `
                <div class="task_row" data-task-id="${task.id}">
                    <div class="ms-3 row hoverPlace">
                        <div class="col-md-7 d-flex align-items-center gap-2">
                            ${statusIcon}
                               <input type="text" class="create_new_task ${task.approval == 'rejected' ? 'text-danger' : task.approval == 'approved' ? 'text-success' : ''}"
                               value="${task.title}" placeholder="New Task" readonly/>
                               
                            <div class="task_actions">
                                <div class="add_sub_task task_action_btn" data-task-id="${task.id}">
                                    <img src="${BASE_URL}assets/task/add-icon.svg" alt="add" />
                                </div>
                                ${(checkRole || TaskEditAllow) ? `<div class="edit_task task_action" data-task-id="${task.id}" data-status="${status}"  data-project-id="${task.project_id}"> <img src="${BASE_URL}assets/task/edit-icon.svg" alt="edit" />
                                    </div>` : ''}
                                <div class="view_task task_action viewTaskModal" data-task-id="${task.id}" data-status="${status}"  data-project-id="${task.project_id}">
                                    <img src="${BASE_URL}assets/task/view.svg" alt="view" />
                                </div>
                                ${checkRole ? (task.status == "completed" ? `<div class="col-md-1 approval-change">
                                    <img src="${BASE_URL}assets/img/approval.svg" alt="approval" width="15" height="15" />
                                    <div class="approval-list" >
                                        <ul>
                                            <li data-task-id="${task.id}" data-project-id="${task.project_id}" data-status="pending">
                                                Pending
                                            </li>
                                            <li data-task-id="${task.id}" data-project-id="${task.project_id}" data-status="approved">
                                                Approved
                                            </li>
                                            <li data-task-id="${task.id}" data-project-id="${task.project_id}" data-status="rejected">
                                                Rejected
                                            </li>
                                        </ul>
                                    </div>
                                </div>`  : '') : ''}
                            </div>
                            <ul class="list-unstyled users-list m-0 avatar-group d-flex align-items-center"> ${taskImages} </ul>
                        </div>
                        <div class="col-md-1 assign-main position-relative">
                            ${task.users && task.users.length > 0 ? `
                            <ul class="list-unstyled d-flex align-items-center avatar-group mb-0 z-2">
                                ${task.users.map(user => `
                        <li data-bs-toggle="tooltip" data-popup="tooltip-custom" data-bs-placement="top"
                            title="${user.name}" class="avatar avatar-sm pull-up ${buttonClass}" data-user-id="${user.id}" data-task-id="${task.id}">
                            <img class="rounded-circle" src="${user.profile_img}" alt="Avatar">
                        </li> `).join('')}
                            <li class="avatar avatar-sm assign-toggle ${toUser && toUser.length > 0 ? 'visible' : 'invisible'}">
                                <span class="avatar-initial rounded-circle pull-up" data-bs-toggle="tooltip"
                                    data-bs-placement="top" title="assign more">+</span>
                            </li>
                        </ul>
                    ` : `
                            <img id="assign-toggle" class="assign-toggle-two ${toUser && toUser.length > 0 ? 'visible' : 'invisible'}"
                                data-task-id="${task.id}" src="${BASE_URL}assets/task/assignee.png" alt="assignee" width="15" height="15" />
                        `}
                            <!-- Assign User List Always Present (Initially Hidden) -->
                            <div class="assign-user-list" style="display: none;">
                                <ul>
                                    ${toUser.map(user => `
                                    <li data-task-id="${task.id}" data-user-id="${user.id}">
                                        <img src="${user.profile_img}" alt="${user.name}" />
                                        <span>${user.name}</span>
                                    </li>
                                `).join('')}
                                </ul>
                            </div>
                        </div>
                        <div class="col-md-1">
                        ${checkRole ?
                            (task.due_date ?
                                (() => {
                                    let dueStatus = formatDueDate(task.due_date, task.end_time);
                                    let badgeClass = "bg-info"; // Default color
                                    if (dueStatus.includes("Overdue")) {
                                        badgeClass = "bg-warning"; // Red for overdue
                                    } else if (dueStatus.includes("Late")) {
                                        badgeClass = "bg-danger"; // Yellow for late completion
                                    } else if (dueStatus.includes("Completed")) {
                                        badgeClass = "bg-success"; // Green for on-time completion
                                    }

                                    return ` < span class = "badge custom-badge ${badgeClass} task-datetime real-timer"
        data - task - id = "${task.id}"
        data - bs - toggle = "tooltip"
        data - bs - placement = "top"
        title = "${dueStatus}"
        data - project - id = "${task.project_id}"
        data - status = "${task.status}"
        data - value = "${task.due_date}" >
            $ {
                new Date(task.due_date).toISOString().split('T')[0]
            } <
            /span>`;
    })():
    `<img src="${BASE_URL}assets/task/date.png" class="task-datetime" alt="due date" width="15" height="15" 
                                        data-task-id="${task.id}" data-project-id="${task.project_id}" data-status="${task.status}">`
    ):
    (task.due_date ?
        (() => {
            let dueStatus = formatDueDate(task.due_date, task.end_time);
            let badgeClass = "bg-info"; // Default color

            if (dueStatus.includes("Overdue")) {
                badgeClass = "bg-warning"; // Red for overdue
            } else if (dueStatus.includes("Late")) {
                badgeClass = "bg-danger"; // Yellow for late completion
            } else if (dueStatus.includes("Completed")) {
                badgeClass = "bg-success"; // Green for on-time completion
            }

            return `<span class="badge custom-badge ${badgeClass}" 
                            data-task-id="${task.id}"
                            data-bs-toggle="tooltip"
                            data-bs-placement="top"
                            title="${dueStatus}"
                            data-project-id="${task.project_id}" 
                            data-status="${task.status}" 
                            data-value="${task.due_date}">
                            ${new Date(task.due_date).toISOString().split('T')[0]}
                            </span>`;
        })() :
        `<img src="${BASE_URL}assets/task/date.png" alt="due date" width="15" height="15" 
                            data-task-id="${task.id}" data-project-id="${task.project_id}" data-status="${task.status}">`
    )

    } <
    /div>
    $ {
        checkRole ?
            `<div class="col-md-1 task-priority">
                            <span class="badge custom-badge
                                ${(task.priority === 0) ? 'bg-secondary' :
                                (task.priority === 1) ? 'bg-info' :
                                (task.priority === 2) ? 'bg-warning' :
                                'bg-danger'}">
                                ${task.priority === 0 ? 'Low' :
                                task.priority === 1 ? 'Normal' :
                                task.priority === 2 ? 'High' : 'Urgent'}
                            </span>

                              <div class="task-priority-list">
                            <ul>
                                <li data-task-id="${task.id}" data-project-id="${task.project_id}" data-value="0">Low</li>
                                <li data-task-id="${task.id}" data-project-id="${task.project_id}" data-value="1">Normal</li>
                                <li data-task-id="${task.id}" data-project-id="${task.project_id}" data-value="2">High</li>
                                <li data-task-id="${task.id}" data-project-id="${task.project_id}" data-value="3">Urgent</li>
                            </ul>
                                    </div>
                                </div>` :
            `<div class="col-md-1">
                                <span class="badge custom-badge
                                    ${(task.priority === 0) ? 'bg-secondary' :
                                    (task.priority === 1) ? 'bg-info' :
                                    (task.priority === 2) ? 'bg-warning' :
                                    'bg-danger'}">
                                    ${task.priority === 0 ? 'Low' :
                                    task.priority === 1 ? 'Normal' :
                                    task.priority === 2 ? 'High' : 'Urgent'}
                                </span>
                            </div>`
    } <
    div class = "col-md-1 status-change" >
    <
    span class =
    "badge custom-badge bg-${(status === 'pending') ? 'dark' :(status === 'in_progress') ? 'info' : 'success' }" >
    $ {
        status === 'pending' ? 'Pending' : status === 'in_progress' ? 'In Progress' : 'Completed'
    } <
    /span> <
    div class = "status-list" >
    <
    ul >
        <
        li data - task - id = "${task.id}"
    data - project - id = "${task.project_id}"
    data - status = "pending" > Pending < /li> <
        li data - task - id = "${task.id}"
    data - project - id = "${task.project_id}"
    data - status = "in_progress" > In Progress < /li> <
        li data - task - id = "${task.id}"
    data - project - id = "${task.project_id}"
    data - status = "completed" > Completed < /li> <
        /ul> <
        /div> <
        /div>

        <
        /div> <
        hr / >
        <
        div class = "sub_task_container" > $ {
            subTaskHtml
        } < /div> <
        /div>`;

        if (status === "pending") {
            taskHtml.pending += taskRow;
        } else if (status === "in_progress") {
            taskHtml.in_progress += taskRow;
        } else if (status === "completed") {
            taskHtml.completed += taskRow;
        }
        });

        $('.pending .task_area').html(taskHtml.pending);
        $('.in_progress .task_area').html(taskHtml.in_progress);
        $('.completed .task_area').html(taskHtml.completed);
        initializeFlatpickr();
        };


        $(document).on("click", ".assign-main ul.list-unstyled .assign-toggle, .assign-toggle-two", function(event) {
            event.stopPropagation();
            $(".assign-user-list").not($(this).closest(".assign-main").find(".assign-user-list")).hide();
            $(this).closest(".assign-main").find(".assign-user-list").toggle();
        });
        // Close the assign-user-list when clicking outside
        $(document).on("click", function() {
            $(".assign-user-list").hide();
        });
        $(document).on("click", ".assign-user-list li", function(event) {
            event.stopPropagation();
            let userId = $(this).data("user-id");
            let taskId = $(this).data("task-id");
            $.ajax({
                url: '{{ route('tasks.assign-task') }}',
                type: "POST",
                data: {
                    user_id: userId,
                    task_id: taskId,
                    _token: "{{ csrf_token() }}"
                },
                success: function(response) {
                    if (response.success) {
                        $(".assign-user-list").hide();
                        isEdit = true;
                        fetchLatestTask(response.data.project_id, response.data.status, isEdit);
                        toastr.success(response.message);
                    }
                },
                error: function(xhr) {
                    toastr.error(xhr.responseJSON.message);
                },
            });
        })
        $(document).on("click", function(event) {
            if (!$(event.target).closest(".assign-main").length) {
                $(".assign-user-list").hide();
            }
        });



        
        // -----------------------------------Tasks----------------------------------------- //
        $(document).on('keypress', '.create_new_task', function(event) {
            if (event.which === 13) { // Enter key
                event.preventDefault();
                $(this).closest('.task_row').find('.save_task_btn').click(); // Trigger save button click
            }
        });
        $(document).on('click', '.save_task_btn', function() {
            var taskInput = $(this).closest('.task_row').find('.create_new_task');
            var statusInput = $(this).closest('.task_row').find('.status');
            var projectIdInput = $(this).closest('.task_row').find('.project_id');
            var title = taskInput.val().trim();
            var status = statusInput.val().trim();
            var project_id = projectIdInput.val().trim();
            if (title === '' || status === '' || project_id === '') {
                console.error("Validation error: Task ID and Subtask title are required!");
                return;
            }
            $('.save_task_btn').prop('disabled', true);

            $.ajax({
                url: `${BASE_URL}tasks`,
                type: 'POST',
                data: {
                    title: title,
                    status: status,
                    project_id: project_id,
                    _token: $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    $('.save_task_btn').prop('disabled', false);
                    toastr.success(response.message);
                    isEdit = true;
                    fetchLatestTask(project_id, status, isEdit);
                },
                error: function(xhr) {
                    console.error("Error saving subtask:", xhr.responseText);
                }
            });
        });
        // Function to fetch the latest tasks and update the UI
        const fetchLatestTask = (project_id, status) => {
            $.ajax({
                url: `${BASE_URL}get-all-tasks/${project_id}`,
                type: 'GET',
                success: function(response) {
                    var Tasks = response.project.tasks;
                    if (Array.isArray(Tasks) && Tasks.length > 0) {
                        GetTask(Tasks);
                        if (isEdit === true) {
                            let taskArea = $('.add_parent_task[data-project-id="' + project_id +
                                '"][data-status="' + status + '"]').closest('.row').find('.task_area');
                            appendTask(taskArea, project_id, status)
                        }
                    } else {
                        console.error("No tasks found or incorrect data format");
                    }
                },
                error: function(xhr) {
                    console.error("Error fetching tasks:", xhr.responseText);
                }
            });
        };
        $(document).on("click", '.add_parent_task', function() {
            let projectId = $(this).data("project-id");
            let status = $(this).data("status");
            let taskArea = $(this).closest('.row').find('.task_area');
            appendTask(taskArea, projectId, status);
        });
        $(document).on("click", '.edit_task', function() {
            let projectId = $(this).data("project-id");
            let taskId = $(this).data("task-id");
            let status = $(this).data("status");
            let inputField = $(this).closest('.task_row').find('.create_new_task');
            inputField.removeAttr('readonly').focus();
            inputField.off("keydown").on("keydown", function(event) {
                if (event.key === "Enter") {
                    event.preventDefault();
                    let updatedTaskName = $(this).val().trim();

                    if (updatedTaskName !== "") {
                        $.ajax({
                            url: `${BASE_URL}tasks/${taskId}`,
                            type: "PUT",
                            data: {
                                project_id: projectId,
                                status: status,
                                title: updatedTaskName,
                                _token: $('meta[name="csrf-token"]').attr('content')
                            },
                            success: function(response) {
                                toastr.success(response.message);
                                isEdit = false
                                fetchLatestTask(projectId, status, isEdit);
                                inputField.attr('readonly', true);
                            },
                            error: function(response) {
                                toastr.error(response.responseJSON.message);
                            }
                        });
                    } else {
                        toastr.error("Task name cannot be empty.");
                    }
                }
            });
        });
        const appendTask = (taskArea, projectId, status) => {
            let newTaskHtml = `<div class="task_row">
                <div class="ms-3 row">
                    <div class="col-md-7 d-flex align-items-center gap-2">
                        <input type="text" class="create_new_task" placeholder="New Task"/>
                        <input type="hidden" class="project_id" value="${projectId}" readonly/>
                        <input type="hidden" class="status" value="${status}" readonly/>
                    </div>
                    <div class="col-md-2 p-2 row">
                        <div class="col-md-6">
                            <button class="btn btn-sm btn-light cancel_task_btn">Cancel</button>
                        </div>
                        <div class="col-md-6">
                            <button class="btn btn-sm btn-info save_task_btn">Save</button>
                        </div>
                    </div>
                </div>
                <hr/>
            </div>`;
            let newTask = $(newTaskHtml);
            taskArea.append(newTask);
            newTask.find('.create_new_task').focus();
        };
        $(document).on('click', '.cancel_task_btn', function() {
            $(this).closest('.task_row').remove();
        });
        $(document).on("click", ".status-change", function(event) {
            event.stopPropagation();
            if (!canChangeTaskStatus) {
                toastr.warning('You do not have permission to change task status.');
                return;
            }
            let $statusList = $(this).find(".status-list");
            $(".status-list").not($statusList).hide();
            $statusList.toggle();
        });
        $(document).on("click", ".approval-change", function(event) {
            event.stopPropagation();

            let $statusList = $(this).find(".approval-list");
            $(".approval-list").not($statusList).hide();
            $statusList.toggle();
        });
        $(document).on("click", ".approval-list li", function(event) {
            event.stopPropagation();
            let status = $(this).data("status");
            let taskId = $(this).data("task-id");
            let project_id = $(this).data("project-id");
            $.ajax({
                url: '{{ route('tasks.approval-update') }}',
                type: "POST",
                data: {
                    approval: status,
                    task_id: taskId,
                    project_id: project_id,
                    _token: "{{ csrf_token() }}"
                },
                success: function(response) {
                    if (response.success) {
                        $(".approval-list").hide();
                        toastr.success(response.message);
                        isEdit = false;
                        fetchLatestTask(project_id, status, isEdit);
                    }
                },
                error: function(xhr) {
                    toastr.error(xhr.responseJSON.message);
                },
            });
        })
        $(document).on("click", ".status-list li", function(event) {
            event.stopPropagation();
            if (!canChangeTaskStatus) return;
            let status = $(this).data("status");
            let taskId = $(this).data("task-id");
            let project_id = $(this).data("project-id");
            $.ajax({
                url: '{{ route('tasks.status-update') }}',
                type: "POST",
                data: {
                    status: status,
                    task_id: taskId,
                    _token: "{{ csrf_token() }}"
                },
                success: function(response) {
                    if (response.success) {
                        $(".status-list").hide();
                        toastr.success(response.message);
                        isEdit = false;
                        fetchLatestTask(project_id, status, isEdit);
                    }
                },
                error: function(xhr) {
                    toastr.error(xhr.responseJSON.message);
                },
            });
        });
        $(document).on("click", ".subtask-approval-change", function(event) {
            event.stopPropagation();

            let $statusList = $(this).find(".subtask-approval-list");
            $(".subtask-approval-list").not($statusList).hide();
            $statusList.toggle();
        });
        $(document).on("click", ".subtask-approval-list li", function(event) {
            event.stopPropagation();
            let status = $(this).data("status");
            let taskId = $(this).data("task-id");
            let subTaskId = $(this).data("sub-task-id");
            $.ajax({
                url: '{{ route('subtasks.approval-update') }}',
                type: "POST",
                data: {
                    approval: status,
                    task_id: taskId,
                    subtask_id: subTaskId,
                    _token: "{{ csrf_token() }}"
                },
                success: function(response) {
                    if (response.success) {
                        $(".approval-list").hide();
                        toastr.success(response.message);
                        isEdit = false;
                        fetchLatestTask(project_id, status, isEdit);
                    }
                },
                error: function(xhr) {
                    toastr.error(xhr.responseJSON.message);
                },
            });
        })
        $(document).on("click", function(event) {
            if (!$(event.target).closest(".status-change").length) {
                $(".status-list").hide();
            }
        });
        $(document).on("click", function(event) {
            if (!$(event.target).closest(".approval-change").length) {
                $(".approval-list").hide();
            }
        });
        $(document).on("click", function(event) {
            if (!$(event.target).closest(".subtask-approval-change").length) {
                $(".subtask-approval-list").hide();
            }
        });
        $(document).on("click", ".task-priority", function(event) {
            event.stopPropagation();
            let $statusList = $(this).find(".task-priority-list");
            $(".task-priority-list").not($statusList).hide();
            $statusList.toggle();
        });
        $(document).on("click", ".task-priority-list li", function(event) {
            event.stopPropagation();
            let priority = $(this).data("value");
            let taskId = $(this).data("task-id");
            let project_id = $(this).data("project-id");
            $.ajax({
                url: '{{ route('tasks.priority-update') }}',
                type: "POST",
                data: {
                    priority: priority,
                    task_id: taskId,
                    _token: "{{ csrf_token() }}"
                },
                success: function(response) {
                    if (response.success) {
                        $(".status-list").hide();
                        toastr.success(response.message);
                        isEdit = false;
                        fetchLatestTask(project_id, status, isEdit);
                    }
                },
                error: function(xhr) {
                    toastr.error(xhr.responseJSON.message);
                },
            });
        });
        $(document).on("click", ".assign-remove-btn", function() {
            const task_id = $(this).data("task-id");
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
                        url: '/remove-task-assign',
                        data: {
                            task_id: task_id,
                            user_id: user_id,
                            _token: '{{ csrf_token() }}'
                        },
                        success: function(data) {
                            Swal.fire('Removed!', 'Remove the assigned employee',
                                'success');
                            if (response.success) {
                                toastr.success(response.message);
                                isEdit = false;
                                fetchLatestTask(project_id, status, isEdit);
                            }
                        },
                        error: function(error) {
                            Swal.fire('Error!', 'Something went wrong.', 'error');
                            console.log('Error:', error);
                        }
                    });
                }
            });
        });
        $(document).on("click", function(event) {
            if (!$(event.target).closest(".task-priority").length) {
                $(".task-priority-list").hide();
            }
        });
        // -----------------------------Sub Task------------------------------------------- //
        // Handle "Enter" key press inside the subtask input
        $(document).on('keypress', '.create_new_sub_task', function(event) {
            if (event.which === 13) { // Enter key
                event.preventDefault();
                $(this).closest('.sub_task').find('.save_btn').click(); // Trigger save button click
            }
        });
        $(document).on("click", '.add_sub_task', function() {
            let taskId = $(this).data("task-id");
            if (Array.isArray(Tasks) && Tasks.length > 0) {
                appendSubTask(taskId, Tasks);
            } else {
                console.error("No tasks found or incorrect data format");
            }
        });
        $(document).on('click', '.save_btn', function() {
            var subTaskInput = $(this).closest('.sub_task').find('.create_new_sub_task');
            var taskIdInput = $(this).closest('.sub_task').find('.task_id');
            var projectIdInput = $(this).closest('.sub_task').find('.project_id');

            var subTask = subTaskInput.val().trim();
            var taskId = taskIdInput.val().trim();
            var projectId = projectIdInput.val().trim();
            if (subTask === '' || taskId === '') {
                console.error("Validation error: Task ID and Subtask title are required!");
                return;
            }

            $.ajax({
                url: `${BASE_URL}subtasks`,
                type: 'POST',
                data: {
                    task_id: taskId,
                    title: subTask,
                    _token: $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    toastr.success(response.message);
                    isSubTaskEdit = true
                    fetchLatestSubTasks(projectId, taskId, isSubTaskEdit);
                },
                error: function(xhr) {
                    console.error("Error saving subtask:", xhr.responseText);
                }
            });
        });
        // Function to fetch the latest tasks and update the UI
        const fetchLatestSubTasks = (projectId, taskId) => {
            $.ajax({
                url: `${BASE_URL}get-all-tasks/${projectId}`,
                type: 'GET',
                success: function(response) {
                    var Tasks = response.project.tasks;
                    if (Array.isArray(Tasks) && Tasks.length > 0) {
                        GetTask(Tasks);
                        if (isSubTaskEdit === true) {
                            appendSubTask(taskId, Tasks);
                        }
                    } else {
                        console.error("No tasks found or incorrect data format");
                    }
                },
                error: function(xhr) {
                    console.error("Error fetching tasks:", xhr.responseText);
                }
            });
        };
        const appendSubTask = (taskId, Tasks) => {
            let subTaskHtml = `
            <div class="sub_task row_sub_task">
                    <div class="row ms-5 align-items-center">
                        <div class="col-md-7 d-flex align-items-center gap-2 p-0">
                            <input class="form-check-input mt-0" type="radio" checked>
                            <input type="text" class="create_new_sub_task" placeholder="New Subtask">
                            <input type="hidden" class="task_id" value="${taskId}">
                            <input type="hidden" class="project_id" value="${Tasks[0].project_id}">
                        </div>
                        <div class="col-md-2 p-2 row">
                            <div class="col-md-6">
                                <button class="btn btn-sm btn-light cancel_btn
                                ">Cancel</button>
                            </div>
                            <div class="col-md-6">
                                <button class="btn btn-sm btn-info save_btn">Save</button>
                            </div>
                        </div>
                    </div>
                    <hr>
                </div>`
            let newSubTask = $(subTaskHtml);
            $(`.task_row[data-task-id="${taskId}"] .sub_task_container`).append(newSubTask);
            newSubTask.find('.create_new_sub_task').focus();
        };
        $(document).on('click', '.cancel_btn', function() {
            $(this).closest('.sub_task').remove();
        });
        $(document).on("click", '.edit_sub_task', function() {
            let taskId = $(this).data("task-id");
            let subTaskId = $(this).data("sub-task-id");
            let inputField = $(this).closest('.row_sub_task').find('.create_new_sub_task');
            let projectId = $(this).closest('.task_row').find('.edit_task').data('project-id');
            inputField.removeAttr('readonly').focus();
            inputField.off("keydown").on("keydown", function(event) {
                if (event.key === "Enter") {
                    event.preventDefault();
                    let updatedTaskName = $(this).val().trim();

                    if (updatedTaskName !== "") {
                        $.ajax({
                            url: `${BASE_URL}subtasks/${subTaskId}`,
                            type: "PUT",
                            data: {
                                title: updatedTaskName,
                                _token: $('meta[name="csrf-token"]').attr('content')
                            },
                            success: function(response) {
                                toastr.success(response.message);
                                isSubTaskEdit = false
                                fetchLatestSubTasks(projectId, taskId, isSubTaskEdit);
                                inputField.attr('readonly', true);
                            },
                            error: function(response) {
                                toastr.error(response.responseJSON.message);
                            }
                        });
                    } else {
                        toastr.error("Task name cannot be empty.");
                    }
                }
            });
        });
        $(document).on("click", ".sub-task-status-change", function(event) {
            event.stopPropagation();
            let $statusList = $(this).find(".sub-task-status-list");
            $(".sub-task-status-list").not($statusList).hide();
            $statusList.toggle();
        });
        $(document).on("click", ".sub-task-status-list li", function(event) {
            event.stopPropagation();
            let subtaskId = $(this).data("sub-task-id");
            let taskId = $(this).data("task-id");
            let status = $(this).data("status");
            var projectId = $(this).closest('.task_row').find('.edit_task').data('project-id');
            $.ajax({
                url: '{{ route('subtasks.status-update') }}',
                type: "POST",
                data: {
                    status: status,
                    sub_task_id: subtaskId,
                    _token: $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    if (response.success) {
                        $(".sub-task-status-list").hide();
                        toastr.success(response.message);
                        isSubTaskEdit = false
                        fetchLatestSubTasks(projectId, taskId, isSubTaskEdit);
                    }
                },
                error: function(xhr) {
                    toastr.error(xhr.responseJSON.message);
                },
            });
        });
        $(document).on("click", function(event) {
            if (!$(event.target).closest(".status-change").length) {
                $(".status-list").hide();
            }
        });
        $(document).on("click", ".sub-task-priority", function(event) {
            event.stopPropagation();
            let $statusList = $(this).find(".sub-task-priority-list");
            $(".sub-task-priority-list").not($statusList).hide();
            $statusList.toggle();
        });
        $(document).on("click", ".sub-task-priority-list li", function(event) {
            event.stopPropagation();
            let subtaskId = $(this).data("sub-task-id");
            let taskId = $(this).data("task-id");
            let priority = $(this).data("value");
            var projectId = $(this).closest('.task_row').find('.edit_task').data('project-id');
            $.ajax({
                url: '{{ route('subtasks.priority-update') }}',
                type: "POST",
                data: {
                    priority: priority,
                    sub_task_id: subtaskId,
                    _token: $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    if (response.success) {
                        $(".sub-task-status-list").hide();
                        toastr.success(response.message);
                        isSubTaskEdit = false
                        fetchLatestSubTasks(projectId, taskId, isSubTaskEdit);
                    }
                },
                error: function(xhr) {
                    toastr.error(xhr.responseJSON.message);
                },
            });
        });
        $(document).on("click", function(event) {
            if (!$(event.target).closest(".status-change").length) {
                $(".status-list").hide();
            }
        });
        // Description task & Subtask
        $(document).on('click', '.viewTaskModal, .viewSubTaskModal', function() {
            let isMainTask = $(this).hasClass('viewTaskModal');
            let taskId = isMainTask ? $(this).data('task-id') : $(this).data('sub-task-id');
            let url = isMainTask ? `${BASE_URL}tasks/${taskId}` : `${BASE_URL}subtasks/${taskId}`;

            // Clear previous modal data
            $('#exLargeModal #description').val('');
            $('#task_id').remove();
            $('#main_task').remove();

            $.ajax({
                url: url,
                type: 'GET',
                data: {
                    _token: $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    let task = isMainTask ? response.task : response.subtask;

                    $('#exLargeModal').modal('show');
                    $('#taskTitle').text(task.title);
                    $('#exLargeModal .formData').append(`
                <input type="hidden" value="${task.id}" id="task_id" readonly>
                <input type="hidden" value="${isMainTask}" id="main_task" readonly>
            `);
                    $('#exLargeModal .response .status').text(task.status);

                    let dueDate = new Date(task.due_date).toLocaleString('en-US', {
                        year: 'numeric',
                        month: 'long',
                        day: '2-digit',
                        hour: '2-digit',
                        minute: '2-digit',
                        second: '2-digit',
                        hour12: true
                    });
                    $('#exLargeModal .response .due_date').text(dueDate);

                    let priorities = ["Low", "Normal", "High", "Urgent"];
                    $('#exLargeModal .response .priority').text(priorities[task.priority] || "Low");
                    $('#exLargeModal .response .invested_time').text(task.invest_time);
                    $('#exLargeModal .response .stage').text(task.approval);
                    $('#exLargeModal #description').val(task.description);
                    window.tinymce?.get('description')?.setContent(task.description || '');
                    if (task.images) {
                        let imagesArray = JSON.parse(task.images);
                        if (Array.isArray(imagesArray) && imagesArray.length > 0) {
                            let taskImages = imagesArray.map(imageUrl =>
                                `<img src="${imageUrl}" class="task-image-preview rounded-pill me-2" alt="Task Image" width="50" height="50" style="object-fit: cover;" >`
                                ).join('');
                            $('#exLargeModal .task-images').html(taskImages);
                        }
                    }
                },
                error: function(xhr) {
                    console.error("Error fetching task/subtask:", xhr.responseText);
                }
            });
        });
        $(document).on('click', '.submitDescription', function() {
            let formData = new FormData();
            let description = $('#description').val();
            formData.append("task_id", $("#task_id").val().trim());
            formData.append("description", $("#description").val().trim());
            formData.append("_token", "{{ csrf_token() }}");

            let attachmentInput = $("#attachment")[0].files;
            for (let i = 0; i < attachmentInput.length; i++) {
                formData.append("attachments[]", attachmentInput[i]);
            }

            let isMainTask = $('#main_task').val() === "true";
            let url = isMainTask ? `${BASE_URL}tasks-description` : `${BASE_URL}sub-task-description`;

            $.ajax({
                url: url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    let task = isMainTask ? response.task : response.subtask;
                    $('#exLargeModal #description').val(task.description);
                    let priorities = ["Low", "Normal", "High", "Urgent"];
                    $('#exLargeModal .response .priority').text(priorities[task.priority] || "Low");
                    $('#exLargeModal .response .invested_time').text(task.invest_time);
                    $('#exLargeModal .response .stage').text(task.approval);
                    $('#exLargeModal').modal('hide');
                },
                error: function(xhr) {
                    console.error("Error updating description:", xhr.responseText);
                }
            });
        });
    </script>

    <script>
        $(document).on('click', '.task-image-preview', function() {
            let imageUrl = $(this).attr('src'); // Get the image source
            $('#imagePreviewModal img').attr('src', imageUrl); // Set image in modal
            $('#imagePreviewModal').modal('show'); // Show modal
        });
    </script>

     {{-- <script src="{{ asset('assets/vendor/libs/fullcalendar/fullcalendar.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/formvalidation/dist/js/plugins/Bootstrap5.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/formvalidation/dist/js/plugins/AutoFocus.js') }}"></script>
    <script src="{{ asset('assets/js/app-calendar-events.js')}}"></script>
    <script src="{{ asset('assets/js/app-calendar.js')}}"></script> --}}
@endpush
