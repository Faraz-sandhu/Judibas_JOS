<?php

namespace App\Modules\Pms\Http\Controllers;
use App\Http\Controllers\Controller;
use App\Modules\Pms\Services\PmsAccess;

use App\Modules\Pms\Events\ProjectChatMessageSent;
use App\Modules\Pms\Models\ProjectChat;
use App\Modules\Pms\Models\Projects;
use Illuminate\Http\Request;

class ProjectChatController extends Controller
{
    public function fetch($projectId)
    {
        $user = \App\Modules\Pms\Services\PmsAuth::user();

        // Load project with chatMessages and users (assignees)
        $project = Projects::with('users')->find($projectId);

        if (! $project) {
            abort(404, 'Project not found');
        }

        $this->ensureProjectAccess($project);

        // Get top-level chat messages with replies and sender info
        $messages = $project->chatMessages()
            ->whereNull('parent_id')
            ->with(['replies.sender', 'sender'])
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($message) {
                $message->can_delete=$message->user_id===auth()->id();
                $message->created_at_human = $message->created_at->diffForHumans();
                $message->replies->each(function ($reply) {
                    $reply->can_delete=$reply->user_id===auth()->id();
                    $reply->created_at_human = $reply->created_at->diffForHumans();
                });

                return $message;
            });

        return response()->json($messages);
    }

    public function send(Request $request, $projectId)
    {
        $user = \App\Modules\Pms\Services\PmsAuth::user();
        $project = Projects::with('users')->findOrFail($projectId);

        $this->ensureProjectAccess($project);

        $request->validate([
            'message' => ['required', 'string', 'max:5000'],
            'parent_id' => ['nullable', 'exists:pms_project_chats,id'],
        ]);
        if ($request->parent_id) {
            abort_unless(ProjectChat::whereKey($request->parent_id)->where('project_id', $project->id)->exists(), 422, 'Reply must belong to this project.');
        }

        $chat = ProjectChat::create([
            'project_id' => $projectId,
            'user_id' => auth()->id(),
            'author_name' => $user->name,
            'message' => $request->message,
            'parent_id' => $request->parent_id,
        ]);

        $chat->load(['sender', 'replies.sender']);
        $chat->created_at_human = $chat->created_at->diffForHumans();

        // Optional: broadcast event here

        try {
            \App\Modules\Pms\Services\PmsRealtime::publish(new ProjectChatMessageSent($chat));
        } catch (\Throwable $exception) {
            report($exception);
        }

        return response()->json(['status' => 'sent', 'message' => $chat]);
    }

    public function markMessagesAsSeen($projectId)
    {
        $project = Projects::findOrFail($projectId);
        $this->ensureProjectAccess($project);
        $userId = auth()->id() ?? 'super';
        $messages = ProjectChat::where('project_id', $projectId)
            ->where(function ($query) use ($userId) {
                $query->whereNull('seen_by')
                    ->orWhereJsonDoesntContain('seen_by', $userId);
            })
            ->get();
        foreach ($messages as $message) {
            $seenBy = $message->seen_by ?? [];
            $seenBy[] = $userId;
            $message->seen_by = array_unique($seenBy);

            $message->save();
        }

        return response()->json(['status' => 'success']);
    }

    public function delete($id)
    {
        $chat = ProjectChat::findOrFail($id);

        if ($chat->user_id !== auth()->id() || (auth()->id() === null && !request()->session()->get('judibas_admin'))) {
            abort(403, 'Unauthorized');
        }

        $event=new ProjectChatMessageSent($chat);$chat->delete();\App\Modules\Pms\Services\PmsRealtime::publish($event);

        return response()->json(['status' => 'deleted']);
    }

    public function ensureProjectAccess(Projects $project): void
    {
        $user = \App\Modules\Pms\Services\PmsAuth::user();
        if ($user->hasRole('admin')) {
            return;
        }

        abort_unless(
            $project->users()->where('users.id', $user->id)->exists()
            || $project->tasks()->whereHas('users', fn ($query) => $query->where('users.id', $user->id))->exists()
            || $project->tasks()->whereHas('subtasks.users', fn ($query) => $query->where('users.id', $user->id))->exists(),
            403,
            'Unauthorized access'
        );
    }
}
