<?php

namespace App\Modules\Daymark\Services;

use App\Modules\Daymark\Models\CustomRole;
use App\Modules\Daymark\Models\User;

class RoleAccess
{
    public const ROLES = ['super_admin', 'company_admin', 'ceo', 'md', 'om', 'pm', 'hr', 'employee'];

    public const ABILITIES = [
        'view_reports' => 'View reports within assigned scope',
        'write_reports' => 'Write own daily reports',
        'export_reports' => 'Download reports within assigned scope',
        'view_companies' => 'View linked companies',
        'view_people' => 'View people within assigned scope',
        'review_reports' => 'Review other people within assigned scope',
        'manage_settings' => 'Manage application email settings',
        'manage_companies' => 'Create companies',
        'manage_departments' => 'Create and delete company departments',
        'manage_invitations' => 'Create and revoke invitations',
        'manage_people' => 'Manage employee profiles and account status',
        'manage_roles' => 'Manage company roles and reporting assignments',
        'view_roles' => 'View role and permission settings',
        'view_audit' => 'View platform activity log',
    ];

    public static function permissions(?User $user): array
    {
        if (! $user || ! $user->active || ! in_array($user->role, self::ROLES, true)) {
            return [];
        }
        $base = self::forRole($user->role);
        if (! $user->custom_role_id || $user->role === 'super_admin') {
            return $base;
        }
        $role = CustomRole::where('company_id', $user->company_id)->find($user->custom_role_id);

        return $role && $role->base_role === $user->role ? array_values(array_intersect($base, $role->permissions)) : [];
    }

    public static function forRole(string $role): array
    {
        $base = ['view_reports', 'write_reports', 'export_reports', 'view_companies'];

        return match ($role) {
            'super_admin' => array_values(array_diff(array_keys(self::ABILITIES), ['write_reports'])),
            'company_admin' => [...$base, 'view_people', 'review_reports', 'manage_invitations', 'manage_people', 'manage_roles', 'view_roles', 'manage_departments'],
            'ceo', 'md', 'hr', 'pm', 'om' => [...$base, 'view_people', 'review_reports'],
            'employee' => $base,
            default => [],
        };
    }

    public static function allows(?User $user, string $ability): bool
    {
        return in_array($ability, self::permissions($user), true);
    }

    public static function matrix(): array
    {
        return ['abilities' => self::ABILITIES, 'roles' => array_map(fn ($role) => [
            'role' => $role,
            'permissions' => self::forRole($role),
            'scope' => match ($role) {
                'super_admin' => 'All companies',
                'company_admin', 'ceo', 'md', 'hr' => 'Own company',
                'pm', 'om' => 'Assigned people in own company',
                default => 'Own reports only'
            },
        ], self::ROLES)];
    }
}
