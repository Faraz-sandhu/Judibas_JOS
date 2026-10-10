<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {
        $this->authorize('role-view');
        if ($request->ajax()) {
            $roles = Role::with('permissions:id,permission_name')->get();

            return response()->json(['data' => $roles]);
        }
        $permissions = Permission::orderBy('permission_name')->get(['id', 'permission_name', 'permission_key']);

        return view('dashboard.role.index', compact('permissions'));
    }

    public function create()
    {
        $this->authorize('role-add');

        return view('admin.role.create');
    }

    public function store(Request $request)
    {
        $this->authorize('role-add');
        $validation = $request->validate([
            'role_name' => ['required', 'string', 'max:255', Rule::unique('roles', 'role_name')],
            'role_key' => ['required', 'string', 'max:255', 'alpha_dash', 'unique:roles,role_key'],
            'permission_ids' => ['required', 'array', 'min:1'],
            'permission_ids.*' => ['integer', 'exists:permissions,id'],
        ], [
            'role_name.unique' => 'A role with this name already exists.',
            'role_key.unique' => 'A role with this key already exists.',
            'permission_ids.required' => 'Select at least one permission.',
            'permission_ids.min' => 'Select at least one permission.',
        ]);
        $role = DB::transaction(function () use ($validation) {
            $role = Role::create(['role_name' => $validation['role_name'], 'role_key' => $validation['role_key']]);
            $role->permissions()->sync($validation['permission_ids']);

            return $role;
        });
        if ($role) {
            return response()->json(['success' => true, 'message' => 'Role created'], 200);
        } else {
            return response()->json(['error' => true, 'message' => 'Role not created'], 500);
        }
    }

    public function show(Role $role)
    {
        //
    }

    public function edit($id)
    {
        $this->authorize('role-edit');
        $data = Role::with('permissions:id')->findOrFail($id);

        return response()->json(['data' => $data]);
    }

    public function update(Request $request)
    {
        $this->authorize('role-edit');
        $validation = $request->validate([
            'role_name' => ['required', 'string', 'max:255', Rule::unique('roles', 'role_name')->ignore($request->record_id)],
            'role_key' => ['required', 'string', 'max:255', 'alpha_dash', Rule::unique('roles', 'role_key')->ignore($request->record_id)],
            'permission_ids' => ['required', 'array', 'min:1'],
            'permission_ids.*' => ['integer', 'exists:permissions,id'],
        ], [
            'role_name.unique' => 'A role with this name already exists.',
            'role_key.unique' => 'A role with this key already exists.',
            'permission_ids.required' => 'Select at least one permission.',
            'permission_ids.min' => 'Select at least one permission.',
        ]);
        $role = Role::findOrFail($request->record_id);
        DB::transaction(function () use ($role, $validation) {
            $role->update(['role_name' => $validation['role_name'], 'role_key' => $validation['role_key']]);
            $role->permissions()->sync($validation['permission_ids']);
        });
        if ($role) {
            return response()->json(['success' => true, 'message' => 'Role updated'], 200);
        } else {
            return response()->json(['error' => true, 'message' => 'Role not updated'], 500);
        }
    }

    public function destroy($id)
    {
        $this->authorize('role-trash');
        $role = Role::findOrFail($id);
        $role->delete();
        return response()->json(['success' => true, 'message' => 'Role permanently deleted'], 200);
    }
}
