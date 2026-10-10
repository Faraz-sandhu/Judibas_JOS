<?php

namespace App\Modules\Pms\Http\Controllers;
use App\Http\Controllers\Controller;
use App\Modules\Pms\Services\PmsAccess;

use Illuminate\Http\Request;
use App\Modules\Pms\Models\User;
use App\Modules\Pms\Models\Role;
use App\Modules\Pms\Models\Permission;
use App\Modules\Pms\Models\RoleUser;
use Illuminate\Http\Response;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class RolePermissionController extends Controller
{
    use AuthorizesRequests;
    public function PermissionAssignRole()
    {
        PmsAccess::requirePermission(request(), 'permission-assign-role');
        $roles = Role::all();
        $permissions = Permission::all();
        return view('dashboard.permission-assign.index', compact('roles', 'permissions'));
    }
    public function getRolePermissions(Request $request)
    {
        PmsAccess::requirePermission(request(), 'permission-assign-role');
        $role = Role::with('permissions')->findOrFail($request->role_id);
        return response()->json($role->permissions->pluck('id'));
    }
    public function assignPermissionToRole(Request $request)
    {
        PmsAccess::requirePermission(request(), 'permission-assign-role');
        $role = Role::findOrFail($request->role_id);
        if ($request->has('assign_all')) {
            if ($request->assign_all) {
                if (isset($request->permission_ids) && is_array($request->permission_ids)) {
                    $role->permissions()->syncWithoutDetaching($request->permission_ids);
                    return response()->json(['success' => true, 'message' => 'All permissions assigned'], Response::HTTP_OK);
                }
            } else {
                if (isset($request->permission_ids) && is_array($request->permission_ids)) {
                    $role->permissions()->detach($request->permission_ids);
                    return response()->json(['success' => true, 'message' => 'All permissions removed'], Response::HTTP_OK);
                }
            }
        }
        if ($request->filled('permission_id')) {
            $permission = Permission::findOrFail($request->permission_id);
            if ($role->permissions->contains($permission->id)) {
                $role->permissions()->detach([$permission->id]);
                return response()->json(['success' => true, 'message' => 'All permissions remocved'], Response::HTTP_OK);
            } else {
                $role->permissions()->syncWithoutDetaching([$permission->id]);
                return response()->json(['success' => true, 'message' => 'All permissions assigned'], Response::HTTP_OK);
            }
        }
        return response()->json(['error' => true, 'message' => 'something went wrong'], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
    public function RoleAssignUser(Request $request)
    {
        PmsAccess::requirePermission(request(), 'role-assign-user');
        $roles = Role::all();
        $users = User::all();
        if ($request->ajax()) {
            $employee = User::with('roles')->whereHas('roles')->select('id', 'name')->get();
            return response()->json(['data' => $employee]);
        }
        return view('dashboard.role-assign.index', compact('roles', 'users'));
    }
    public function assignRoleToUser(Request $request)
    {
        PmsAccess::requirePermission(request(), 'role-assign-user');
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'role_id' => 'required|exists:pms_roles,id',
        ]);
        $user = User::findOrFail($validated['user_id']);
        $role = Role::findOrFail($validated['role_id']);
        $user->roles()->sync([$role->id]);
        return redirect()->route('role-permission.role-assign')->with(['success' => true, 'message' => 'Role assigned']);
    }
    public function assignRoleToUserUpdate(Request $request , $id)
    {
        PmsAccess::requirePermission(request(), 'role-assign-user');
        $validated = $request->validate([
            'role_id' => 'required|exists:pms_roles,id',
        ]);
        $roleUser = RoleUser::where('user_id',$id)->first();
        $roleUser->update([
            'role_id' => $validated['role_id'],
        ]);
        if($roleUser){
            return response()->json(['success' => true, 'message' => 'Role assigned'] , Response::HTTP_OK);
        }else{
            return response()->json(['error' => true, 'message' => 'Role not assigned'] , Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
