<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Role;
use App\Models\User;
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
        $this->authorize('employee-view');
        $departments = Department::all();
        $roles = Role::all();
        if ($request->ajax()) {
            $employees = User::with(['departments:id,dept_name', 'roles:id,role_name'])->where('id', '!=', 1)->select('id', 'name', 'email', 'profile_img', 'status')->get();

            return response()->json(['data' => $employees]);
        }

        return view('dashboard.employee.index', compact('departments', 'roles'));
    }

    public function create()
    {
        $this->authorize('employee-add');

        return view('dashboard.employee.create');
    }

    public function store(Request $request)
    {
        $this->authorize('employee-add');
        // Validate the request
        $validatedData = $request->validate([
            'name' => ['required', 'string', 'max:255', 'regex:'.self::PERSON_NAME_PATTERN],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'surname' => ['nullable', 'string', 'max:255', 'regex:'.self::PERSON_NAME_PATTERN],
            'designation' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8'],
            'status' => ['required'],
            'dept_id' => ['required', 'exists:departments,id'],
            'role_id' => ['required', 'exists:roles,id'],
        ], [
            'name.regex' => 'The name may only contain letters, spaces, apostrophes, and hyphens.',
            'surname.regex' => 'The surname may only contain letters, spaces, apostrophes, and hyphens.',
        ]);
        $imageUrl = asset('default.jpg');
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
        $this->authorize('employee-edit');
        $data = User::with(['departments:id', 'roles:id'])->findOrFail($id);

        return response()->json(['data' => $data]);
    }

    public function update(Request $request)
    {
        $this->authorize('employee-edit');
        $validatedData = $request->validate([
            'name' => ['required', 'string', 'max:255', 'regex:'.self::PERSON_NAME_PATTERN],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($request->record_id)],
            'surname' => ['nullable', 'string', 'max:255', 'regex:'.self::PERSON_NAME_PATTERN],
            'designation' => ['required', 'string', 'max:255'],
            'status' => ['required'],
            'dept_id' => ['required', 'exists:departments,id'],
            'role_id' => ['required', 'exists:roles,id'],
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
        $this->authorize('employee-trash');
        $employee = User::findOrFail($id);
        DB::transaction(function () use ($employee) {
            DB::table('invitations')
                ->where('sender_id', $employee->id)
                ->orWhere('recipient_id', $employee->id)
                ->delete();
            DB::table('notifications')
                ->where('notifiable_type', User::class)
                ->where('notifiable_id', $employee->id)
                ->delete();
            $employee->delete();
        });

        return response()->json(['success' => true, 'message' => 'Employee permanently deleted']);
    }
}
