<?php

namespace App\Modules\Pms\Http\Controllers;
use App\Http\Controllers\Controller;
use App\Modules\Pms\Services\PmsAccess;

use Illuminate\Http\Request;
use App\Modules\Pms\Models\Department;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class DepartmentController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {
        PmsAccess::requirePermission(request(), 'department-view');
        if (true) {
            $departments = Department::get();
            return response()->json(['data' => $departments]);
        }
        return view('dashboard.department.index');
    }


    public function create()
    {
        //
    }

    public function store(Request $request)
    {
        PmsAccess::requirePermission(request(), 'department-add');
        // Validate the request
        $validatedData = $request->validate([
            'dept_name' => ['required', 'string', 'max:255','unique:pms_departments,dept_name'],
            'description' => ['string'],
            'status' => ['required'],
        ]);

        // Create the Department
        $department = Department::create([
            'dept_name' => $validatedData['dept_name'],
            'description' => $validatedData['description'],
            'status' => $validatedData['status'],
        ]);

        if ($department) {
            return response()->json(['success' => true, 'message' => 'Department created'],200);
        } else {
            return response()->json(['error' => true, 'message' => 'Department not created'], 500);
        }
    }

    public function show(Department $department)
    {
        //
    }

    public function edit($id)
    {
        PmsAccess::requirePermission(request(), 'department-edit');
        $request->merge(['record_id'=>$id]);
        $data = Department::findOrFail($id);
        return response()->json(['data' => $data]);
    }

    public function update(Request $request, int $id)
    {
        $request->merge(['record_id'=>$id]);
        PmsAccess::requirePermission(request(), 'department-edit');
        $validatedData = $request->validate([
            'dept_name' => ['required', 'string', 'max:255', Rule::unique('pms_departments')->ignore($request->record_id)],
            'description' => ['string'],
            'status' => ['required'],
        ]);
        $department = Department::findOrFail($request->record_id);
        $department->update([
            'dept_name' => $validatedData['dept_name'],
            'description' => $validatedData['description'],
            'status' => $validatedData['status'],
        ]);

        if ($department) {
            return response()->json(['success' => true, 'message' => 'Department updated']);
        } else {
            return response()->json(['error' => true, 'message' => 'Department not updated'], 500);
        }
    }

    public function destroy($id)
    {
        PmsAccess::requirePermission(request(), 'department-trash');
        $department = Department::findOrFail($id);
        $department->delete();
        return response()->json(['success' => true, 'message' => 'Department permanently deleted']);
    }
}
