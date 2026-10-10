<?php

namespace App\Modules\Pms\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Pms\Models\Projects;
use App\Modules\Pms\Services\PmsAccess;
use App\Modules\Pms\Services\PmsAuth;
use Illuminate\Http\Request;

class ProjectTaskListController extends Controller
{
    public function __invoke(Request $request, Projects $project)
    {
        PmsAccess::requirePermission($request, 'project-view');
        $user = PmsAuth::user();
        $admin = $user->hasRole('admin');
        abort_unless($admin || $project->users()->where('users.id', $user->id)->exists(), 403);
        $tasks = $project->tasks()->with('users:id,name,profile_img');
        if (!$admin && !$user->hasRole('project_manager')) {
            $tasks->where(fn ($q) => $q->whereHas('users', fn ($u) => $u->where('users.id', $user->id))
                ->orWhereHas('subtasks.users', fn ($u) => $u->where('users.id', $user->id)));
        }
        return response()->json(['data' => ['tasks' => $tasks->orderBy('id')->get()]]);
    }
}
