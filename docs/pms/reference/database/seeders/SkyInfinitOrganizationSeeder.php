<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SkyInfinitOrganizationSeeder extends Seeder
{
    /**
     * Seed the current Sky Infinit SEO and Marketing structure.
     *
     * Existing departments are preserved. Role permissions are synchronized so
     * rerunning this seeder safely brings these roles back to the intended state.
     */
    public function run(): void
    {
        $memberPermissionKeys = [
            'dashboard-view',
            'my-work-view',
            'project-view',
            'department-task-view-assigned',
            'task-add',
            'task-edit',
            'task-priority',
            'task-due-date',
            'task-status',
            'task-handoff',
            'sub-task-add',
            'sub-task-edit',
            'sub-task-priority',
            'sub-task-due-date',
            'sub-task-status',
            'description-update',
            'summary-view',
            'filter-date-summary',
            'message-access',
        ];

        $leadPermissionKeys = array_values(array_unique([
            ...$memberPermissionKeys,
            'simple-dashboard-view',
            'team-reporting-dashboard',
            'time-tracking-dashboard',
            'my-work-manage-team',
            'project-assign',
            'department-task-view-all',
            'task-assign',
            'filter-user-summary',
            'view-all-users-summary',
            'report-view',
        ]));

        $departments = [
            [
                'dept_name' => 'SEO',
                'description' => 'Organic search, content, and website performance',
                'status' => 1,
            ],
            [
                'dept_name' => 'Marketing',
                'description' => 'Video production, social media, and creative marketing',
                'status' => 1,
            ],
        ];

        $roles = [
            [
                'role_name' => 'SEO Team Lead',
                'role_key' => 'team_leader',
                'permissions' => $leadPermissionKeys,
            ],
            [
                'role_name' => 'Content Writer',
                'role_key' => 'content_writer',
                'permissions' => $memberPermissionKeys,
            ],
            [
                'role_name' => 'SEO Expert',
                'role_key' => 'seo_expert',
                'permissions' => $memberPermissionKeys,
            ],
            [
                'role_name' => 'Junior SEO Expert',
                'role_key' => 'junior_seo_expert',
                'permissions' => $memberPermissionKeys,
            ],
            [
                'role_name' => 'Video Editor',
                'role_key' => 'video_editor',
                'permissions' => $memberPermissionKeys,
            ],
            [
                'role_name' => 'Social Media Expert',
                'role_key' => 'social_media_expert',
                'permissions' => $memberPermissionKeys,
            ],
            [
                'role_name' => 'Creative Manager',
                'role_key' => 'team_leader',
                'permissions' => $leadPermissionKeys,
            ],
            [
                'role_name' => 'Senior Videographer',
                'role_key' => 'senior_videographer',
                'permissions' => $memberPermissionKeys,
            ],
            [
                'role_name' => 'Junior Videographer',
                'role_key' => 'junior_videographer',
                'permissions' => $memberPermissionKeys,
            ],
        ];

        $requiredPermissionKeys = collect($roles)
            ->flatMap(fn (array $role) => $role['permissions'])
            ->unique()
            ->values();

        $permissions = Permission::query()
            ->whereIn('permission_key', $requiredPermissionKeys)
            ->get()
            ->keyBy('permission_key');

        $missingPermissionKeys = $requiredPermissionKeys->diff($permissions->keys());

        if ($missingPermissionKeys->isNotEmpty()) {
            throw new RuntimeException(
                'Run PermissionSeeder first. Missing permissions: '.$missingPermissionKeys->implode(', ')
            );
        }

        DB::transaction(function () use ($departments, $roles, $permissions): void {
            foreach ($departments as $departmentData) {
                Department::query()->firstOrCreate(
                    ['dept_name' => $departmentData['dept_name']],
                    [
                        'description' => $departmentData['description'],
                        'status' => $departmentData['status'],
                    ]
                );
            }

            foreach ($roles as $roleData) {
                $role = Role::query()->firstOrNew([
                    'role_name' => $roleData['role_name'],
                ]);

                $role->role_key = $roleData['role_key'];
                $role->save();

                $permissionIds = collect($roleData['permissions'])
                    ->map(fn (string $key) => $permissions->get($key)->id)
                    ->all();

                $role->permissions()->sync($permissionIds);
            }

            $reportPermissionId = $permissions->get('report-view')->id;
            Role::query()
                ->where('role_key', 'project_manager')
                ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching([$reportPermissionId]));
        });
    }
}
