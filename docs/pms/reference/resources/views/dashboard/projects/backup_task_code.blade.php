@extends('layouts.app')

@push('css-before')
    <link rel="stylesheet" href="{{ asset('assets/css/task.css') }}?v={{ time() }}">
    <style>
        .subtask-table-wrapper {
            background: #f9f9f9;
            border-left: 3px solid #7367f0;
            border-radius: 0.25rem;
            padding: 1rem;
        }
        .assign-main {
            position: relative;
            display: inline-block;
        }
        .assign-toggle {
            cursor: pointer;
        }
        .add-task-row input,
        .add-task-row select,
        .add-subtask-row input,
        .add-subtask-row select {
            width: 100%;
            padding: 0.25rem;
            font-size: 0.875rem;
            border: 1px solid #ddd;
            border-radius: 0.25rem;
        }
        .user-list-container li {
        padding: 0.5rem 1rem;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .viewTaskModal:hover{
        cursor: pointer;
    }
    .viewSubTaskModal:hover{
        cursor: pointer;
    }
    .user-list-container li:hover {
       
        transform: scale(1.02);
        transition: all 0.2s ease-in-out;
    }
    .user-list-container .role-header {
        font-size: 1rem;
        font-weight: 600;
        padding:0.5rem 1rem;
        background-color: #7367f0;
        margin: 0 2px;
        color: #fff;
        border-radius: 20px;
        text-align: center;
        justify-content: center;

    }
    .user-list-container .no-results {
        padding: 0.5rem 1rem;
        color: #6c757d;
        text-align: center;
    }
        .task-details .task-images img {
            cursor: pointer;
        }
        .task-details .description {
            border: 1px solid #ddd;
            padding: 10px;
            min-height: 100px;
            background: #fff;
            border-radius: 0.25rem;
        }
            .timer-start {
            color: #7367f0;
            font-size: 2rem;
          
            vertical-align: middle;
            }
            .timer-start:hover {
            color: #5a4fc1;
            }
            .timer-pause {
            color: #6c757d;
            font-size: 2rem;
          
            vertical-align: middle;
            }
            .timer-pause:hover {
            color: #5c636a;
            }
            .timer-stop {
            color: #dc3545;
            font-size: 2rem;
          
            vertical-align: middle;
            }
            .timer-stop:hover {
            color: #c82333;
            }
        .timer-start.d-none,
        .timer-pause.d-none,
        .timer-stop.d-none {
            display: none !important;
        }
        .task-title-wrapper {
            display: flex;
            align-items: center;
        }
        .action-buttons {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
 
        .blinking-dot {
        display: inline-block;
        width: 10px;
        height: 10px;
        background-color: red;
        border-radius: 50%;
        margin-left: 6px;
        animation: blink 1s infinite;
        vertical-align: middle;
    }
    @keyframes blink {
        0% { opacity: 1; }
        50% { opacity: 0; }
        100% { opacity: 1; }
    }
            
    </style>
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/fullcalendar/fullcalendar.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/quill/editor.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/formvalidation/dist/css/formValidation.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/css/pages/app-calendar.css') }}">
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
    <div class="row">
        <div class="col-12 ">
            <div class="nav-align-top nav-tabs-shadow">
                <ul class="nav nav-tabs" role="tablist">
                    <li class="nav-item">
                        <button
                            type="button"
                            class="nav-link active"
                            role="tab"
                            data-bs-toggle="tab"
                            data-bs-target="#navs-table"
                            aria-controls="navs-table"
                            aria-selected="true">
                            Table
                        </button>
                    </li>
                    <li class="nav-item">
                        <button
                            type="button"
                            class="nav-link"
                            role="tab"
                            data-bs-toggle="tab"
                            data-bs-target="#navs-calender"
                            aria-controls="navs-calender"
                            aria-selected="false">
                            Calender
                        </button>
                    </li>
                </ul>
                <div class="tab-content">
                    {{-- table content --}}
                  <div class="row">
                    <div class="col-12">
                        
                    </div>
                    <div class="col-12">
                        <div class="tab-pane fade show active" id="navs-table" role="tabpanel">
                            <div class="task-list mt-3">
                                <div class="row">
                                    <div class="table-responsive">
                                        <table class="table table-hover" id="taskTable">
                                            <thead>
                                                <tr>
                                                    <th><input type="checkbox" id="select-all" class="form-check-input"></th>
                                                    <th>Task</th>
                                                    <th>Assignee</th>
                                                    <th>Due Date</th>
                                                    <th>Priority</th>
                                                    <th>Status</th>
                                                    <th>Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <!-- DataTable rows will be populated here -->
                                            </tbody>
                                            <tfoot>
                                                <tr class="add-task-row">
                                                    <td></td>
                                                    <td>
                                                        <input type="text" class="form-control" id="new-task-title" placeholder="Enter task title" />
                                                    </td>
                                                    <td></td>
                                                    <td>
                                                        <input type="text" class="form-control date-input" id="new-task-due-date" placeholder="Select due date" />
                                                    </td>
                                                    <td>
                                                        <select class="form-select" id="new-task-priority">
                                                            <option value="0">Low</option>
                                                            <option value="1" selected>Normal</option>
                                                            <option value="2">High</option>
                                                            <option value="3">Urgent</option>
                                                        </select>
                                                    </td>
                                                    <td>
                                                        <select class="form-select" id="new-task-status">
                                                            <option value="pending" selected>Pending</option>
                                                            <option value="in_progress">In Progress</option>
                                                            <option value="completed">Completed</option>
                                                        </select>
                                                    </td>
                                                    <td class="text-start">
                                                        <button class="btn btn-sm btn-success btn-icon add-task" title="Add Task">
                                                            <i class="fas fa-plus"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>
                                <div class="task_area"></div>
                            </div>
                        </div>
                    </div>
                  </div>

                    {{-- calender content --}}
                    <div class="tab-pane fade" id="navs-calender" role="tabpanel">
                        <!-- Calendar content remains unchanged -->
                        <x-calender-component/>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Assignee Offcanvas Panel -->
    <div class="offcanvas offcanvas-end" tabindex="-1" id="assigneeOffcanvas" aria-labelledby="assigneeOffcanvasLabel">
        <div class="offcanvas-header">
            <h5 id="assigneeOffcanvasLabel" class="offcanvas-title">Assign Team Members</h5>
            <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            <div class="mb-3">
                <input type="text" class="form-control user-search-input" placeholder="Search team members...">
            </div>
            <ul class="user-list-container list-unstyled"></ul>
        </div>
    </div>

    <!-- Task View Offcanvas Panel -->
    <div class="offcanvas offcanvas-end" tabindex="-1" id="viewTaskOffcanvas" aria-labelledby="viewTaskOffcanvasLabel">
        <div class="offcanvas-header bg-primary">
            <h5 id="viewTaskOffcanvasLabel" class="offcanvas-title text-white">Task Details</h5>
            <button type="button" class="btn-close text-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <hr>
        <div class="offcanvas-body task-details">
            <h5 id="taskTitle" class="text-break"></h5>
            <input type="hidden" id="task_id">
            <input type="hidden" id="main_task">
            <input type="hidden" id="sub_task_id">
            <div class="row g-2">
                <div class="col-12">
                    <table class="table table-striped table-hover table-bordered">
                        <tr> <th>Status:</th> <td><span class="status"></span></td></tr>
                        <tr> <th >Due Date:</th> <td><span class="due_date"></span></td></tr>
                        <tr> <th>Priority:</th> <td><span class="priority"></span></td></tr>
                        <tr> <th>Invested Time:</th> <td><span class="invested_time"></span></td></tr>
                        <tr> <th>Stage:</th> <td><span class="stage"></span></td></tr>
                    </table>
                </div>
                @can('description-update')
                <div class="col-12">
                    <label for="attachment">Attachment</label>
                    <input id="attachment" name="attachment" type="file" multiple class="form-control"/>
                </div>
                @endcan
                <div class="col-12">
                    <label for="description" class="form-label mb-2">Description</label>
                    <textarea id="description" rows="8" class="form-control" placeholder="Type here..." @cannot('description-update') readonly @endcannot></textarea>
                </div>
                <div class="col-12">
                    <div class="task-images"> </div>
                </div>
                <div class="col-12">
                    @can('description-update')
                    <div class="text-end"><button type="button" class="btn btn-primary submitDescription">Update</button></div>
                    @endcan
                </div>
            </div>
        </div>
    </div>

    @include('dashboard.projects.task-modal')
    @include('dashboard.projects.image-modal')
     @include('dashboard.projects.submission')
     @include('dashboard.projects.view-submissions-modal')
@endsection

@push('page-scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    // Initialize variables
    const allProjects = @json($project);
    const assignUser = @json($project->users);
    const Tasks = @json($project->tasks);
    const checkRole = @json($checkRole);
    const BASE_URL = window.location.origin + '/';
    const toUser = @json($asignee) ?? [];
    const AuthId = @json($AuthId);
    const timers = {};
    let table;
    let currentRunningTimer = null;
    let currentTaskId = null;
    let currentSubTaskId = null;
    const buttonClass = `${checkRole ? 'assign-remove-btn' : 'no-class'}`;


     // task notification
     window.Echo.private("task-approval-notification." + window.Laravel.user)
            .listen(".TaskApprovalNotification", (data) => {
                // console.log(data);
                isEdit = true;
                if (data.project_id != null) {
                    fetchTasksAndTimers(project_id, null);
                } 
            });
        window.Echo.private("task-notification." + window.Laravel.user)
            .listen(".TaskNotification", (data) => {
                isEdit = true;
                fetchTasksAndTimers(project_id, null);
               
            });
        window.Echo.private("global-update." + window.Laravel.user)
            .listen(".globalUpdate", (data) => {
                if (data.project_id != null) {
                    fetchTasksAndTimers(project_id, null);
                }
        });

         // Fetch tasks and timers
         const fetchTasksAndTimers = (project_id, status, isEdit = false) => {
            $('#taskTable').addClass('loading');
            $.ajax({
                url: `${BASE_URL}get-all-tasks/${project_id}`,
                type: 'GET',
                success: function(response) {
                    if (response.success && Array.isArray(response.project.tasks)) {
                        tableData = response.project.tasks.map(task => {
                            const taskTimer = task.timerLogs?.find(log => log.user_id === AuthId && log.start_time && !log.end_time && !log.subtask_id);
                            return {
                                id: task.id,
                                title: task.title,
                                assignee: task.users && task.users.length ?
                                    `<div class="assign-main vis ps-0 position-relative">
                                        <ul class="list-unstyled users-list m-0 avatar-group d-flex align-items-center">
                                            ${task.users.map(user => `
                                                <li data-bs-toggle="tooltip" data-popup="tooltip-custom"
                                                    data-bs-placement="top" class="avatar avatar-xs pull-up ${buttonClass}" data-task-id="${task.id}" data-user-id="${user.id}" title="${user.name}">
                                                    <img src="${user.profile_img}" alt="${user.name}" class="rounded-circle">
                                                </li>`).join('')}
                                            <li class="avatar avatar-sm assign-toggle ${toUser.length > 0 ? 'visible' : 'invisible'}" data-task-id="${task.id}">
                                                <span class="avatar-initial rounded-circle pull-up" data-bs-toggle="tooltip"
                                                    data-bs-placement="top" title="Assign more">+</span>
                                            </li>
                                        </ul>
                                    </div>` :
                                    `<div class="assign-main vis ps-0 position-relative">
                                        <ul class="list-unstyled users-list m-0 avatar-group d-flex align-items-center">
                                            <li class="avatar avatar-sm assign-toggle ${toUser.length > 0 ? 'visible' : 'invisible'}" data-task-id="${task.id}">
                                                <span class="avatar-initial rounded-circle pull-up" data-bs-toggle="tooltip"
                                                    data-bs-placement="top" title="Assign more">+</span>
                                            </li>
                                        </ul>
                                    </div>`,
                                due_date: task.due_date || null,
                                priority: ['Low', 'Normal', 'High', 'Urgent'][task.priority] || 'Normal',
                                status: task.status || 'pending',
                                isRunning: !!taskTimer,
                                invest_time: task.invest_time || '0 days, 0 hours, 0 minutes',
                                subtasks: task.subtasks.map(sub => {
                                    const subTimer = sub.timerLogs?.find(log => log.user_id === AuthId && log.start_time && !log.end_time);
                                    return {
                                        id: sub.id,
                                        task: sub.title,
                                        priority: ['Low', 'Normal', 'High', 'Urgent'][sub.priority] || 'Normal',
                                        status: sub.status || 'pending',
                                        assignee: sub.users && sub.users.length ?
                                            `<div class="assign-main vis ps-0 position-relative">
                                                <ul class="list-unstyled users-list m-0 avatar-group d-flex align-items-center">
                                                    ${sub.users.map(user => `
                                                        <li data-bs-toggle="tooltip" data-popup="tooltip-custom"
                                                            data-bs-placement="top" class="avatar avatar-xs pull-up ${buttonClass}" data-task-id="${sub.task_id}" data-user-id="${user.id}" data-sub-task-id="${sub.id}" title="${user.name}">
                                                            <img src="${user.profile_img}" alt="${user.name}" class="rounded-circle">
                                                        </li>`).join('')}
                                                    <li class="avatar avatar-sm assign-toggle ${toUser.length > 0 ? 'visible' : 'invisible'}" data-task-id="${sub.task_id}" data-sub-task-id="${sub.id}">
                                                        <span class="avatar-initial rounded-circle pull-up" data-bs-toggle="tooltip"
                                                            data-bs-placement="top" title="Assign more">+</span>
                                                    </li>
                                                </ul>
                                            </div>` :
                                            `<div class="assign-main vis ps-0 position-relative">
                                                <ul class="list-unstyled users-list m-0 avatar-group d-flex align-items-center">
                                                    <li class="avatar avatar-sm assign-toggle ${toUser.length > 0 ? 'visible' : 'invisible'}" data-task-id="${sub.task_id}" data-sub-task-id="${sub.id}">
                                                        <span class="avatar-initial rounded-circle pull-up" data-bs-toggle="tooltip"
                                                            data-bs-placement="top" title="Assign more">+</span>
                                                    </li>
                                                </ul>
                                            </div>`,
                                        due_date: sub.due_date || null,
                                        description: sub.description || '',
                                        task_id: sub.task_id,
                                        isRunning: !!subTimer,
                                        invest_time: sub.invest_time || '0 days, 0 hours, 0 minutes'
                                    };
                                })
                            };
                        });

                        // Update DataTable efficiently
                        table.clear().rows.add(tableData).draw();

                        // Update running timers
                        currentRunningTimer = null;
                        $('.task-title-wrapper .blinking-dot').remove();
                        if (response.runningTimers?.length) {
                            response.runningTimers.forEach(timer => {
                                if (timer.user_id === AuthId) {
                                    currentRunningTimer = {
                                        taskId: timer.task_id,
                                        subTaskId: timer.subtask_id || null,
                                        isSubtask: !!timer.subtask_id,
                                        userId: AuthId
                                    };
                                    updateTimerUI(timer.task_id, timer.subtask_id, true);
                                    // Add blinking dot to main task if subtask timer is running
                                    const $mainTaskModal = $(`[data-task-id="${timer.task_id}"][data-is-subtask="false"]`).closest('tr').find('.viewTaskModal');
                                if ($mainTaskModal.find('.blinking-dot').length === 0) {
                                    const title = timer.subtask_id ? 'A subtask timer is running' : 'Your task timer is running';
                                    $mainTaskModal.append(`<span class="blinking-dot" title="${title}"></span>`);
                                }
                                }
                            });
                        }

                        // toastr.success('Tasks loaded successfully');
                    } else {
                        console.error("No tasks found or incorrect data format");
                        tableData = [];
                        table.clear().draw();
                    }
                },
                error: function(xhr) {
                    console.error("Error fetching tasks and timers:", xhr.responseText);
                    toastr.error("Failed to fetch tasks");
                },
                complete: function() {
                    $('#taskTable').removeClass('loading');
                }
            });
        };



    // Initialize tableData with user-specific timer states
    let tableData = Tasks.map(task => {
        const taskTimer = task.timerLogs?.find(log => log.user_id === AuthId && log.start_time && !log.end_time && !log.subtask_id);
        const subtasks = task.subtasks?.map(sub => {
            const subTimer = sub.timerLogs?.find(log => log.user_id === AuthId && log.start_time && !log.end_time);
            return {
                id: sub.id,
                task: sub.title,
                priority: ['Low', 'Normal', 'High', 'Urgent'][sub.priority] || 'Normal',
                status: sub.status || 'pending',
                assignee: sub.users && sub.users.length ?
                    `<div class="assign-main vis ps-0 position-relative">
                        <ul class="list-unstyled users-list m-0 avatar-group d-flex align-items-center">
                            ${sub.users.map(user => `
                                <li data-bs-toggle="tooltip" data-popup="tooltip-custom"
                                    data-bs-placement="top" class="avatar avatar-xs pull-up ${buttonClass}" data-task-id="${sub.task_id}" data-user-id="${user.id}" title="${user.name}">
                                    <img src="${user.profile_img}" alt="${user.name}" class="rounded-circle">
                                </li>`).join('')}
                            <li class="avatar avatar-sm assign-toggle ${toUser.length > 0 ? 'visible' : 'invisible'}" data-task-id="${sub.task_id}" data-sub-task-id="${sub.id}">
                                <span class="avatar-initial rounded-circle pull-up" data-bs-toggle="tooltip"
                                    data-bs-placement="top" title="Assign more">+</span>
                            </li>
                        </ul>
                    </div>` :
                    `<div class="assign-main vis ps-0 position-relative">
                        <ul class="list-unstyled users-list m-0 avatar-group d-flex align-items-center">
                            <li class="avatar avatar-sm assign-toggle ${toUser.length > 0 ? 'visible' : 'invisible'}" data-task-id="${sub.task_id}" data-sub-task-id="${sub.id}">
                                <span class="avatar-initial rounded-circle pull-up" data-bs-toggle="tooltip"
                                    data-bs-placement="top" title="Assign more">+</span>
                            </li>
                        </ul>
                    </div>`,
                due_date: sub.due_date || null,
                description: sub.description || '',
                task_id: sub.task_id,
                isRunning: !!subTimer,
                invest_time: sub.invest_time || '0 days, 0 hours, 0 minutes'
            };
        }) || [];
        return {
            id: task.id,
            title: task.title,
            assignee: task.users && task.users.length ?
                `<div class="assign-main vis ps-0 position-relative">
                    <ul class="list-unstyled users-list m-0 avatar-group d-flex align-items-center">
                        ${task.users.map(user => `
                            <li data-bs-toggle="tooltip" data-popup="tooltip-custom"
                                data-bs-placement="top" class="avatar avatar-xs pull-up ${buttonClass}" data-task-id="${task.id}" data-user-id="${user.id}" title="${user.name}">
                                <img src="${user.profile_img}" alt="${user.name}" class="rounded-circle">
                            </li>`).join('')}
                        <li class="avatar avatar-sm assign-toggle ${toUser.length > 0 ? 'visible' : 'invisible'}" data-task-id="${task.id}">
                            <span class="avatar-initial rounded-circle pull-up" data-bs-toggle="tooltip"
                                data-bs-placement="top" title="Assign more">+</span>
                        </li>
                    </ul>
                </div>` :
                `<div class="assign-main vis ps-0 position-relative">
                    <ul class="list-unstyled users-list m-0 avatar-group d-flex align-items-center">
                        <li class="avatar avatar-sm assign-toggle ${toUser.length > 0 ? 'visible' : 'invisible'}" data-task-id="${task.id}">
                            <span class="avatar-initial rounded-circle pull-up" data-bs-toggle="tooltip"
                                data-bs-placement="top" title="Assign more">+</span>
                        </li>
                    </ul>
                </div>`,
            due_date: task.due_date || null,
            priority: ['Low', 'Normal', 'High', 'Urgent'][task.priority] || 'Normal',
            status: task.status || 'pending',
            isRunning: !!taskTimer,
            invest_time: task.invest_time || '0 days, 0 hours, 0 minutes',
            subtasks: subtasks
        };
    });

    $(document).ready(function() {
        // Function to clear timer state on logout
        function clearTimerState() {
            currentRunningTimer = null;
            localStorage.removeItem('currentRunningTimer');
        }

        // Update timer UI for a specific task/subtask
        function updateTimerUI(taskId, subTaskId, isRunning) {
            const isSubtask = !!subTaskId;
            const selector = isSubtask
                ? `[data-sub-task-id="${subTaskId}"][data-task-id="${taskId}"]`
                : `[data-task-id="${taskId}"][data-is-subtask="false"]`;

            const $row = $(selector).closest('.task-title-wrapper');
            if (!$row.length) return;

            // Only update buttons for subtasks or main tasks with their own timers
            if (!isSubtask) {
                // For main tasks, only update buttons if the timer is for the task itself
                const mainTaskHasTimer = currentRunningTimer && currentRunningTimer.taskId === taskId && !currentRunningTimer.subTaskId;
                $row.find('.timer-start').toggleClass('d-none', mainTaskHasTimer);
                $row.find('.timer-pause, .timer-stop').toggleClass('d-none', !mainTaskHasTimer);
            } else {
                // For subtasks, update buttons as before
                $row.find('.timer-start').toggleClass('d-none', isRunning);
                $row.find('.timer-pause, .timer-stop').toggleClass('d-none', !isRunning);
            }

            // Handle blinking dot for subtasks
            if (isSubtask && isRunning) {
                const $subTaskModal = $row.find('.viewSubTaskModal');
                if ($subTaskModal.find('.blinking-dot').length === 0) {
                    $subTaskModal.append('<span class="blinking-dot" title="Your subtask timer is running"></span>');
                }
            } else if (isSubtask) {
                $row.find('.blinking-dot').remove();
            }
        }

        // Format subtasks for child rows
        const formatSubtasks = (subtasks, taskId) => {
            if (!Array.isArray(subtasks) || subtasks.length === 0) {
                subtasks = [];
            }
            const statusMap = {
                pending: { label: "Pending", class: "bg-label-warning" },
                in_progress: { label: "In Progress", class: "bg-label-info" },
                completed: { label: "Completed", class: "bg-label-success" }
            };
            const priorityMap = {
                'Low': { title: 'Low', class: 'bg-primary' },
                'Normal': { title: 'Normal', class: 'bg-warning' },
                'High': { title: 'High', class: 'bg-success' },
                'Urgent': { title: 'Urgent', class: 'bg-danger' }
            };
            return `
                <div class="subtask-table-wrapper">
                    <table class="table table-bordered table-sm mb-0" id="subTaskTable-${taskId}">
                        <thead class="table-light">
                            <tr>
                                <th class="text-center" style="width: 40px;"><input type="checkbox" class="form-check-input"></th>
                                <th>Subtask</th>
                                <th>Assignee</th>
                                <th>Due Date</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${subtasks.map(sub => {
                                const status = statusMap[sub.status] || { label: "Unknown", class: "bg-label-secondary" };
                                const priority = priorityMap[sub.priority] || { title: "Unknown", class: "bg-secondary" };
                                const dueDate = sub.due_date ?? "";
                                const isRunning = sub.isRunning;
                                const isCompleted = sub.status === 'completed';
                                return `
                                    <tr>
                                        <td class="text-center"><input type="checkbox" class="form-check-input"></td>
                                        <td>
                                            <div class="task-title-wrapper text-break">
                                                <button class="btn btn-sm btn-icon timer-start ${isRunning || isCompleted ? 'd-none' : ''}"
                                                        data-sub-task-id="${sub.id}" data-task-id="${sub.task_id}" data-is-subtask="true" title="Start Timer">
                                                    <i class="far fa-play-circle"></i>
                                                </button>
                                                <button class="btn btn-sm btn-icon timer-pause ${isRunning ? '' : 'd-none'}"
                                                        data-sub-task-id="${sub.id}" data-task-id="${sub.task_id}" data-is-subtask="true" title="Pause Timer">
                                                   <i class="far fa-pause-circle"></i>
                                                </button>
                                                <button class="btn btn-sm btn-icon timer-stop ${isRunning ? '' : 'd-none'}"
                                                        data-sub-task-id="${sub.id}" data-task-id="${sub.task_id}" data-is-subtask="true" title="Stop Timer">
                                                   <i class="far fa-stop-circle"></i>
                                                </button>
                                                <strong>
                                                    <span class="viewSubTaskModal" data-sub-task-id="${sub.id}" data-task-id="${sub.task_id}">
                                                        ${sub.task ?? "Untitled Subtask"}
                                                        ${isRunning ? `<span class="blinking-dot" title="Your subtask timer is running"></span>` : ''}
                                                    </span>
                                                </strong><br/>
                                                
                                            </td>
                                            <td>${sub.assignee}</td>
                                            <td>
                                                <div class="d-flex align-items-center due-date-wrapper">
                                                    <i class="fas fa-calendar-alt me-2 text-primary ${checkRole ? 'date-icon' : ''} cursor-pointer"></i>
                                                    <span class="due-date-text">${dueDate || '<span class="text-muted">No Date</span>'}</span>
                                                    <input type="text" class="form-control form-control-sm d-none date-input"
                                                        value="${dueDate}" data-sub-task-id="${sub.id}" data-task-id="${sub.task_id}" data-project-id="${allProjects.id}"/>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="subtask-priority-wrapper" data-sub-task-id="${sub.id}" data-task-id="${sub.task_id}">
                                                    <span class="badge rounded-pill ${priority.class} ${checkRole ? 'subtask-priority-label' : ''} cursor-pointer">${priority.title}</span>
                                                    <select data-sub-task-id="${sub.id}" data-task-id="${sub.task_id}" class="form-select form-select-sm d-none subtask-priority-select mt-1">
                                                        <option value="0" ${sub.priority === 'Low' ? 'selected' : ''}>Low</option>
                                                        <option value="1" ${sub.priority === 'Normal' ? 'selected' : ''}>Normal</option>
                                                        <option value="2" ${sub.priority === 'High' ? 'selected' : ''}>High</option>
                                                        <option value="3" ${sub.priority === 'Urgent' ? 'selected' : ''}>Urgent</option>
                                                    </select>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="subtask-status-wrapper" data-sub-task-id="${sub.id}" data-task-id="${sub.task_id}">
                                                    <span class="badge rounded-pill ${status.class} subtask-status-label cursor-pointer">${status.label}</span>
                                                    <select data-sub-task-id="${sub.id}" data-task-id="${sub.task_id}" class="form-select form-select-sm d-none subtask-status-select mt-1">
                                                        <option value="pending" ${sub.status === 'pending' ? 'selected' : ''}>Pending</option>
                                                        <option value="in_progress" ${sub.status === 'in_progress' ? 'selected' : ''}>In Progress</option>
                                                        <option value="completed" ${sub.status === 'completed' ? 'selected' : ''}>Completed</option>
                                                    </select>
                                                </div>
                                            </td>
                                            <td class="text-end">
                                                <div class="action-buttons">
                                                    <div class="dropdown">
                                                        <a href="javascript:;" class="btn btn-sm text-primary btn-icon dropdown-toggle hide-arrow" data-bs-toggle="dropdown">
                                                            <i class="fas fa-ellipsis-v"></i>
                                                        </a>
                                                        <ul class="dropdown-menu dropdown-menu-end">
                                                            <li><a href="javascript:;" class="dropdown-item viewSubTaskModal" data-sub-task-id="${sub.id}" data-task-id="${sub.task_id}">View</a></li>
                                                            <li ${checkRole ? '' : 'style="display:none;"'}><a href="javascript:;" class="dropdown-item text-danger delete-subtask" data-sub-task-id="${sub.id}" data-task-id="${sub.task_id}">Delete</a></li>
                                                            <li><a href="javascript:;" class="dropdown-item submitTaskCanvas" data-sub-task-id="${sub.id}" data-task-id="${sub.task_id}">Submit</a></li>
                                                        </ul>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>`;
                            }).join("")}
                        </tbody>
                        <tfoot>
                            <tr class="add-subtask-row">
                                <td></td>
                                <td>
                                    <input type="text" class="form-control new-subtask-title" data-task-id="${taskId}" placeholder="Enter subtask title" />
                                </td>
                                <td></td>
                                <td>
                                    <input type="text" class="form-control new-subtask-due-date" data-task-id="${taskId}" placeholder="Select due date" />
                                </td>
                                <td>
                                    <select class="form-select new-subtask-priority">
                                        <option value="0">Low</option>
                                        <option value="1" selected>Normal</option>
                                        <option value="2">High</option>
                                        <option value="3">Urgent</option>
                                    </select>
                                </td>
                                <td>
                                    <select class="form-select new-subtask-status">
                                        <option value="pending" selected>Pending</option>
                                        <option value="in_progress">In Progress</option>
                                        <option value="completed">Completed</option>
                                    </select>
                                </td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-primary btn-icon add-subtask" data-task-id="${taskId}" title="Add Subtask">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>`;
        };

        // Initialize Task DataTable
        table = $('#taskTable').DataTable({
            data: tableData,
            paging: false,
            info: false,
            searching: false,
            destroy: true,
            columns: [
                {
                    data: null,
                    orderable: false,
                    className: 'text-center',
                    render: () => '<input type="checkbox" class="form-check-input row-select">'
                },
                {
                    data: null,
                    className: 'task-toggle',
                    render: row => {
                        const isRunning = row.isRunning;
                        const isCompleted = row.status === 'completed';
                        const subtaskCount = row.subtasks?.length || 0;
                        const runningSubtasks = row.subtasks?.filter(sub => sub.isRunning).length || 0;
                        return `
                            <div class="task-title-wrappers">
                               <div class="align-items-center">
                                 <i class="fas fa-chevron-right me-1 cursor-pointer toggle-icon text-primary"
                                   data-task-id="${row.id}"></i>
                                <button class="btn btn-sm m-0 p-0 btn-icon timer-start ${isRunning || isCompleted ? 'd-none' : ''}"
                                        data-task-id="${row.id}" data-is-subtask="false" title="Start Timer">
                                    <i class="far fa-play-circle"></i>
                                </button>
                                <button class="btn btn-sm m-0 p-0 btn-icon timer-pause ${isRunning ? '' : 'd-none'}"
                                        data-task-id="${row.id}" data-is-subtask="false" title="Pause Timer">
                                   <i class="far fa-pause-circle"></i>
                                </button>
                                <button class="btn btn-sm m-0 p-0 btn-icon timer-stop ${isRunning ? '' : 'd-none'}"
                                        data-task-id="${row.id}" data-is-subtask="false" title="Stop Timer">
                                   <i class="far fa-stop-circle"></i>
                                </button>
                                <span class="viewTaskModal text-break " data-task-id="${row.id}">
                                    ${row.title ?? 'Untitled Task'}
                                    ${isRunning || runningSubtasks > 0 ? `<span class="blinking-dot" title="A timer is running"></span>` : ''}
                                    ${runningSubtasks > 0 ? `<span class="ms-1 text-muted">${runningSubtasks}</span>` : ''}
                                    ${subtaskCount > 0 ? `<span class="badge bg-secondary rounded-pill ms-2">${subtaskCount}</span>` : ''}
                                </span></div>

                                ${checkRole ? (row.status == "completed" ? 
                            `<div class=" approval-change text-end justify-content-end ms-2">
                                    <img src="${BASE_URL}assets/img/approval.svg" alt="approval" width="15" height="15" />
                                    <div class="approval-list" >
                                        <ul>
                                            <li data-task-id="${row.id}"  data-project-id="${allProjects.id}" data-status="pending">
                                                Pending
                                            </li>
                                            <li data-task-id="${row.id}"  data-project-id="${allProjects.id}" data-status="approved">
                                                Approved
                                            </li>
                                            <li data-task-id="${row.id}"  data-project-id="${allProjects.id}" data-status="rejected">
                                                Rejected
                                            </li>
                                        </ul>
                                    </div>
                                </div>`  : '') : ''}
                            </div>
                            `;
                    }
                },
                { data: 'assignee' },
                {
                    data: 'due_date',
                    render: function(data, type, full) {
                        const display = data || '<span class="text-muted">No Date</span>';
                        const value = data ? `value="${data}"` : '';
                        return `
                            <div class="d-flex align-items-center due-date-wrapper" data-row="${full.id}">
                                <i class="fas fa-calendar-alt cursor-pointer me-2 text-primary ${checkRole ? 'date-icon' : ''}"></i>
                                <span class="due-date-text">${display}</span>
                                <input type="text" class="form-control form-control-sm d-none date-input"
                                    ${value}
                                    data-task-id="${full.id}"
                                    data-project-id="${full.project_id || ''}" />
                            </div>`;
                    }
                },
                {
                    data: 'priority',
                    render: function(data, type, row) {
                        const priorityMap = {
                            'Low': { title: 'Low', class: 'bg-primary' },
                            'Normal': { title: 'Normal', class: 'bg-warning' },
                            'High': { title: 'High', class: 'bg-success' },
                            'Urgent': { title: 'Urgent', class: 'bg-danger' }
                        };
                        const priority = priorityMap[data] || { title: 'Unknown', class: 'bg-secondary' };
                        return `
                            <div class="priority-wrapper" data-id="${row.id}">
                                <span class="badge rounded-pill ${priority.class} ${checkRole ? 'priority-label' : ''} cursor-pointer">${priority.title}</span>
                                <select data-id="${row.id}" class="form-select form-select-sm d-none priority-select mt-1">
                                    <option value="0" ${data === 'Low' ? 'selected' : ''}>Low</option>
                                    <option value="1" ${data === 'Normal' ? 'selected' : ''}>Normal</option>
                                    <option value="2" ${data === 'High' ? 'selected' : ''}>High</option>
                                    <option value="3" ${data === 'Urgent' ? 'selected' : ''}>Urgent</option>
                                </select>
                            </div>`;
                    }
                },
                {
                    data: 'status',
                    render: function(data, type, row) {
                        const statusMap = {
                            'pending': { title: 'Pending', class: 'bg-label-warning' },
                            'in_progress': { title: 'In Progress', class: 'bg-label-info' },
                            'completed': { title: 'Completed', class: 'bg-label-success' }
                        };
                        const status = statusMap[data] || { title: 'Unknown', class: 'bg-label-secondary' };
                        return `
                            <div class="status-wrapper" data-id="${row.id}">
                                <span class="badge rounded-pill ${status.class} status-label cursor-pointer">${status.title}</span>
                                <select data-id="${row.id}" class="form-select form-select-sm d-none status-select mt-1">
                                    <option value="pending" ${data === 'pending' ? 'selected' : ''}>Pending</option>
                                    <option value="in_progress" ${data === 'in_progress' ? 'selected' : ''}>In Progress</option>
                                    <option value="completed" ${data === 'completed' ? 'selected' : ''}>Completed</option>
                                </select>
                            </div>`;
                    }
                },
                {
                    data: null,
                    orderable: false,
                    render: row => `
                        <div class="action-buttons">
                            <div class="dropdown">
                                <a href="javascript:;" class="btn btn-sm text-primary btn-icon dropdown-toggle hide-arrow" data-bs-toggle="dropdown">
                                    <i class="fas fa-ellipsis-v"></i>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a href="javascript:;" class="dropdown-item viewTaskModal" data-task-id="${row.id}">View</a></li>
                                    <li ${checkRole ? '' : 'style="display:none;"'}><a href="javascript:;" class="dropdown-item text-danger delete-task" data-task-id="${row.id}">Delete</a></li>
                                    <li><a href="javascript:;" class="dropdown-item submitTaskCanvas" data-task-id="${row.id}">Submit</a></li>
                                </ul>
                            </div>
                        </div>`
                }
            ],
            drawCallback: function() {
                // Update timer UI for running timers
                if (currentRunningTimer) {
                    updateTimerUI(
                        currentRunningTimer.taskId,
                        currentRunningTimer.subTaskId,
                        true
                    );
                }
            }
        });

        // Fetch tasks and timers
        const fetchTasksAndTimers = (project_id, status, isEdit = false) => {
            $('#taskTable').addClass('loading');
            $.ajax({
                url: `${BASE_URL}get-all-tasks/${project_id}`,
                type: 'GET',
                success: function(response) {
                    if (response.success && Array.isArray(response.project.tasks)) {
                        tableData = response.project.tasks.map(task => {
                            const taskTimer = task.timerLogs?.find(log => log.user_id === AuthId && log.start_time && !log.end_time && !log.subtask_id);
                            return {
                                id: task.id,
                                title: task.title,
                                assignee: task.users && task.users.length ?
                                    `<div class="assign-main vis ps-0 position-relative">
                                        <ul class="list-unstyled users-list m-0 avatar-group d-flex align-items-center">
                                            ${task.users.map(user => `
                                                <li data-bs-toggle="tooltip" data-popup="tooltip-custom"
                                                    data-bs-placement="top" class="avatar avatar-xs pull-up ${buttonClass}" data-task-id="${task.id}" data-user-id="${user.id}" title="${user.name}">
                                                    <img src="${user.profile_img}" alt="${user.name}" class="rounded-circle">
                                                </li>`).join('')}
                                            <li class="avatar avatar-sm assign-toggle ${toUser.length > 0 ? 'visible' : 'invisible'}" data-task-id="${task.id}">
                                                <span class="avatar-initial rounded-circle pull-up" data-bs-toggle="tooltip"
                                                    data-bs-placement="top" title="Assign more">+</span>
                                            </li>
                                        </ul>
                                    </div>` :
                                    `<div class="assign-main vis ps-0 position-relative">
                                        <ul class="list-unstyled users-list m-0 avatar-group d-flex align-items-center">
                                            <li class="avatar avatar-sm assign-toggle ${toUser.length > 0 ? 'visible' : 'invisible'}" data-task-id="${task.id}">
                                                <span class="avatar-initial rounded-circle pull-up" data-bs-toggle="tooltip"
                                                    data-bs-placement="top" title="Assign more">+</span>
                                            </li>
                                        </ul>
                                    </div>`,
                                due_date: task.due_date || null,
                                priority: ['Low', 'Normal', 'High', 'Urgent'][task.priority] || 'Normal',
                                status: task.status || 'pending',
                                isRunning: !!taskTimer,
                                invest_time: task.invest_time || '0 days, 0 hours, 0 minutes',
                                subtasks: task.subtasks.map(sub => {
                                    const subTimer = sub.timerLogs?.find(log => log.user_id === AuthId && log.start_time && !log.end_time);
                                    return {
                                        id: sub.id,
                                        task: sub.title,
                                        priority: ['Low', 'Normal', 'High', 'Urgent'][sub.priority] || 'Normal',
                                        status: sub.status || 'pending',
                                        assignee: sub.users && sub.users.length ?
                                            `<div class="assign-main vis ps-0 position-relative">
                                                <ul class="list-unstyled users-list m-0 avatar-group d-flex align-items-center">
                                                    ${sub.users.map(user => `
                                                        <li data-bs-toggle="tooltip" data-popup="tooltip-custom"
                                                            data-bs-placement="top" class="avatar avatar-xs pull-up ${buttonClass}" data-task-id="${sub.task_id}" data-user-id="${user.id}" data-sub-task-id="${sub.id}" title="${user.name}">
                                                            <img src="${user.profile_img}" alt="${user.name}" class="rounded-circle">
                                                        </li>`).join('')}
                                                    <li class="avatar avatar-sm assign-toggle ${toUser.length > 0 ? 'visible' : 'invisible'}" data-task-id="${sub.task_id}" data-sub-task-id="${sub.id}">
                                                        <span class="avatar-initial rounded-circle pull-up" data-bs-toggle="tooltip"
                                                            data-bs-placement="top" title="Assign more">+</span>
                                                    </li>
                                                </ul>
                                            </div>` :
                                            `<div class="assign-main vis ps-0 position-relative">
                                                <ul class="list-unstyled users-list m-0 avatar-group d-flex align-items-center">
                                                    <li class="avatar avatar-sm assign-toggle ${toUser.length > 0 ? 'visible' : 'invisible'}" data-task-id="${sub.task_id}" data-sub-task-id="${sub.id}">
                                                        <span class="avatar-initial rounded-circle pull-up" data-bs-toggle="tooltip"
                                                            data-bs-placement="top" title="Assign more">+</span>
                                                    </li>
                                                </ul>
                                            </div>`,
                                        due_date: sub.due_date || null,
                                        description: sub.description || '',
                                        task_id: sub.task_id,
                                        isRunning: !!subTimer,
                                        invest_time: sub.invest_time || '0 days, 0 hours, 0 minutes'
                                    };
                                })
                            };
                        });

                        // Update DataTable efficiently
                        table.clear().rows.add(tableData).draw();

                        // Update running timers
                        currentRunningTimer = null;
                        $('.task-title-wrapper .blinking-dot').remove();
                        if (response.runningTimers?.length) {
                            response.runningTimers.forEach(timer => {
                                if (timer.user_id === AuthId) {
                                    currentRunningTimer = {
                                        taskId: timer.task_id,
                                        subTaskId: timer.subtask_id || null,
                                        isSubtask: !!timer.subtask_id,
                                        userId: AuthId
                                    };
                                    updateTimerUI(timer.task_id, timer.subtask_id, true);
                                    // Add blinking dot to main task if subtask timer is running
                                    const $mainTaskModal = $(`[data-task-id="${timer.task_id}"][data-is-subtask="false"]`).closest('tr').find('.viewTaskModal');
                                if ($mainTaskModal.find('.blinking-dot').length === 0) {
                                    const title = timer.subtask_id ? 'A subtask timer is running' : 'Your task timer is running';
                                    $mainTaskModal.append(`<span class="blinking-dot" title="${title}"></span>`);
                                }
                                }
                            });
                        }

                        // toastr.success('Tasks loaded successfully');
                    } else {
                        console.error("No tasks found or incorrect data format");
                        tableData = [];
                        table.clear().draw();
                    }
                },
                error: function(xhr) {
                    console.error("Error fetching tasks and timers:", xhr.responseText);
                    toastr.error("Failed to fetch tasks");
                },
                complete: function() {
                    $('#taskTable').removeClass('loading');
                }
            });
        };

        // Other functions (unchanged)
        function ucfirst(str) {
            return str.charAt(0).toUpperCase() + str.slice(1);
        }

        function formatInvestTime(timeString) {
            return timeString || '0 days, 0 hours, 0 minutes';
        }

        function initializeDatepickers() {
            console.log('Initializing datepickers');
            $('#new-task-due-date').flatpickr({
                enableTime: true,
                dateFormat: "Y-m-d H:i",
                time_24hr: true,
                defaultHour: 18,
                defaultMinute: 0
            });
        }

        function initializeSubtaskDatepickers(taskId) {
            $(`#subTaskTable-${taskId} .new-subtask-due-date`).flatpickr({
                enableTime: true,
                dateFormat: "Y-m-d H:i",
                time_24hr: true,
                defaultHour: 18,
                defaultMinute: 0
            });
        }

        function startClientTimer(id, isSubtask, startTime) {
            const key = isSubtask ? `sub-${id}` : `task-${id}`;
            if (timers[key] && timers[key].isRunning) return;

            timers[key] = {
                isRunning: true,
                isSubtask: isSubtask,
                startTime: startTime || new Date(),
                intervalId: setInterval(() => {
                    const elapsed = Math.floor((new Date() - timers[key].startTime) / 1000);
                }, 1000)
            };

            currentRunningTimer = {
                taskId: isSubtask ? timers[key].task_id : id,
                subTaskId: isSubtask ? id : null,
                isSubtask: isSubtask,
                userId: AuthId
            };
        }

        function pauseClientTimer(id, isSubtask) {
            const key = isSubtask ? `sub-${id}` : `task-${id}`;
            if (timers[key] && timers[key].isRunning) {
                clearInterval(timers[key].intervalId);
                timers[key].isRunning = false;
            }

            if (currentRunningTimer && currentRunningTimer.taskId === (isSubtask ? timers[key].task_id : id) && currentRunningTimer.subTaskId === (isSubtask ? id : null)) {
                currentRunningTimer = null;
            }
        }

        function stopClientTimer(id, isSubtask) {
            const key = isSubtask ? `sub-${id}` : `task-${id}`;
            if (timers[key]) {
                clearInterval(timers[key].intervalId);
                timers[key].isRunning = false;
                delete timers[key]; // Clean up timer object
            }

            if (currentRunningTimer && currentRunningTimer.taskId === (isSubtask ? timers[key]?.task_id || id : id) && currentRunningTimer.subTaskId === (isSubtask ? id : null)) {
                currentRunningTimer = null;
            }
        }

        // Add new task
        $(document).on('click', '.add-task', function(e) {
            e.preventDefault();
            const title = $('#new-task-title').val().trim();
            const dueDate = $('#new-task-due-date').val();
            const priority = $('#new-task-priority').val();
            const status = $('#new-task-status').val();
            $('.add-task').attr('disabled', true);
            if (!title) {
                toastr.error('Task title is required!');
                $('.add-task').attr('disabled', false);
                return;
            
            }
            $.ajax({
                url: '{{ route('tasks.store') }}',
                type: 'POST',
                data: {
                    title: title,
                    project_id: allProjects.id,
                    status: status,
                    due_date: dueDate || null,
                    priority: priority || 1,
                    _token: $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    if (response.success && response.task?.id) {
                        $('#new-task-title').val('');
                        $('#new-task-due-date').val('');
                        $('#new-task-priority').val('1');
                        $('#new-task-status').val('pending');
                        toastr.success(response.message);
                         $('.add-task').attr('disabled', false);
                        fetchTasksAndTimers(allProjects.id, null);
                       
                    } else {
                        $('.add-task').attr('disabled', false);
                        toastr.error(response.message || 'Failed to add task!');
                        
                    }
                },
                error: function(xhr) {
                    $(this).attr('disabled', false);
                    toastr.error('Error adding task!');
                    console.error('Error:', xhr);
                    
                }
            });
        });

        // Add new subtask
        $(document).on('click', '.add-subtask', function(e) {
            e.preventDefault();
            const taskId = $(this).data('task-id');
            const title = $(this).closest('tr').find('.new-subtask-title').val().trim();
            const dueDate = $(this).closest('tr').find('.new-subtask-due-date').val();
            const priority = $(this).closest('tr').find('.new-subtask-priority').val();
            const status = $(this).closest('tr').find('.new-subtask-status').val();


            if (!title) {
                toastr.error('Subtask title is required!');
                return;
            }

            $.ajax({
                url: '{{ route('subtasks.store') }}',
                type: 'POST',
                data: {
                    title: title,
                    task_id: taskId,
                    due_date: dueDate || null,
                    priority: priority || 1,
                    status: status || 'pending',
                    _token: $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    if (response.success && response.subtask?.id) {
                        const row = table.rows().nodes().to$().find(`.toggle-icon[data-task-id="${taskId}"]`).closest('tr');
                        const rowData = table.row(row).data();

                        const newSubtask = {
                            id: response.subtask.id,
                            task: title,
                            priority: ['Low', 'Normal', 'High', 'Urgent'][priority || 1],
                            status: status || 'pending',
                            assignee: `
                                <div class="assign-main vis ps-0 position-relative">
                                    <ul class="list-unstyled users-list m-0 avatar-group d-flex align-items-center">
                                        <li class="avatar avatar-sm assign-toggle ${toUser.length > 0 ? 'visible' : 'invisible'}" data-task-id="${taskId}" data-sub-task-id="${response.subtask.id}">
                                            <span class="avatar-initial rounded-circle pull-up" data-bs-toggle="tooltip"
                                                data-bs-placement="top" title="Assign more">+</span>
                                        </li>
                                    </ul>
                                </div>`,
                            due_date: dueDate || null,
                            description: '',
                            task_id: taskId,
                            isRunning: false,
                            invest_time: '0 days, 0 hours, 0 minutes'
                        };

                        rowData.subtasks.push(newSubtask);
                        table.row(row).data(rowData).invalidate().draw(false);

                        if (table.row(row).child.isShown()) {
                            table.row(row).child(formatSubtasks(rowData.subtasks, taskId)).show();
                            initializeSubtaskDatepickers(taskId);
                        }

                        $(`#subTaskTable-${taskId} .new-subtask-title`).val('');
                        $(`#subTaskTable-${taskId} .new-subtask-due-date`).val('');
                        $(`#subTaskTable-${taskId} .new-subtask-priority`).val('1');
                        $(`#subTaskTable-${taskId} .new-subtask-status`).val('pending');
                        toastr.success(response.message);
                    } else {
                        toastr.error(response.message || 'Failed to add subtask!');
                    }
                },
                error: function(xhr) {
                    toastr.error('Error adding subtask!');
                    console.error('Error:', xhr);
                }
            });
        });

        // Timer event handlers
        $(document).on('click', '.timer-start', function() {
            const taskId = $(this).data('task-id');
            const isSubtask = $(this).data('is-subtask') === true;
            const subTaskId = isSubtask ? $(this).data('sub-task-id') : null;

            $.ajax({
                url: '{{ route('timer_logs.start') }}',
                type: 'POST',
                data: {
                    task_id: taskId,
                    subtask_id: subTaskId,
                    _token: $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    if (response.success) {
                        currentRunningTimer = {
                            taskId: taskId,
                            subTaskId: subTaskId,
                            isSubtask: isSubtask,
                            userId: AuthId
                        };
                        startClientTimer(isSubtask ? subTaskId : taskId, isSubtask);
                        updateTimerUI(taskId, subTaskId, true);
                        toastr.success(response.message);
                        fetchTasksAndTimers(allProjects.id, null);
                    } else {
                        toastr.error(response.message || 'Failed to start timer!');
                    }
                },
                error: function(xhr) {
                    toastr.error(xhr.responseJSON?.message || 'Another task or subtask is already running. Please pause or stop it first.');
                    console.error('Error starting timer:', xhr);
                }
            });
        });

        $(document).on('click', '.timer-pause', function() {
            const taskId = $(this).data('task-id');
            const isSubtask = $(this).data('is-subtask') === true;
            const subTaskId = isSubtask ? $(this).data('sub-task-id') : null;

            $.ajax({
                url: '{{ route('timer_logs.pause') }}',
                type: 'POST',
                data: {
                    task_id: taskId,
                    subtask_id: subTaskId,
                    _token: $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    if (response.success) {
                        pauseClientTimer(isSubtask ? subTaskId : taskId, isSubtask);
                        currentRunningTimer = null;
                        updateTimerUI(taskId, subTaskId, false);
                        toastr.success(response.message);
                        fetchTasksAndTimers(allProjects.id, null);
                    } else {
                        toastr.error(response.message || 'Failed to pause timer!');
                    }
                },
                error: function(xhr) {
                    toastr.error(xhr.responseJSON?.message || 'Error pausing timer!');
                    console.error('Error pausing timer:', xhr);
                }
            });
        });

        $(document).off('click', '.timer-stop').on('click', '.timer-stop', function(e) {
    e.preventDefault();
    e.stopPropagation();

    const $button = $(this);
    if ($button.hasClass('disabled')) return;

    // Disable button to prevent multiple clicks
    $button.addClass('disabled').prop('disabled', true);

    const taskId = $button.data('task-id');
    const isSubtask = $button.data('is-subtask') === true;
    const subTaskId = isSubtask ? $button.data('sub-task-id') : null;

    console.log('Stopping timer:', { taskId, subTaskId, isSubtask });

    $.ajax({
        url: '{{ route('timer_logs.stop') }}',
        type: 'POST',
        data: {
            task_id: taskId,
            subtask_id: subTaskId,
            _token: $('meta[name="csrf-token"]').attr('content')
        },
        success: function(response) {
            console.log('Success response:', response);
            if (response.success) {
                stopClientTimer(isSubtask ? subTaskId : taskId, isSubtask);
                currentRunningTimer = null;
                updateTimerUI(taskId, subTaskId, false);
                toastr.success(response.message);
                fetchTasksAndTimers(allProjects.id, null);
            } else {
                toastr.error(response.message || 'Failed to stop timer!');
            }
            // Re-enable button
            $button.removeClass('disabled').prop('disabled', false);
        },
        error: function(xhr) {
            console.error('Error response:', xhr.responseText);
            toastr.error(xhr.responseJSON?.message || 'Error stopping timer!');
            // Re-enable button
            $button.removeClass('disabled').prop('disabled', false);
        }
    });
});

        // Event handlers (unchanged)
        $(document).on('click', '#logout', function(e) {
            e.preventDefault();
            clearTimerState();
            window.location.href = '{{ route('logout') }}';
        });

        $('#taskTable tbody').on('click', '.toggle-icon', function() {
            const tr = $(this).closest('tr');
            const row = table.row(tr);
            const icon = $(this);
            if (row.child.isShown()) {
                row.child.hide();
                icon.removeClass('fa-chevron-down').addClass('fa-chevron-right');
            } else {
                row.child(formatSubtasks(row.data().subtasks, row.data().id)).show();
                icon.removeClass('fa-chevron-right').addClass('fa-chevron-down');
                initializeSubtaskDatepickers(row.data().id);
            }
        });

        $('#select-all').on('click', function() {
            $('.row-select').prop('checked', this.checked);
        });

        initializeDatepickers();

        $('#new-task-title').on('keypress', function(e) {
            if (e.which === 13) {
                e.preventDefault();
                $('.add-task').click();
            }
        });

        $(document).on('keypress', '.new-subtask-title', function(e) {
            if (e.which === 13) {
                e.preventDefault();
                $(this).closest('tr').find('.add-subtask').click();
            }
        });

        $(document).on('click', '.assign-toggle', function() {
            currentTaskId = $(this).data('task-id');
            currentSubTaskId = $(this).data('sub-task-id') || null;
            const offcanvas = new bootstrap.Offcanvas(document.getElementById('assigneeOffcanvas'));
            $('.user-list-container').empty();
            const groupedUsers = toUser.reduce((acc, user) => {
                const roleKey = user.roles && user.roles[0] && user.roles[0].role_key ? user.roles[0].role_key : 'others';
                const roleName = user.roles && user.roles[0] && user.roles[0].role_name ? user.roles[0].role_name : 'Others';
                if (!acc[roleKey]) {
                    acc[roleKey] = { roleName, users: [] };
                }
                acc[roleKey].users.push(user);
                return acc;
            }, {});
            Object.keys(groupedUsers).sort().forEach(roleKey => {
                const { roleName, users } = groupedUsers[roleKey];
                $('.user-list-container').append(`
                    <li class="role-header" data-role-key="${roleKey}">${ucfirst(roleKey.replace(/_/g, ' '))}</li>
                `);
                users.forEach(user => {
                    $('.user-list-container').append(`
                        <li data-user-id="${user.id}" data-task-id="${currentTaskId}" ${currentSubTaskId ? `data-sub-task-id="${currentSubTaskId}"` : ''}>
                            <img src="${user.profile_img}" alt="${user.name}" class="rounded-circle" width="30" height="30"/>
                            <span class="fw-bold">${user.name}</span> ${user.designation ? `(${user.designation})` : ''}
                        </li>
                    `);
                });
            });
            offcanvas.show();
        });

        $(document).on('click', '.user-list-container li[data-user-id]', function() {
            const userId = $(this).data('user-id');
            const taskId = $(this).data('task-id');
            const subTaskId = $(this).data('sub-task-id') || null;

            $.ajax({
                url: '{{ route('tasks.assign-task') }}',
                type: 'POST',
                data: {
                    user_id: userId,
                    task_id: taskId,
                    sub_task_id: subTaskId,
                    _token: '{{ csrf_token() }}'
                },
                success: function(response) {
                    if (response.success) {
                        bootstrap.Offcanvas.getInstance(document.getElementById('assigneeOffcanvas')).hide();
                        fetchTasksAndTimers(response.data.project_id, response.data.status, true);
                        toastr.success(response.message);
                    }
                },
                error: function(xhr) {
                    toastr.error(xhr.responseJSON?.message || 'Error assigning task/subtask');
                }
            });
        });

        $('.user-search-input').on('input', function() {
            const searchTerm = $(this).val().toLowerCase();
            let anyVisible = false;

            $('.user-list-container .role-header').each(function() {
                const roleHeader = $(this);
                const roleUsers = roleHeader.nextUntil('.role-header', 'li[data-user-id]');
                let hasVisibleUsers = false;

                roleUsers.each(function() {
                    const userName = $(this).find('span').text().toLowerCase();
                    const isVisible = userName.includes(searchTerm);
                    $(this).toggle(isVisible);
                    if (isVisible) {
                        hasVisibleUsers = true;
                        anyVisible = true;
                    }
                });

                roleHeader.toggle(hasVisibleUsers);
            });

            if (!anyVisible && searchTerm) {
                if (!$('.user-list-container .no-results').length) {
                    $('.user-list-container').append('<li class="no-results">No users found</li>');
                }
            } else {
                $('.user-list-container .no-results').remove();
            }
        });

        function decodeHtml(html) {
    const txt = document.createElement('textarea');
    txt.innerHTML = html;
    return txt.value;
}

        $(document).on('click', '.viewTaskModal, .viewSubTaskModal', function() {
            const isMainTask = $(this).hasClass('viewTaskModal');
            const taskId = isMainTask ? $(this).data('task-id') : $(this).data('sub-task-id');
            const url = isMainTask ? `${BASE_URL}tasks/${taskId}` : `${BASE_URL}subtasks/${taskId}`;

            $('#viewTaskOffcanvas #taskTitle').text('');
            $('#viewTaskOffcanvas .status').text('');
            $('#viewTaskOffcanvas .due_date').text('');
            $('#viewTaskOffcanvas .priority').text('');
            $('#viewTaskOffcanvas .invested_time').text('');
            $('#viewTaskOffcanvas .timer_status').text('');
            $('#viewTaskOffcanvas .stage').text('');
           $('#viewTaskOffcanvas #description').val('');
            $('#viewTaskOffcanvas .task-images').html('');
            $('#viewTaskOffcanvas #task_id').val('');
            $('#viewTaskOffcanvas #main_task').val('');
            $('#viewTaskOffcanvas #sub_task_id').val('');

            $.ajax({
                url: url,
                type: 'GET',
                success: function(response) {
                    const task = isMainTask ? response.task : response.subtask;
                    const offcanvas = new bootstrap.Offcanvas(document.getElementById('viewTaskOffcanvas'));

                    $('#viewTaskOffcanvas #taskTitle').text(task.title);
                    $('#viewTaskOffcanvas #task_id').val(task.id);
                    $('#viewTaskOffcanvas #main_task').val(isMainTask);
                    $('#viewTaskOffcanvas .status').text(task.status);
                    $('#viewTaskOffcanvas #attachment').val();
                    $('#viewTaskOffcanvas .due_date').text(task.due_date ? new Date(task.due_date).toLocaleString('en-US', {
                        year: 'numeric',
                        month: 'long',
                        day: '2-digit',
                        hour: '2-digit',
                        minute: '2-digit',
                        hour12: true
                    }) : 'No Due Date');
                    $('#viewTaskOffcanvas .priority').text(['Low', 'Normal', 'High', 'Urgent'][task.priority] || 'Low');
                    $('#viewTaskOffcanvas .invested_time').text(formatInvestTime(task.invest_time));
                    $('#viewTaskOffcanvas .timer_status').text(task.timerLogs?.find(log => log.user_id === AuthId && log.start_time && !log.end_time) ? 'Running' : 'Stopped');
                    $('#viewTaskOffcanvas .stage').text(task.approval || 'N/A');
                    $('#viewTaskOffcanvas #description').val(stripHtml(task.description || ''));

                    if (task.images) {
                        const imagesArray = JSON.parse(task.images);
                        if (Array.isArray(imagesArray) && imagesArray.length > 0) {
                            const taskImages = imagesArray.map(imageUrl =>
                                `<img src="${imageUrl}" class="task-image-preview rounded-pill me-2" alt="Task Image" width="50" height="50" style="object-fit: cover;" >`
                            ).join('');
                            $('#viewTaskOffcanvas .task-images').html(taskImages);
                        } else {
                            $('#viewTaskOffcanvas .task-images').html('');
                        }
                    } else {
                        $('#viewTaskOffcanvas .task-images').html('');
                    }

                    offcanvas.show();
                },
                error: function(xhr) {
                    toastr.error('Failed to load task/subtask details');
                }
            });
        });

        $('#taskTable, [id^=subTaskTable]').on('click', '.date-icon', function() {
            const parent = $(this).closest('.due-date-wrapper');
            const dateTextEl = parent.find('.due-date-text');
            const input = parent.find('input');

            dateTextEl.hide();
            input.removeClass('d-none');

            if (input.hasClass('flatpickr-input') && input[0]._flatpickr) {
                input[0]._flatpickr.destroy();
            }

            input.flatpickr({
    enableTime: true,
    dateFormat: "Y-m-d H:i",
    time_24hr: true,
    defaultHour: 18,
    defaultMinute: 0,
    onReady: function(selectedDates, dateStr, instance) {
        // Store the original date when the picker is initialized
        instance.originalDate = dateStr || input.val();
    },
    onClose: function(selectedDates, dateStr, instance) {
        if (dateStr && dateStr !== instance.originalDate) {
            // Only proceed if a date is selected and it differs from the original
            dateTextEl.text(dateStr).show();
            input.addClass('d-none');

            const taskId = input.data('task-id');
            const subTaskId = input.data('sub-task-id');
            const url = subTaskId ? `${BASE_URL}update-sub-task-due-date` : `${BASE_URL}update-task-due-date`;
            const postData = subTaskId ?
                { sub_task_id: subTaskId, due_date: dateStr, _token: $('meta[name="csrf-token"]').attr('content') } :
                { task_id: taskId, due_date: dateStr, _token: $('meta[name="csrf-token"]').attr('content') };

            $.post(url, postData)
                .done(res => toastr.success(res.message))
                .fail(() => toastr.error('Error updating due date!'));
        }
    }
}).open();
        });

        $('#taskTable').on('click', '.status-label', function() {
            const wrapper = $(this).closest('.status-wrapper');
            wrapper.find('.status-label').hide();
            wrapper.find('.status-select').removeClass('d-none');
        });

        $('#taskTable').on('change', '.status-select', function() {
            const wrapper = $(this).closest('.status-wrapper');
            const newVal = $(this).val();
            $.ajax({
                url: '{{ route('tasks.status-update') }}',
                type: 'POST',
                data: {
                    status: newVal,
                    task_id: $(this).data('id'),
                    _token: '{{ csrf_token() }}'
                },
                success: function(response) {
                    if (response.success) {
                        const statusMap = {
                            'pending': { title: 'Pending', class: 'bg-label-warning' },
                            'in_progress': { title: 'In Progress', class: 'bg-label-info' },
                            'completed': { title: 'Completed', class: 'bg-label-success' }
                        };
                        const status = statusMap[newVal] || { title: 'Unknown', class: 'bg-label-secondary' };
                        wrapper.find('.status-label')
                            .text(status.title)
                            .attr('class', `badge rounded-pill ${status.class} status-label cursor-pointer`)
                            .show();
                        wrapper.find('.status-select').addClass('d-none');
                        toastr.success(response.message);
                        fetchTasksAndTimers(allProjects.id, newVal);
                    } else {
                        toastr.error(response.message || 'Failed to update task status');
                    }
                },
                error: function(xhr) {
                    toastr.error(xhr.responseJSON?.message || 'Error updating task status');
                }
            });
        });

        $('#taskTable').on('click', '.priority-label', function() {
            const wrapper = $(this).closest('.priority-wrapper');
            wrapper.find('.priority-label').hide();
            wrapper.find('.priority-select').removeClass('d-none');
        });

        $('#taskTable').on('change', '.priority-select', function() {
            const wrapper = $(this).closest('.priority-wrapper');
            const newVal = $(this).val();
            $.ajax({
                url: '{{ route('tasks.priority-update') }}',
                type: 'POST',
                data: {
                    priority: newVal,
                    task_id: $(this).data('id'),
                    _token: '{{ csrf_token() }}'
                },
                success: function(response) {
                    if (response.success) {
                        const priorityMap = {
                            '0': { title: 'Low', class: 'bg-primary' },
                            '1': { title: 'Normal', class: 'bg-warning' },
                            '2': { title: 'High', class: 'bg-success' },
                            '3': { title: 'Urgent', class: 'bg-danger' }
                        };
                        const priority = priorityMap[newVal] || { title: 'Unknown', class: 'bg-secondary' };
                        wrapper.find('.priority-label')
                            .text(priority.title)
                            .attr('class', `badge rounded-pill ${priority.class} priority-label cursor-pointer`)
                            .show();
                        wrapper.find('.priority-select').addClass('d-none');
                        toastr.success(response.message);
                        fetchTasksAndTimers(allProjects.id, null);
                    } else {
                        toastr.error(response.message || 'Failed to update task priority');
                    }
                },
                error: function(xhr) {
                    toastr.error(xhr.responseJSON?.message || 'Error updating task priority');
                }
            });
        });

        $(document).on('click', '.subtask-status-label', function() {
            const wrapper = $(this).closest('.subtask-status-wrapper');
            wrapper.find('.subtask-status-label').hide();
            wrapper.find('.subtask-status-select').removeClass('d-none').focus();
        });

        $(document).on('change', '.subtask-status-select', function() {
            const wrapper = $(this).closest('.subtask-status-wrapper');
            const newVal = $(this).val();
            const subTaskId = $(this).data('sub-task-id');

            $.ajax({
                url: '{{ route('subtasks.status-update') }}',
                type: 'POST',
                data: {
                    status: newVal,
                    sub_task_id: subTaskId,
                    _token: '{{ csrf_token() }}'
                },
                success: function(response) {
                    if (response.success) {
                        const statusMap = {
                            'pending': { title: 'Pending', class: 'bg-label-warning' },
                            'in_progress': { title: 'In Progress', class: 'bg-label-info' },
                            'completed': { title: 'Completed', class: 'bg-label-success' }
                        };
                        const status = statusMap[newVal] || { title: 'Unknown', class: 'bg-label-secondary' };
                        wrapper.find('.subtask-status-label')
                            .text(status.title)
                            .attr('class', `badge rounded-pill ${status.class} subtask-status-label cursor-pointer`)
                            .show();
                        wrapper.find('.subtask-status-select').addClass('d-none');
                        toastr.success(response.message);
                        fetchTasksAndTimers(allProjects.id, newVal);
                    } else {
                        toastr.error(response.message || 'Failed to update subtask status');
                    }
                },
                error: function(xhr) {
                    toastr.error(xhr.responseJSON?.message || 'Error updating subtask status');
                }
            });
        });

        $(document).on('click', '.subtask-priority-label', function() {
            const wrapper = $(this).closest('.subtask-priority-wrapper');
            wrapper.find('.subtask-priority-label').hide();
            wrapper.find('.subtask-priority-select').removeClass('d-none').focus();
        });

        $(document).on('change', '.subtask-priority-select', function() {
            const wrapper = $(this).closest('.subtask-priority-wrapper');
            const newVal = $(this).val();
            const subTaskId = $(this).data('sub-task-id');

            $.ajax({
                url: '{{ route('subtasks.priority-update') }}',
                type: 'POST',
                data: {
                    priority: newVal,
                    sub_task_id: subTaskId,
                    _token: '{{ csrf_token() }}'
                },
                success: function(response) {
                    if (response.success) {
                        const priorityMap = {
                            '0': { title: 'Low', class: 'bg-primary' },
                            '1': { title: 'Normal', class: 'bg-warning' },
                            '2': { title: 'High', class: 'bg-success' },
                            '3': { title: 'Urgent', class: 'bg-danger' }
                        };
                        const priority = priorityMap[newVal] || { title: 'Unknown', class: 'bg-secondary' };
                        wrapper.find('.subtask-priority-label')
                            .text(priority.title)
                            .attr('class', `badge rounded-pill ${priority.class} ${checkRole ? 'subtask-priority-label' : ''} cursor-pointer`)
                            .show();
                        wrapper.find('.subtask-priority-select').addClass('d-none');
                        toastr.success(response.message);
                        fetchTasksAndTimers(allProjects.id, null);
                    } else {
                        toastr.error(response.message || 'Failed to update subtask priority');
                    }
                },
                error: function(xhr) {
                    toastr.error(xhr.responseJSON?.message || 'Error updating subtask priority');
                }
            });
        });

        $(document).on('click', '.delete-task', function(e) {
            e.preventDefault();
            const taskId = $(this).data('task-id');

            if (timers[`task-${taskId}`]?.isRunning) {
                toastr.error('Cannot delete task while timer is running. Please pause or stop the timer first.');
                return;
            }

            Swal.fire({
                title: 'Are you sure?',
                text: 'This will permanently delete the task and its subtasks!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: '{{ route("tasks.destroy", ":id") }}'.replace(':id', taskId),
                        type: 'DELETE',
                        data: {
                            _token: '{{ csrf_token() }}'
                        },
                        success: function(response) {
                            if (response.status) {
                                toastr.success(response.message);
                                fetchTasksAndTimers(allProjects.id, null);
                            } else {
                                toastr.error(response.message || 'Failed to delete task!');
                            }
                        },
                        error: function(xhr) {
                            toastr.error(xhr.responseJSON?.message || 'Error deleting task!');
                        }
                    });
                }
            });
        });

        $(document).on('click', '.delete-subtask', function(e) {
            e.preventDefault();
            const subTaskId = $(this).data('sub-task-id');
            const taskId = $(this).data('task-id');

            if (timers[`sub-${subTaskId}`]?.isRunning) {
                toastr.error('Cannot delete subtask while timer is running. Please pause or stop the timer first.');
                return;
            }

            Swal.fire({
                title: 'Are you sure?',
                text: 'This will permanently delete the subtask!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: '{{ route("subtasks.destroy", ":id") }}'.replace(':id', subTaskId),
                        type: 'DELETE',
                        data: {
                            _token: '{{ csrf_token() }}'
                        },
                        success: function(response) {
                            if (response.status) {
                                toastr.success(response.message);
                                const row = table.rows().nodes().to$().find(`.toggle-icon[data-task-id="${taskId}"]`).closest('tr');
                                const rowData = table.row(row).data();
                                rowData.subtasks = rowData.subtasks.filter(sub => sub.id !== subTaskId);
                                table.row(row).data(rowData).invalidate().draw(false);

                                if (table.row(row).child.isShown()) {
                                    table.row(row).child(formatSubtasks(rowData.subtasks, taskId)).show();
                                    initializeSubtaskDatepickers(taskId);
                                }
                            } else {
                                toastr.error(response.message || 'Failed to delete subtask!');
                            }
                        },
                        error: function(xhr) {
                            toastr.error(xhr.responseJSON?.message || 'Error deleting subtask!');
                        }
                    });
                }
            });
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
                _token: "{{ csrf_token() }}"
            },
            success: function(response) {
                if (response.success) {
                    $(".approval-list").hide();
                    toastr.success(response.message);
                    isEdit = false;
                    fetchTasksAndTimers(project_id, null);
                } else {
                    toastr.error('Unexpected response format');
                }
            },
            error: function(xhr) {
                console.log('Error:', xhr.responseJSON); // Debug the error
                toastr.error(xhr.responseJSON?.message || 'An error occurred');
            },
        });
    });

        $(document).on("click", ".status-list li", function(event) {
            event.stopPropagation();
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
                        fetchTasksAndTimers(project_id, null);
                    }
                },
                error: function(xhr) {
                    toastr.error(xhr.responseJSON.message);
                },
            });
        });

        $(document).on("click", function(event) {
            if (!$(event.target).closest(".approval-change").length) {
                $(".approval-list").hide();
            }
        });


        // Initialize tasks and timers on page load
        fetchTasksAndTimers(allProjects.id, null);
    });

      $(document).on("click", ".assign-remove-btn", function() {
            const task_id = $(this).data("task-id");
            const user_id = $(this).data("user-id");
            const subtask_id = $(this).data("sub-task-id");
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
                            subtask_id: subtask_id,
                            _token: '{{ csrf_token() }}'
                        },
                        success: function(data) {
                            if (data.success===true) {
                                fetchTasksAndTimers(allProjects.id, null);
                                toastr.success(data.message);
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
                // Hide the offcanvas
                const offcanvasElement = document.getElementById('viewTaskOffcanvas');
                const offcanvas = bootstrap.Offcanvas.getOrCreateInstance(offcanvasElement);
                offcanvas.hide();
                
                // Show success message
                toastr.success(response.message);
            },
            error: function(xhr) {
                console.error("Error updating description:", xhr.responseText);
                toastr.error('An error occurred while updating.');
            }
        });
        });

        function stripHtml(html) {
    var div = document.createElement("div");
    div.innerHTML = html;
    return div.textContent || div.innerText || "";
}

    $(document).on('click', '.task-image-preview', function() {
            let imageUrl = $(this).attr('src'); // Get the image source
            $('#imagePreviewModal img').attr('src', imageUrl); // Set image in modal
            $('#imagePreviewModal').modal('show'); // Show modal
        });

    $(document).on('click', '.submitTaskCanvas', function() {
    var taskId = $(this).data('task-id');
    var subtaskId = $(this).data('sub-task-id');
    if(subtaskId){
        var type = 'subtask';
        var taskId = subtaskId;
    }
    else{
        var type = 'task';
    }
    const offcanvas = new bootstrap.Offcanvas(document.getElementById(
        'submissionOffcanvas'));
    url = "{{ route('submissions.getDetails') }}";
    $.ajax({
        url: url,
        type: "get",
        data: {
            id: taskId,
            type: type,
            _token: "{{ csrf_token() }}",
        },
        success: function(response) {
            if (response.status === true) {
                $('#submissionOffcanvas #main_id').val(taskId);
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
                @if(auth()->user()->hasRole('admin') || auth()->user()->hasRole('project_manager') || auth()->user()->hasRole('team_leader'))
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
                                @if(auth()->user()->hasRole('admin') || auth()->user()->hasRole('project_manager') || auth()->user()->hasRole('team_leader'))
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
                                    @if(auth()->user()->hasRole('admin') || auth()->user()->hasRole('project_manager') || auth()->user()->hasRole('team_leader')) 
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
</script>
@endpush

