<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PermissionController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {
        $this->authorize('permission-view');
        if ($request->ajax()) {
            $permission = Permission::all();

            return response()->json(['data' => $permission]);
        }

        return view('dashboard.permission.index');
    }

    public function create()
    {
        $this->authorize('permission-add');

        return view('dashboard.permission.create');
    }

    public function store(Request $request)
    {
        $this->authorize('permission-add');
        $Validation = $request->validate([
            'permission_name' => ['required', 'string', 'max:255'],
            'permission_key' => ['required', 'string', 'max:255', 'unique:'.Permission::class],
        ]);
        $permission = Permission::create([
            'permission_name' => $Validation['permission_name'],
            'permission_key' => $Validation['permission_key'],
        ]);
        Cache::forget('authorization.permission_definitions');
        if ($permission) {
            return response()->json(['success' => true, 'message' => 'Permission created'], 200);
        } else {
            return response()->json(['error' => true, 'message' => 'Permission not created'], 500);
        }
    }

    public function show(Permission $permission)
    {
        //
    }

    public function edit($id)
    {
        $this->authorize('permission-edit');
        $data = Permission::findOrFail($id);

        return response()->json(['data' => $data]);
    }

    public function update(Request $request)
    {
        $this->authorize('permission-edit');
        $validation = $request->validate([
            'permission_name' => ['required', 'string', 'max:255'],
        ]);
        $permission = Permission::findOrFail($request->record_id);
        $permission->update([
            'permission_name' => $validation['permission_name'],
        ]);
        if ($permission) {
            return response()->json(['success' => true, 'message' => 'Permission updated'], 200);
        } else {
            return response()->json(['error' => true, 'message' => 'Permission not updated'], 500);
        }
    }

    public function destroy($id)
    {
        $this->authorize('permission-trash');
        $permission = Permission::findOrFail($id);
        $permission->delete();
        Cache::forget('authorization.permission_definitions');
        return response()->json(['success' => true, 'message' => 'Permission permanently deleted'], 200);
    }
}
