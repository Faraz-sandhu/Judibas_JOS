<?php

namespace App\Modules\Daymark\Http\Controllers;

use App\Modules\Daymark\Models\Company;
use App\Modules\Daymark\Models\CustomRole;
use App\Modules\Daymark\Services\Access;
use App\Modules\Daymark\Services\RoleAccess;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomRoleController
{
    public function store(Request $r)
    {
        $u = Access::actor($r);
        abort_unless($u->isAdmin(), 403);
        $v = $r->validate(['id' => 'nullable|integer', 'company_id' => 'required|integer', 'name' => 'required|string|max:100', 'base_role' => ['required', Rule::in(['employee', 'pm', 'om', 'ceo', 'md', 'hr', 'company_admin'])], 'permissions' => 'required|array|min:1', 'permissions.*' => ['required', 'distinct', Rule::in(array_keys(RoleAccess::ABILITIES))]]);
        abort_unless($u->role === 'super_admin' || $u->company_id === $v['company_id'], 403);
        Company::findOrFail($v['company_id']);
        $existing = isset($v['id']) ? CustomRole::findOrFail($v['id']) : null;
        if ($existing) {
            abort_unless($existing->company_id === $v['company_id'], 403);
            abort_unless($existing->base_role === $v['base_role'], 422, 'The base role cannot change after creation.');
        }
        $v['name'] = preg_replace('/\s+/u', ' ', trim($v['name']));
        abort_if($v['name'] === '', 422, 'Enter a role name.');
        abort_if(CustomRole::where('company_id', $v['company_id'])->where('name', $v['name'])->when($existing, fn ($q) => $q->where('id', '!=', $existing->id))->exists(), 422, 'This role name already exists.');
        abort_if(array_diff($v['permissions'], RoleAccess::forRole($v['base_role'])), 422, 'Selected permissions exceed this role scope.');
        abort_unless(in_array('view_reports', $v['permissions']), 422, 'Report access is required to open the workspace.');
        unset($v['id']);
        if ($existing) {
            $existing->update($v);
            $role = $existing;
        } else {
            $role = CustomRole::create($v);
        }

        return ['ok' => true, 'role' => $role];
    }

    public static function assignment(array $v, int $company): array
    {
        if (empty($v['custom_role_id'])) {
            return $v;
        }
        $role = CustomRole::where('company_id', $company)->find($v['custom_role_id']);
        abort_unless($role, 422, 'Select a role from this company.');
        $v['role'] = $role->base_role;

        return $v;
    }
}
