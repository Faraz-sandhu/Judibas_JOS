<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Projects;
use App\Models\Tasks;
use App\Models\TimerLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class GeneralTaskController extends Controller
{
    use AuthorizesRequests;
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $project = Projects::where('is_general', true)->first();
        $id = $project->id;
        $this->authorize('project-view');
        $user = Auth::user();
        // if ($user->hasRole('admin' || 'project_manager')) {
        //     $project = Projects::with(['tasks.subtasks.users', 'tasks.users'])->findOrFail($id);
        // } else {
        //     $project = Projects::with([
        //         'tasks' => function ($query) use ($user) {
        //             $query->whereHas('users', function ($q) use ($user) {
        //                 $q->where('user_id', $user->id);
        //             })->with([
        //                 'subtasks' => function ($subQuery) use ($user) {
        //                     $subQuery->whereHas('users', function ($q) use ($user) {
        //                         $q->where('user_id', $user->id);
        //                     })->with('users');
        //                 },
        //                 'users'
        //             ]);
        //         }
        //     ])->findOrFail($id);
        // }

        if ($user->hasRole('admin') || $user->hasRole('project_manager') || $user->hasRole('team_leader')) {
            $project = Projects::with(['tasks.subtasks.users', 'tasks.users'])->findOrFail($id);
        } else {
            $project = Projects::with([
                'tasks' => function ($query) use ($user) {
                    $query->where(function ($q) use ($user) {
                        // Tasks where the user is directly assigned
                        $q->whereHas('users', function ($subQ) use ($user) {
                            $subQ->where('user_id', $user->id);
                        })
                            // OR tasks that have subtasks where the user is assigned
                            ->orWhereHas('subtasks', function ($subQ) use ($user) {
                                $subQ->whereHas('users', function ($subSubQ) use ($user) {
                                    $subSubQ->where('user_id', $user->id);
                                });
                            });
                    })->with([
                        'subtasks' => function ($subQuery) use ($user) {
                            // Only load subtasks where the user is assigned
                            $subQuery->whereHas('users', function ($q) use ($user) {
                                $q->where('user_id', $user->id);
                            })->with('users');
                        },
                        'users'
                    ]);
                }
            ])->findOrFail($id);
        }

        if ($user->hasRole('admin' || 'project_manager')) {
            $asignee = User::whereHas('roles', function ($query) {
                $query->whereNotIn('role_key', ['admin']);
            })->with(['roles' => function ($query) {
                $query->select('role_key', 'role_name');
            }])->get();
        } else {
            $asignee = null;
        }
        return view('dashboard.projects.tasks', compact('project', 'asignee'));
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
}
