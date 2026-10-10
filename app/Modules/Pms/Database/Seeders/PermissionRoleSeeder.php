<?php

namespace App\Modules\Pms\Database\Seeders;

use App\Modules\Pms\Models\Permission;
use App\Modules\Pms\Models\Role;
use Illuminate\Database\Seeder;

class PermissionRoleSeeder extends Seeder
{
    public function run()
    {
        // Define the permissions for each role
        $rolePermissions = [

            // Admin
            'Admin' => Permission::pluck('id')->toArray(),

            // Developement Team Leader
            'Development Team Lead' => Permission::whereIn('permission_key', [
                'dashboard-view',
                'simple-dashboard-view',
                'team-reporting-dashboard',
                'time-tracking-dashboard',
                'project-view',
                'project-assign',
                'task-add',
                'task-edit',
                'task-assign',
                'task-priority',
                'task-status',
                'task-due-date',
                'sub-task-add',
                'sub-task-edit',
                'sub-task-status',
                'sub-task-priority',
                'sub-task-due-date',
                'summary-view',
                'filter-date-summary',
            ])->pluck('id')->toArray(),

            // Developer
            'Developer' => Permission::whereIn('permission_key', [
                'dashboard-view',
                'project-view',
                'task-add',
                'task-edit',
                'task-status',
                'task-priority',
                'task-due-date',
                'sub-task-add',
                'sub-task-edit',
                'sub-task-status',
                'sub-task-priority',
                'sub-task-due-date',
                'summary-view',
                'filter-date-summary',
            ])->pluck('id')->toArray(),

            // Graphic Team Lead
            'Graphic Team Lead' => Permission::whereIn('permission_key', [
                'dashboard-view',
                'simple-dashboard-view',
                'team-reporting-dashboard',
                'time-tracking-dashboard',
                'project-view',
                'project-assign',
                'task-add',
                'task-edit',
                'task-assign',
                'task-priority',
                'task-status',
                'task-due-date',
                'sub-task-add',
                'sub-task-edit',
                'sub-task-status',
                'sub-task-priority',
                'sub-task-due-date',
                'summary-view',
                'filter-date-summary',
            ])->pluck('id')->toArray(),

            // Graphic
            'Graphic' => Permission::whereIn('permission_key', [
                'dashboard-view',
                'project-view',
                'task-add',
                'task-edit',
                'task-status',
                'task-priority',
                'task-due-date',
                'sub-task-add',
                'sub-task-edit',
                'sub-task-status',
                'sub-task-priority',
                'sub-task-due-date',
                'summary-view',
                'filter-date-summary',
            ])->pluck('id')->toArray(),

            // Project Manager
            'Project Manager' => Permission::whereIn('permission_key', [
                'dashboard-view',
                'simple-dashboard-view',
                'team-reporting-dashboard',
                'time-tracking-dashboard',
                'project-view',
                'project-add',
                'project-edit',
                'project-assign',
                'task-add',
                'task-edit',
                'task-assign',
                'task-priority',
                'task-status',
                'task-due-date',
                'sub-task-add',
                'sub-task-edit',
                'sub-task-status',
                'sub-task-priority',
                'sub-task-due-date',
                'summary-view',
                'filter-date-summary',
                'report-view',
            ])->pluck('id')->toArray(),

            // SEO Team Leader
            'SEO Team Lead' => Permission::whereIn('permission_key', [
                'dashboard-view',
                'simple-dashboard-view',
                'team-reporting-dashboard',
                'time-tracking-dashboard',
                'project-view',
                'project-assign',
                'task-add',
                'task-edit',
                'task-assign',
                'task-priority',
                'task-status',
                'task-due-date',
                'sub-task-add',
                'sub-task-edit',
                'sub-task-status',
                'sub-task-priority',
                'sub-task-due-date',
                'summary-view',
                'filter-date-summary',
            ])->pluck('id')->toArray(),

            // SEO
            'SEO' => Permission::whereIn('permission_key', [
                'dashboard-view',
                'project-view',
                'task-add',
                'task-edit',
                'task-status',
                'task-priority',
                'task-due-date',
                'sub-task-add',
                'sub-task-edit',
                'sub-task-status',
                'sub-task-priority',
                'sub-task-due-date',
                'summary-view',
                'filter-date-summary',
            ])->pluck('id')->toArray(),

            // Writer Team Lead
            'Writer Team Lead' => Permission::whereIn('permission_key', [
                'dashboard-view',
                'simple-dashboard-view',
                'team-reporting-dashboard',
                'time-tracking-dashboard',
                'project-view',
                'project-assign',
                'task-add',
                'task-edit',
                'task-assign',
                'task-priority',
                'task-status',
                'task-due-date',
                'sub-task-add',
                'sub-task-edit',
                'sub-task-status',
                'sub-task-priority',
                'sub-task-due-date',
                'summary-view',
                'filter-date-summary',
            ])->pluck('id')->toArray(),

            // Writer
            'Writer' => Permission::whereIn('permission_key', [
                'dashboard-view',
                'project-view',
                'task-add',
                'task-edit',
                'task-status',
                'task-priority',
                'task-due-date',
                'sub-task-add',
                'sub-task-edit',
                'sub-task-status',
                'sub-task-priority',
                'sub-task-due-date',
                'summary-view',
                'filter-date-summary',
            ])->pluck('id')->toArray(),

        ];
        foreach ($rolePermissions as $roleName => $permissions) {
            $role = Role::where('role_name', $roleName)->first();
            if ($role) {
                $role->permissions()->sync($permissions);
            }
        }

        $viewPermission = Permission::where('permission_key', 'my-work-view')->value('id');
        $managePermission = Permission::where('permission_key', 'my-work-manage-team')->value('id');
        if ($viewPermission) {
            Role::query()->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching([$viewPermission]));
        }
        if ($managePermission) {
            Role::whereIn('role_key', ['admin', 'project_manager', 'team_leader'])
                ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching([$managePermission]));
        }
        $messagePermission = Permission::where('permission_key', 'message-access')->value('id');
        if ($messagePermission) {
            Role::where('role_key', 'developer')
                ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching([$messagePermission]));
        }

        $handoffPermission = Permission::where('permission_key', 'task-handoff')->value('id');
        if ($handoffPermission) {
            Role::whereHas('permissions', fn ($query) => $query->where('permission_key', 'task-edit'))
                ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching([$handoffPermission]));
        }
        $departmentAssigned = Permission::where('permission_key', 'department-task-view-assigned')->value('id');
        $departmentAll = Permission::where('permission_key', 'department-task-view-all')->value('id');
        if ($departmentAssigned) Role::query()->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching([$departmentAssigned]));
        if ($departmentAll) Role::whereIn('role_key', ['admin','project_manager','team_leader'])->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching([$departmentAll]));
    }
}
