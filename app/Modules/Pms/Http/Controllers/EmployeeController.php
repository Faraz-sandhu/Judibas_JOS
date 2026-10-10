<?php

namespace App\Modules\Pms\Http\Controllers;
use App\Http\Controllers\Controller;
use App\Modules\Pms\Services\PmsAccess;

use App\Modules\Pms\Models\Department;
use App\Modules\Pms\Models\Role;
use App\Modules\Pms\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class EmployeeController extends Controller
{
    use AuthorizesRequests;

    private const PERSON_NAME_PATTERN = "/^[\pL\pM]+(?:[ '\-][\pL\pM]+)*$/u";

    public function index(Request $request)
    {
        PmsAccess::requirePermission(request(), 'employee-view');
        $departments = Department::all();
        $roles = Role::all();
        if (true) {
            $employees = User::with(['departments:id,dept_name', 'roles:id,role_name'])->select('id', 'name', 'surname', 'email', 'profile_img', 'status')->get();

            return response()->json(['data'=>$employees,'departments'=>$departments,'roles'=>$roles]);
        }

        return view('dashboard.employee.index', compact('departments', 'roles'));
    }

    public function create()
    {
        PmsAccess::requirePermission(request(), 'employee-add');

        return view('dashboard.employee.create');
    }

    public function store(Request $request)
    {
        PmsAccess::requirePermission(request(), 'employee-add');
        // Validate the request
        $validatedData = $request->validate([
            'name' => ['required', 'string', 'max:255', 'regex:'.self::PERSON_NAME_PATTERN],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'surname' => ['nullable', 'string', 'max:255', 'regex:'.self::PERSON_NAME_PATTERN],
            'designation' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8'],
            'status' => ['required'],
            'dept_id' => ['required', 'exists:pms_departments,id'],
            'role_id' => ['required', 'exists:pms_roles,id'],
        ], [
            'name.regex' => 'The name may only contain letters, spaces, apostrophes, and hyphens.',
            'surname.regex' => 'The surname may only contain letters, spaces, apostrophes, and hyphens.',
        ]);
        $imageUrl = asset('pms-assets/default.jpg');
        $user = User::create([
            'name' => $validatedData['name'],
            'email' => $validatedData['email'],
            'surname' => $validatedData['surname'],
            'designation' => $validatedData['designation'],
            'password' => Hash::make($validatedData['password']),
            'status' => $validatedData['status'],
            'profile_img' => $imageUrl,
        ]);

        if ($user) {
            DB::table('user_product_access')->updateOrInsert(['user_id'=>$user->id,'product_slug'=>'projects'],[]);
            $user->roles()->attach($validatedData['role_id']);
            $user->departments()->attach($validatedData['dept_id']);

            return response()->json([
                'success' => true,
                'message' => 'Employee created and department assigned',
            ], 200);
        } else {
            return response()->json([
                'error' => true,
                'message' => 'Employee not created',
            ], 500);
        }
    }

    public function show(User $user)
    {
        //
    }

    public function edit($id)
    {
        PmsAccess::requirePermission(request(), 'employee-edit');
        $data = User::with(['departments:id', 'roles:id'])->findOrFail($id);

        return response()->json(['data' => $data]);
    }

    public function update(Request $request,int $id)
    {
        $request->merge(['record_id'=>$id]);
        PmsAccess::requirePermission(request(), 'employee-edit');
        $request->merge(['record_id'=>$id]);
        $validatedData = $request->validate([
            'name' => ['required', 'string', 'max:255', 'regex:'.self::PERSON_NAME_PATTERN],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($request->record_id)],
            'surname' => ['nullable', 'string', 'max:255', 'regex:'.self::PERSON_NAME_PATTERN],
            'designation' => ['required', 'string', 'max:255'],
            'status' => ['required'],
            'dept_id' => ['required', 'exists:pms_departments,id'],
            'role_id' => ['required', 'exists:pms_roles,id'],
        ], [
            'name.regex' => 'The name may only contain letters, spaces, apostrophes, and hyphens.',
            'surname.regex' => 'The surname may only contain letters, spaces, apostrophes, and hyphens.',
        ]);
        $employee = User::findOrFail($request->record_id);
        $employee->update([
            'name' => $validatedData['name'],
            'surname' => $validatedData['surname'],
            'designation' => $validatedData['designation'],
            'email' => $validatedData['email'],
            'status' => $validatedData['status'],
        ]);

        $employee->roles()->sync([$validatedData['role_id']]);
        $employee->departments()->sync([$validatedData['dept_id']]);

        return response()->json(['success' => true, 'message' => 'Employee updated'], 200);
    }

    public function destroy($id)
    {
        PmsAccess::requirePermission(request(), 'employee-trash');
        $employee = User::findOrFail($id);
        DB::transaction(function () use ($employee) {
            DB::table('pms_invitations')
                ->where('sender_id', $employee->id)
                ->orWhere('recipient_id', $employee->id)
                ->delete();
            DB::table('pms_notifications')
                ->where('notifiable_type', User::class)
                ->where('notifiable_id', $employee->id)
                ->delete();
            $employee->roles()->detach();$employee->departments()->detach();$employee->projects()->detach();$employee->tasks()->detach();$employee->subtasks()->detach();
            DB::table('pms_timer_logs')->where('user_id',$employee->id)->whereNull('end_time')->update(['end_time'=>now(),'updated_at'=>now()]);
            DB::table('user_product_access')->where('user_id',$employee->id)->where('product_slug','projects')->delete();
        });

        return response()->json(['success' => true, 'message' => 'Employee removed from PMS']);
    }
}
