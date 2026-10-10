<?php

namespace App\Modules\Pms\Database\Seeders;

use App\Modules\Pms\Models\Permission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

class PermissionSeeder extends Seeder
{
    public function run()
    {
        $permissions = [
            // Dashboard
            ['permission_name' => 'Dashboard View', 'permission_key' => 'dashboard-view'],
            ['permission_name' => 'Simple Dashboard View', 'permission_key' => 'simple-dashboard-view'],
            ['permission_name' => 'Team Reporting Dashboard', 'permission_key' => 'team-reporting-dashboard'],
            ['permission_name' => 'Time Tracking Dashboard', 'permission_key' => 'time-tracking-dashboard'],
            // Employee
            ['permission_name' => 'Employee View', 'permission_key' => 'employee-view'],
            ['permission_name' => 'Employee Add', 'permission_key' => 'employee-add'],
            ['permission_name' => 'Employee Edit', 'permission_key' => 'employee-edit'],
            ['permission_name' => 'Employee Delete', 'permission_key' => 'employee-trash'],
            // Pulse
            ['permission_name' => 'Pulse View', 'permission_key' => 'pulse-view'],
            // Roles
            ['permission_name' => 'Role View', 'permission_key' => 'role-view'],
            ['permission_name' => 'Role Add', 'permission_key' => 'role-add'],
            ['permission_name' => 'Role Edit', 'permission_key' => 'role-edit'],
            ['permission_name' => 'Role Delete', 'permission_key' => 'role-trash'],
            // Permissions
            ['permission_name' => 'Permission View', 'permission_key' => 'permission-view'],
            ['permission_name' => 'Permission Add', 'permission_key' => 'permission-add'],
            ['permission_name' => 'Permission Edit', 'permission_key' => 'permission-edit'],
            ['permission_name' => 'Permission Delete', 'permission_key' => 'permission-trash'],
            // projects
            ['permission_name' => 'Project View', 'permission_key' => 'project-view'],
            ['permission_name' => 'Project Assign', 'permission_key' => 'project-assign'],
            ['permission_name' => 'Project Add', 'permission_key' => 'project-add'],
            ['permission_name' => 'Project Edit', 'permission_key' => 'project-edit'],
            ['permission_name' => 'Project Delete', 'permission_key' => 'project-trash'],
            // Department
            ['permission_name' => 'Department View', 'permission_key' => 'department-view'],
            ['permission_name' => 'Department Add', 'permission_key' => 'department-add'],
            ['permission_name' => 'Department Edit', 'permission_key' => 'department-edit'],
            ['permission_name' => 'Department Delete', 'permission_key' => 'department-trash'],
            // Task
            ['permission_name' => 'Task Add', 'permission_key' => 'task-add'],
            ['permission_name' => 'Task Edit', 'permission_key' => 'task-edit'],
            ['permission_name' => 'Task Delete', 'permission_key' => 'task-trash'],
            ['permission_name' => 'Task Assign', 'permission_key' => 'task-assign'],
            ['permission_name' => 'Task Priority', 'permission_key' => 'task-priority'],
            ['permission_name' => 'Task Due Date', 'permission_key' => 'task-due-date'],
            ['permission_name' => 'Task Status', 'permission_key' => 'task-status'],
            ['permission_name' => 'Task Handoff', 'permission_key' => 'task-handoff'],
            ['permission_name' => 'Department Task View All', 'permission_key' => 'department-task-view-all'],
            ['permission_name' => 'Department Task View Assigned', 'permission_key' => 'department-task-view-assigned'],
            ['permission_name' => 'My Work View', 'permission_key' => 'my-work-view'],
            ['permission_name' => 'My Work Team Oversight', 'permission_key' => 'my-work-manage-team'],
            ['permission_name' => 'Developer Messaging', 'permission_key' => 'message-access'],
            // Subtask
            ['permission_name' => 'Sub Task Add', 'permission_key' => 'sub-task-add'],
            ['permission_name' => 'Sub Task Edit', 'permission_key' => 'sub-task-edit'],
            ['permission_name' => 'Sub Task Delete', 'permission_key' => 'sub-task-trash'],
            ['permission_name' => 'Sub Task Priority', 'permission_key' => 'sub-task-priority'],
            ['permission_name' => 'Sub Task Due Date', 'permission_key' => 'sub-task-due-date'],
            ['permission_name' => 'Sub Task Status', 'permission_key' => 'sub-task-status'],
            // Description
            ['permission_name' => 'Description Update', 'permission_key' => 'description-update'],
            // Summary
            ['permission_name' => 'Summary View', 'permission_key' => 'summary-view'],
            ['permission_name' => 'Filter User Summary', 'permission_key' => 'filter-user-summary'],
            ['permission_name' => 'Filter Date Summary', 'permission_key' => 'filter-date-summary'],
            ['permission_name' => 'View All Users Summary', 'permission_key' => 'view-all-users-summary'],

            // Reports
            ['permission_name' => 'Report View', 'permission_key' => 'report-view'],
            ['permission_name' => 'Branding Settings', 'permission_key' => 'branding-settings'],
            ['permission_name' => 'Realtime / Pusher Settings', 'permission_key' => 'realtime-settings'],
        ];
        foreach ($permissions as $perm) {
            Permission::updateOrCreate(['permission_key' => $perm['permission_key']], $perm);
        }
        Cache::forget('pms.authorization.permission_definitions');
    }
}
