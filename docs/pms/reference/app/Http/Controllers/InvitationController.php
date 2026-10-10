<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Invitation;
use App\Services\InvitationService;
use App\Services\EmailDeliveryService;
use App\Models\Projects;
use App\Models\Subtasks;
use App\Models\Tasks;
use App\Models\User;
use App\Models\Role;
use App\Models\Department;
use App\Notifications\EmployeeInvitationNotification;
use App\Notifications\TaskInvitationNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class InvitationController extends Controller
{

    protected $invitationService;

    public function __construct(InvitationService $invitationService, protected EmailDeliveryService $emailDelivery)
    {
        $this->invitationService = $invitationService;
    }
    /**
     * Display a listing of the resource.
     */

    public function index()
    {
        $user = request()->user();
        $sent = $user->sentInvitations()->count();
        $accepted = $user->sentInvitations()->whereNotNull('accepted_at')->count();
        $pending = $user->sentInvitations()->pending()->count();

        $canInviteEmployees = $user->can('employee-add') || $user->hasRole('admin');
        $roles = $canInviteEmployees
            ? Role::query()->orderBy('role_name')->get(['id', 'role_name', 'role_key'])
            : collect();
        $departments = $canInviteEmployees
            ? Department::query()->where('status', 1)->orderBy('dept_name')->get(['id', 'dept_name'])
            : collect();

        return view('dashboard.invitations.index', compact('sent', 'accepted', 'pending', 'canInviteEmployees', 'roles', 'departments'));
    }

    public function inviteEmployee(Request $request)
    {
        $sender = $request->user();
        abort_unless($sender->can('employee-add') || $sender->hasRole('admin'), 403, 'You do not have permission to invite employees.');

        $validated = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'delivery_method' => ['required', 'in:email,manual'],
        ]);
        $email = Str::lower(trim($validated['email']));

        if ($existingUser = User::whereRaw('LOWER(email) = ?', [$email])->first(['id', 'name', 'email'])) {
            return response()->json([
                'message' => $existingUser->name.' is already registered with '.$existingUser->email.'.',
            ], 422);
        }

        $role = Role::findOrFail($validated['role_id']);
        if ($role->role_key === 'admin' && ! $sender->hasRole('admin')) {
            abort(403, 'Only an administrator can invite another administrator.');
        }

        $invitation = DB::transaction(function () use ($sender, $email, $validated) {
            Invitation::query()
                ->where('purpose', 'employee_onboarding')
                ->whereRaw('LOWER(invitee_email) = ?', [$email])
                ->pending()
                ->delete();

            return Invitation::create([
                'sender_id' => $sender->id,
                'recipient_id' => null,
                'invitee_email' => $email,
                'invitee_name' => trim($validated['name'] ?? '') ?: Str::headline(Str::before($email, '@')),
                // The sender is the workspace context. Purpose distinguishes this
                // from project/task invitations without breaking existing morphs.
                'invitable_type' => User::class,
                'invitable_id' => $sender->id,
                'role' => 'employee',
                'purpose' => 'employee_onboarding',
                'role_id' => $validated['role_id'],
                'department_id' => $validated['department_id'] ?? null,
                'token' => Str::random(32),
                'expires_at' => now()->addDays(7),
            ]);
        });

        $invitation->load(['sender', 'assignedRole', 'department']);
        if ($validated['delivery_method'] === 'manual') {
            $invitation->update(['delivery_status' => 'manual', 'email_error' => null]);
            $delivery = ['status' => 'manual', 'message' => 'Secure employee invitation created for manual sharing.'];
        } else {
            $delivery = $this->emailDelivery->send(
                Notification::route('mail', $email),
                new EmployeeInvitationNotification($invitation),
                'invitation',
                $invitation
            );
        }

        $url = route('invitations.join', $invitation->token);
        $message = $sender->name.' invited you to join the team as '.$role->role_name.'. Create your account using this secure link: '.$url;

        return response()->json([
            'message' => $delivery['status'] === 'sent'
                ? 'Employee invitation emailed to '.$email.'.'
                : ($delivery['status'] === 'manual'
                    ? 'Employee invitation is ready to copy.'
                    : 'Invitation created, but email was not sent. Copy and share it manually.'),
            'delivery_status' => $delivery['status'],
            'delivery_message' => $delivery['message'],
            'invitation_url' => $url,
            'manual_message' => $message,
        ], 201);
    }

    public function inviteToTask(Request $request, Tasks $task)
    {
        $sender = $request->user();
        abort_unless(
            $sender->hasAnyPermission(['task-assign', 'my-work-manage-team']) ||
            $sender->hasRole('admin') || $sender->hasRole('project_manager') || $sender->hasRole('team_leader'),
            403,
            'You do not have permission to invite task assignees.'
        );
        abort_unless($sender->hasRole('admin') || $task->project->users()->where('users.id', $sender->id)->exists(), 403, 'You do not have access to this task.');

        $validated = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'delivery_method' => ['nullable', 'in:email,manual'],
        ]);
        $email = Str::lower(trim($validated['email']));

        if ($existingUser = User::whereRaw('LOWER(email) = ?', [$email])->first(['id', 'name', 'email', 'profile_img', 'status'])) {
            return response()->json([
                'message' => $existingUser->name.' ('.$existingUser->email.') is already registered. Select the employee instead; an invitation was not sent.',
                'existing_user' => $existingUser,
            ], 422);
        }

        $invitation = DB::transaction(function () use ($sender, $task, $email, $validated) {
            Invitation::where('invitee_email', $email)
                ->where('invitable_type', Tasks::class)
                ->where('invitable_id', $task->id)
                ->pending()
                ->delete();

            return Invitation::create([
                'sender_id' => $sender->id,
                'recipient_id' => null,
                'invitee_email' => $email,
                'invitee_name' => trim($validated['name'] ?? '') ?: Str::headline(Str::before($email, '@')),
                'invitable_type' => Tasks::class,
                'invitable_id' => $task->id,
                'role' => 'task_assignee',
                'token' => Str::random(32),
                'expires_at' => now()->addDays(7),
            ]);
        });

        $invitation->load(['sender', 'invitable.project']);
        $deliveryMethod = $validated['delivery_method'] ?? 'email';
        if ($deliveryMethod === 'manual') {
            $invitation->update(['delivery_status' => 'manual', 'email_error' => null]);
            $delivery = ['allowed' => true, 'status' => 'manual', 'message' => 'Secure invitation link created for manual sharing.'];
        } else {
            $delivery = $this->emailDelivery->send(
                Notification::route('mail', $email),
                new TaskInvitationNotification($invitation),
                'invitation',
                $invitation
            );
        }
        $inviteUrl = route('invitations.join', $invitation->token);
        $manualMessage = $sender->name.' invited you to collaborate on “'.$task->title.'” in “'.$task->project->name.'”. Accept the secure invitation: '.$inviteUrl;

        return response()->json([
            'message' => $delivery['status'] === 'sent'
                ? 'Invitation email sent to '.$email.'.'
                : ($delivery['status'] === 'manual'
                    ? 'Invitation link created and ready to copy.'
                    : 'Invitation created, but email was not sent. Copy and share the secure link manually.'),
            'invitation_id' => $invitation->id,
            'delivery_status' => $delivery['status'],
            'delivery_message' => $delivery['message'],
            'invitation_url' => $inviteUrl,
            'manual_message' => $manualMessage,
        ], 201);
    }

    public function checkInvitee(Request $request, Tasks $task)
    {
        $sender = $request->user();
        abort_unless(
            $sender->hasAnyPermission(['task-assign', 'my-work-manage-team']) ||
            $sender->hasRole('admin') || $sender->hasRole('project_manager') || $sender->hasRole('team_leader'),
            403
        );
        abort_unless($sender->hasRole('admin') || $task->project->users()->where('users.id', $sender->id)->exists(), 403);
        $validated = $request->validate(['email' => ['required', 'email:rfc', 'max:255']]);
        $email = Str::lower(trim($validated['email']));
        $employee = User::with(['roles:id,role_key', 'departments:id'])
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first(['id', 'name', 'email', 'profile_img', 'status']);
        $assignable = $employee
            && (int) $employee->status === 1
            && ! $employee->roles->contains('role_key', 'admin')
            && (! $sender->hasRole('team_leader') || $sender->hasRole('admin') || $sender->hasRole('project_manager')
                || $employee->departments->pluck('id')->intersect($sender->departments->pluck('id'))->isNotEmpty());

        return response()->json([
            'exists' => (bool) $employee,
            'employee' => $employee,
            'assignable' => (bool) $assignable,
        ]);
    }

    public function join(string $token)
    {
        $invitation = Invitation::with(['sender', 'assignedRole', 'department'])->where('token', $token)->firstOrFail();
        abort_unless($invitation->isPending(), 410, 'This invitation has expired or has already been used.');
        abort_unless(! $invitation->isEmployeeOnboarding() || $invitation->assignedRole, 410, 'The role for this invitation is no longer available. Ask the inviter to send a new invitation.');

        if (! $invitation->isEmployeeOnboarding()) {
            $invitation->load('invitable.project');
        }

        return view('auth.accept-invitation', compact('invitation'));
    }

    public function completeJoin(Request $request, string $token)
    {
        $invitation = Invitation::with(['assignedRole', 'department'])->where('token', $token)->firstOrFail();
        abort_unless($invitation->isPending(), 410, 'This invitation has expired or has already been used.');
        abort_unless(! $invitation->isEmployeeOnboarding() || $invitation->assignedRole, 410, 'The role for this invitation is no longer available. Ask the inviter to send a new invitation.');
        if (! $invitation->isEmployeeOnboarding()) {
            $invitation->load('invitable.project');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        if (User::whereRaw('LOWER(email) = ?', [Str::lower($invitation->invitee_email)])->exists()) {
            return back()->withErrors(['email' => 'An account now exists for this email. Please sign in or ask the inviter to resend the invitation.']);
        }

        $user = DB::transaction(function () use ($invitation, $validated) {
            $role = $invitation->isEmployeeOnboarding()
                ? $invitation->assignedRole
                : (Role::whereIn('role_key', ['developer', 'employee', 'team_member'])->first()
                    ?: Role::whereNotIn('role_key', ['admin', 'project_manager'])->first());

            $user = User::create([
                'name' => trim($validated['name']),
                'email' => Str::lower($invitation->invitee_email),
                'password' => Hash::make($validated['password']),
                'status' => 1,
                'designation' => $role?->role_name ?: 'Team member',
                'profile_img' => asset('default.jpg'),
                'email_verified_at' => now(),
            ]);

            if ($role) {
                $user->roles()->syncWithoutDetaching([$role->id]);
            }
            $departmentId = $invitation->isEmployeeOnboarding()
                ? $invitation->department_id
                : $invitation->invitable->department_id;
            if ($departmentId) {
                $user->departments()->syncWithoutDetaching([$departmentId]);
            }

            $invitation->update(['recipient_id' => $user->id]);
            if ($invitation->isEmployeeOnboarding()) {
                $invitation->update(['accepted_at' => now()]);
            } elseif (! $this->invitationService->acceptInvitation($invitation->fresh(['invitable.project']))) {
                throw new \RuntimeException('Invitation could not be accepted.');
            }

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        if ($invitation->isEmployeeOnboarding()) {
            return redirect()->route('dashboard')->with('success', 'Welcome! Your employee account is ready.');
        }

        return redirect()->route('my-work.index', ['task' => $invitation->invitable_id])
            ->with('success', 'Welcome! Your account is ready and the task has been assigned to you.');
    }

  public function receivedList(Request $request)
{
    $user = $request->user();
    $invitations = $user->receivedInvitations()
        ->with(['sender', 'invitable'])
        ->latest()
        ->get();

    $filteredInvitations = $invitations->filter(function ($invitation) {
        $type = class_basename($invitation->invitable_type);

        if ($type == 'Projects') {
            return Projects::find($invitation->invitable_id) !== null;
        } elseif ($type == 'Tasks') {
            $task = Tasks::find($invitation->invitable_id);
            return $task && $task->project;
        } elseif ($type == 'Subtasks') {
            $subtask = Subtasks::find($invitation->invitable_id);
            return $subtask && $subtask->task && $subtask->task->project;
        }

        return false;
    });

    return response()->json([
        'data' => $filteredInvitations->map(function ($invitation) use ($user) {
            $status = 'pending';
            $status_color = 'label-primary';

            if ($invitation->accepted_at) {
                $status = 'accepted';
                $status_color = 'label-success';
            } elseif ($invitation->declined_at) {
                $status = 'declined';
                $status_color = 'label-danger';
            }

            if ($invitation->isExpired() && is_null($invitation->accepted_at) && is_null($invitation->declined_at)) {
                $status = 'expired';
                $status_color = 'label-warning';
            }

            $projectName = '';
            $invitableName = '';
            $invitableType = class_basename($invitation->invitable_type);

            if ($invitableType == 'Projects') {
                $project = Projects::find($invitation->invitable_id);
                $projectName = $project->name;
                $invitableName = $project->name;
            } elseif ($invitableType == 'Tasks') {
                $task = Tasks::find($invitation->invitable_id);
                $projectName = $task->project->name;
                $invitableName = $task->title;
            } elseif ($invitableType == 'Subtasks') {
                $subtask = Subtasks::find($invitation->invitable_id);
                $projectName = $subtask->task->project->name;
                $invitableName = $subtask->title;
            }

            return [
                'token' => $invitation->token,
                'invitable_type' => $invitableType,
                'project_name' => $projectName,
                'invitable_name' => $invitableName,
                'sender' => $invitation->sender->name ?? '',
                'created_at' => $invitation->created_at->diffForHumans(),
                'status' => $status,
                'status_color' => $status_color,
                'can_respond' => is_null($invitation->accepted_at) &&
                    is_null($invitation->declined_at) &&
                    $user->id === $invitation->recipient_id &&
                    !$invitation->isExpired(),
            ];
        })->values(), // reset the array keys
    ]);
}


   public function sentList(Request $request)
{
    $user = $request->user();
    $invitations = $user->sentInvitations()
        ->with(['recipient', 'invitable', 'assignedRole', 'department', 'sender'])
        ->latest()
        ->get();

    $filteredInvitations = $invitations->filter(function ($invitation) {
        if ($invitation->isEmployeeOnboarding()) {
            return true;
        }

        $type = class_basename($invitation->invitable_type);

        if ($type === 'Projects') {
            return Projects::find($invitation->invitable_id) !== null;
        } elseif ($type === 'Tasks') {
            $task = Tasks::find($invitation->invitable_id);
            return $task && $task->project;
        } elseif ($type === 'Subtasks') {
            $subtask = Subtasks::find($invitation->invitable_id);
            return $subtask && $subtask->task && $subtask->task->project;
        }

        return false;
    });

    return response()->json([
        'data' => $filteredInvitations->map(function ($invitation) {
            $status = 'pending';
            $status_color = 'label-primary';

            if ($invitation->accepted_at) {
                $status = 'accepted';
                $status_color = 'label-success';
            } elseif ($invitation->declined_at) {
                $status = 'declined';
                $status_color = 'label-danger';
            }

            if ($invitation->isExpired() && is_null($invitation->accepted_at) && is_null($invitation->declined_at)) {
                $status = 'expired';
                $status_color = 'label-warning';
            }

            $projectName = '';
            $invitableName = '';
            $invitableType = class_basename($invitation->invitable_type);

            if ($invitation->isEmployeeOnboarding()) {
                $invitableType = 'Employee';
                $projectName = $invitation->department?->dept_name ?: 'Workspace';
                $invitableName = $invitation->assignedRole?->role_name ?: 'Employee';
            } elseif ($invitableType === 'Projects') {
                $project = Projects::find($invitation->invitable_id);
                $projectName = $project->name;
                $invitableName = $project->name;
            } elseif ($invitableType === 'Tasks') {
                $task = Tasks::find($invitation->invitable_id);
                $projectName = $task->project->name;
                $invitableName = $task->title;
            } elseif ($invitableType === 'Subtasks') {
                $subtask = Subtasks::find($invitation->invitable_id);
                $projectName = $subtask->task->project->name;
                $invitableName = $subtask->title;
            }

            $manualMessage = $invitation->isEmployeeOnboarding()
                ? (($invitation->sender?->name ?? 'A team member').' invited you to join the team as '.$invitableName.'. Create your account: '.route('invitations.join', $invitation->token))
                : (($invitation->sender?->name ?? 'A team member').' invited you to collaborate on '.$invitableName.' in '.$projectName.'. Accept the secure invitation: '.route('invitations.join', $invitation->token));

            return [
                'id' => $invitation->id,
                'invitable_type' => $invitableType,
                'project_name' => $projectName,
                'invitable_name' => $invitableName,
                'recipient' => $invitation->recipient_display,
                'recipient_email' => $invitation->recipient?->email ?: $invitation->invitee_email,
                'created_at' => $invitation->created_at->diffForHumans(),
                'status' => $status,
                'status_color' => $status_color,
                'delivery_status' => $invitation->delivery_status ?: 'pending',
                'invitation_url' => route('invitations.join', $invitation->token),
                'manual_message' => $manualMessage,
            ];
        })->values(), // reset array indexes
    ]);
}





    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    public function send(Request $request)
    {
        $request->validate([
            'recipient_id' => 'required|exists:users,id',
            'invitable_type' => 'required|in:project,task',
            'invitable_id' => 'required',
            'role' => 'nullable|string'
        ]);

        try {
            $invitable = $request->invitable_type === 'project'
                ? Projects::findOrFail($request->invitable_id)
                : Tasks::findOrFail($request->invitable_id);

            $invitation = $this->invitationService->sendInvitation(
                $request->user(),
                User::find($request->recipient_id),
                $invitable,
                $request->role
            );

            // Send notification to recipient
            $invitation->recipient->notify(new InvitationNotification($invitation));

            return response()->json(['message' => 'Invitation sent successfully'], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function accept($token)
    {
        $invitation = Invitation::where('token', $token)
            ->with(['invitable', 'sender', 'recipient'])
            ->firstOrFail();

        // Check if user is authorized to accept this invitation
        if (auth()->id() !== $invitation->recipient_id) {
            return redirect()->route('invitations.index')->with('error', 'You are not authorized to accept this invitation.');
        }

        try {
            DB::beginTransaction();

            if ($this->invitationService->acceptInvitation($invitation)) {
                // Mark notification as read if coming from notification
                if (request()->has('notification_id')) {
                    auth()->user()->notifications()
                        ->where('id', request('notification_id'))
                        ->update(['read_at' => now()]);
                }

                DB::commit();


                $redirectId = $invitation->invitable_id;

                if(class_basename($invitation->invitable_type) == 'Projects') {
                    $redirectId = $invitation->invitable->id;
                }

                if(class_basename($invitation->invitable_type) == 'Tasks') {
                    $redirectId = $invitation->invitable->project_id;
                }

                if(class_basename($invitation->invitable_type) == 'Subtasks') {
                    $subtask = Subtasks::find($invitation->invitable_id);
                    $redirectId=$subtask->task->project->id;  
                }

                // Redirect based on invitation type
                $redirectRoute = match (class_basename($invitation->invitable_type)) {
                    'Projects' => route('projects.index'),
                    'Tasks' => route('projects.show', $redirectId),
                    'Subtasks' => route('projects.show', $redirectId),
                    default => route('projects.index'),
                };

                return redirect($redirectRoute)
                    ->with('success', 'Invitation accepted! You have been added to the ' . strtolower(class_basename($invitation->invitable_type)));
            }

            DB::rollBack();
            return redirect()->route('invitations.index')->with('error', 'Invitation could not be accepted.');
        } catch (\Exception $e) {
            DB::rollBack();
            logger()->error('Invitation acceptance failed: ' . $e->getMessage());
            return redirect()->route('invitations.index')->with('error', 'Failed to accept invitation. Please try again.');
        }
    }

    public function decline($token)
    {
        $invitation = Invitation::where('token', $token)->firstOrFail();
        if ($this->invitationService->declineInvitation($invitation)) {
            return redirect()->route('invitations.index')->with('success', 'Invitation declined.');
        }
        return redirect()->route('invitations.index')->with('error', 'Invitation could not be declined.');
    }

    
}
