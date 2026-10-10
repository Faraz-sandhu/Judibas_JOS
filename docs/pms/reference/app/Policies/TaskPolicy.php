<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Tasks;

class TaskPolicy
{
    /**
     * Create a new policy instance.
     */
    public function __construct()
    {
        //
    }

    public function assign(User $user, Tasks $task)
{
    // Admins and PMs can always assign
    if ($user->hasRole('admin') || $user->hasRole('project_manager')) {
        return true;
    }
    
    // Team leaders can only assign to their department
    if ($user->hasRole('team_leader')) {
        $project = $task->project;
        return $project->users()->where('user_id', $user->id)->exists();
    }
    
    return false;
}
}
