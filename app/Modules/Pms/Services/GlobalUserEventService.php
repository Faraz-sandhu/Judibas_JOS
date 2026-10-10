<?php

namespace App\Modules\Pms\Services;

use App\Modules\Pms\Events\GlobalUserEvent;
use App\Modules\Pms\Models\User;

class GlobalUserEventService
{
    public function broadcastToUsers($users, $projectId, $taskId, $subtaskId, $status)
    {

        $allUsers = collect($users);

        $adminUsers = User::whereHas('roles', function ($query) {
            $query->where('role_key', 'admin');
        })->get();

        $allUsers = $allUsers->merge($adminUsers)->unique('id');

        try {\App\Modules\Pms\Services\PmsRealtime::publish(new GlobalUserEvent(['project_id'=>$projectId,'task_id'=>$taskId,'sub_task_id'=>$subtaskId,'status'=>$status,'user_id'=>'super']));} catch (\Throwable $exception) {report($exception);}
        foreach ($allUsers as $user) {
            if ($user) {
                try { \App\Modules\Pms\Services\PmsRealtime::publish(new GlobalUserEvent([
                    'project_id' => $projectId,
                    'task_id' => $taskId,
                    'sub_task_id' => $subtaskId,
                    'status' => $status,
                    'user_id' => $user->id,
                ])); } catch (\Throwable $exception) {report($exception);}
            }
        }
    }
}
