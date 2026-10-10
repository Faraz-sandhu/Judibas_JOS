<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\MyWorkController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectChatController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SprintController;
use App\Http\Controllers\SubmissionController;
use App\Http\Controllers\SubtaskController;
use App\Http\Controllers\SummaryController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TaskIssueController;
use App\Http\Controllers\TimerLogController;
use App\Http\Controllers\WorkflowController;
use App\Http\Controllers\BrandingSettingController;
use App\Http\Controllers\MailSettingController;
use App\Http\Controllers\RealtimeSettingController;
use App\Models\Invitation;
use Illuminate\Support\Facades\Route;

Route::get('/join/{token}', [InvitationController::class, 'join'])->name('invitations.join');
Route::post('/join/{token}', [InvitationController::class, 'completeJoin'])->name('invitations.join.complete');

Route::middleware('auth')->group(function () {
    Route::get('my-work', [MyWorkController::class, 'index'])->name('my-work.index');
    Route::get('/', [DashboardController::class, 'dashboard'])->name('dashboard');
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard.index');
    Route::get('profile', [ProfileController::class, 'index'])->name('profile.index');
    Route::post('profile-update', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('settings/branding', [BrandingSettingController::class, 'edit'])->name('settings.branding.edit');
    Route::put('settings/branding', [BrandingSettingController::class, 'update'])->name('settings.branding.update');
    Route::get('settings/email', [MailSettingController::class, 'edit'])->name('settings.mail.edit');
    Route::put('settings/email', [MailSettingController::class, 'update'])->name('settings.mail.update');
    Route::post('settings/email/test', [MailSettingController::class, 'test'])->name('settings.mail.test');
    Route::get('settings/realtime', [RealtimeSettingController::class, 'edit'])->name('settings.realtime.edit');
    Route::put('settings/realtime', [RealtimeSettingController::class, 'update'])->name('settings.realtime.update');
    Route::post('settings/realtime/test', [RealtimeSettingController::class, 'test'])->name('settings.realtime.test');
    Route::get('time-tracking-dashboard', [DashboardController::class, 'timeTrackingDashBoard'])->name('time-tracking-dashboard')->middleware('can:time-tracking-dashboard');
    // employee
    Route::resource('employee', EmployeeController::class)->except(['update']);
    Route::post('employee/{id}', [EmployeeController::class, 'update'])->name('employee.update');

    // roles
    Route::resource('roles', RoleController::class);

    // Permission
    Route::resource('permissions', PermissionController::class);

    // Roles & Permissions

    // projects
    Route::resource('projects', ProjectController::class)->except(['update']);
    Route::get('team-space/departments/{department}', [ProjectController::class, 'departmentBoard'])->name('team-space.departments.board');
    Route::post('projects/{id}', [ProjectController::class, 'update'])->name('projects.update');
    Route::post('assign-project', [ProjectController::class, 'assignProjectToTeamLeaderAndMembers'])->name('projects.assign-project');
    Route::post('remove-assign', [ProjectController::class, 'RemoveAssignUser'])->name('projects.remove-assign-user')->middleware('admin');
    Route::get('get-all-tasks/{id}', [ProjectController::class, 'GetAllTasks'])->name('get-all-tasks');
    Route::post('update-project-status', [ProjectController::class, 'updateProjectStatus'])->name('projects.update-status');
    Route::post('update-project-approval-status/{id}', [ProjectController::class, 'updateProjectApprovalStatus'])->name('projects.update-approval-status');
    Route::post('projects/{project}/sprints', [SprintController::class, 'store'])->name('sprints.store');
    Route::put('sprints/{sprint}', [SprintController::class, 'update'])->name('sprints.update');
    Route::delete('sprints/{sprint}', [SprintController::class, 'destroy'])->name('sprints.destroy');

    // Departments
    Route::resource('departments', DepartmentController::class);
    Route::resource('workflows', WorkflowController::class)->only(['index', 'store', 'update', 'destroy']);

    // tasks
    Route::resource('tasks', TaskController::class);
    Route::post('remove-task-assign', [TaskController::class, 'RemoveAssignUser'])->name('tasks.remove-task-assign');
    Route::post('assign-task', [TaskController::class, 'assignTaskToUser'])->name('tasks.assign-task');
    Route::post('status-update', [TaskController::class, 'StatusUpdate'])->name('tasks.status-update');
    Route::post('priority-update', [TaskController::class, 'priorityUpdate'])->name('tasks.priority-update');
    Route::post('update-task-due-date', [TaskController::class, 'taskDueDateUpdate'])->name('tasks.task-due-date-update');
    Route::post('task-update', [TaskController::class, 'taskApprovalUpdate'])->name('tasks.approval-update')->middleware('authority');
    Route::post('tasks-description', [TaskController::class, 'TaskDescription'])->name('tasks.description');
    Route::patch('tasks/{task}/board', [TaskController::class, 'boardUpdate'])->name('tasks.board-update');
    Route::get('tasks/{task}/handoff/options', [\App\Http\Controllers\TaskHandoffController::class, 'options'])->name('tasks.handoff.options');
    Route::post('tasks/{task}/handoff', [\App\Http\Controllers\TaskHandoffController::class, 'store'])->name('tasks.handoff.store');
    Route::post('tasks/{task}/placement', [\App\Http\Controllers\TaskHandoffController::class, 'place'])->name('tasks.placement.store');
    Route::post('tasks/{task}/comments', [TaskIssueController::class, 'comment'])->name('tasks.comments.store');
    Route::delete('task-comments/{comment}', [TaskIssueController::class, 'deleteComment'])->name('tasks.comments.destroy');
    Route::post('tasks/{task}/attachments', [TaskIssueController::class, 'attachments'])->name('tasks.attachments.store');
    Route::delete('task-attachments/{attachment}', [TaskIssueController::class, 'deleteAttachment'])->name('tasks.attachments.destroy');
    // subtask
    Route::resource('subtasks', SubtaskController::class);
    Route::post('sub-task-status-update', [SubtaskController::class, 'StatusUpdate'])->name('subtasks.status-update');
    Route::post('sub-task-priority-update', [SubtaskController::class, 'priorityUpdate'])->name('subtasks.priority-update');
    Route::post('update-sub-task-due-date', [SubtaskController::class, 'subTaskDueDateUpdate'])->name('subtasks.sub-task-due-date-update');
    Route::post('sub-task-update', [SubtaskController::class, 'subTaskApprovalUpdate'])->name('subtasks.approval-update')->middleware('authority');
    Route::post('sub-task-description', [SubtaskController::class, 'SubTaskDescription'])->name('subtasks.description');
    Route::post('tasks/start-timer', [TaskController::class, 'startTimer'])->name('tasks.start-timer');
    Route::post('tasks/pause-timer', [TaskController::class, 'pauseTimer'])->name('tasks.pause-timer');
    Route::post('subtasks/start-timer', [SubtaskController::class, 'startTimer'])->name('subtasks.start-timer');
    Route::post('subtasks/pause-timer', [SubtaskController::class, 'pauseTimer'])->name('subtasks.pause-timer');
    Route::post('tasks/stop-timer', [TaskController::class, 'stopTimer'])->name('tasks.stop-timer');
    Route::post('subtasks/stop-timer', [SubtaskController::class, 'stopTimer'])->name('subtasks.stop-timer');

    Route::post('timer-logs/start', [TimerLogController::class, 'startTimer'])->name('timer_logs.start');
    Route::post('timer-logs/pause', [TimerLogController::class, 'pauseTimer'])->name('timer_logs.pause');
    Route::post('timer-logs/stop', [TimerLogController::class, 'stopTimer'])->name('timer_logs.stop');
    Route::get('timer-logs/check-running', [TimerLogController::class, 'checkRunningTimer'])->name('timer_logs.check_running');

    // Notification
    Route::get('mark-all-as-read', [NotificationController::class, 'markAllAsRead'])->name('mark-all-as-read');
    Route::get('notifications/{notification}/open', [NotificationController::class, 'open'])->name('notifications.open');

    // Submission
    Route::resource('submissions', SubmissionController::class);
    Route::get('/getDetails', [SubmissionController::class, 'getProjectOrTaskDetails'])->name('submissions.getDetails');
    Route::get('/getSubmission', [SubmissionController::class, 'getSubmission'])->name('submissions.getSubmission');
    Route::post('submissions/update-status', [SubmissionController::class, 'updateStatus'])->name('submissions.updateStatus');

    // Summary
    Route::get('summary', [SummaryController::class, 'index'])->name('summary.index');

    // Invitation
    Route::post('/invitations/employees', [InvitationController::class, 'inviteEmployee'])->name('invitations.employees.store');
    Route::resource('invitations', InvitationController::class);
    Route::get('/invitations/received/ajax-list', [InvitationController::class, 'receivedList'])->name('invitations.received.list');
    Route::get('/invitations/sent/ajax-list', [InvitationController::class, 'sentList'])->name('invitations.sent.list');
    Route::post('/invitations', [InvitationController::class, 'send'])->name('invitations.send');
    Route::post('/tasks/{task}/invite', [InvitationController::class, 'inviteToTask'])->name('tasks.invite');
    Route::get('/tasks/{task}/invite-check', [InvitationController::class, 'checkInvitee'])->name('tasks.invite.check');
    Route::get('/invitations/{token}/accept', [InvitationController::class, 'accept'])->name('invitations.accept');
    Route::get('/invitations/{token}/decline', [InvitationController::class, 'decline'])->name('invitations.decline');

    // reports
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/data', [ReportController::class, 'getReportData'])->name('reports.data');
    Route::get('reports/overview', [ReportController::class, 'overview'])->name('reports.overview');
    Route::get('reports/tasks', [ReportController::class, 'getTasks'])->name('reports.tasks');
    Route::get('reports/subtasks', [ReportController::class, 'getSubtasks'])->name('reports.subtasks');
    // session check
    Route::get('/check-session', function () {
        return response()->json(['authenticated' => auth()->check()]);
    })->name('check-session');

    // Project Chat

    Route::get('/projects/{project}/chat', [ProjectChatController::class, 'fetch']);
    Route::post('/projects/{project}/chat/send', [ProjectChatController::class, 'send']);
    Route::post('/projects/{project}/chat/mark-seen', [ProjectChatController::class, 'markMessagesAsSeen']);
    Route::delete('/projects/chat/delete/{id}', [ProjectChatController::class, 'delete']);

});

require __DIR__.'/auth.php';
