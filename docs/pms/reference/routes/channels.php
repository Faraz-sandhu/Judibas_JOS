<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;

});

Broadcast::channel('task-notification.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});
Broadcast::channel('status-update.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

Broadcast::channel('project-notification.{userId}', function($user, $userId){
    return (int) $user->id === (int) $userId;
});

Broadcast::channel('task-approval-notification.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

Broadcast::channel('global-update.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

Broadcast::channel('user-status', function (User $user)
{
    return ['id' => $user->id, 'name'=> $user->name];
});
Broadcast::channel('user-task-timer.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('task-reminder-notification.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});


Broadcast::channel('chatify', function ($user) {
    return true;
});

Broadcast::channel('chatify.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('unseen-messages.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});


Broadcast::channel('project.chat.{projectId}', function ($user, $projectId) {
    $project = \App\Models\Projects::with('users')->find($projectId);
    if (! $project) return false;
    return $user->hasRole('admin') || $project->users->contains('id', $user->id);
});
