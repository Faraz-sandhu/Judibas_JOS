<?php

namespace App\Services;

use App\Events\GlobalUserEvent;
use App\Models\User;

class GlobalUserEventService
{
    public function broadcastToUsers($users, $projectId, $taskId, $subtaskId, $status)
    {

        $allUsers = collect($users);

        $adminUsers = User::whereHas('roles', function ($query) {
            $query->where('role_key', 'admin');
        })->get();

        $allUsers = $allUsers->merge($adminUsers)->unique('id');

        foreach ($allUsers as $user) {
            if ($user) {
                broadcast(new GlobalUserEvent([
                    'project_id' => $projectId,
                    'task_id' => $taskId,
                    'sub_task_id' => $subtaskId,
                    'status' => $status,
                    'user_id' => $user->id,
                ]));
            }
        }
    }
}
