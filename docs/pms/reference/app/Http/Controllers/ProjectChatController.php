<?php

namespace App\Http\Controllers;

use App\Events\ProjectChatMessageSent;
use App\Models\ProjectChat;
use App\Models\Projects;
use Illuminate\Http\Request;

class ProjectChatController extends Controller
{
    public function fetch($projectId)
    {
        $user = auth()->user();

        // Load project with chatMessages and users (assignees)
        $project = Projects::with(['chatMessages', 'users'])->find($projectId);

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
                $message->created_at_human = $message->created_at->diffForHumans();
                $message->replies->each(function ($reply) {
                    $reply->created_at_human = $reply->created_at->diffForHumans();
                });

                return $message;
            });

        return response()->json($messages);
    }

    public function send(Request $request, $projectId)
    {
        $user = auth()->user();
        $project = Projects::with('users')->findOrFail($projectId);

        $this->ensureProjectAccess($project);

        $request->validate([
            'message' => ['required', 'string', 'max:5000'],
            'parent_id' => ['nullable', 'exists:project_chats,id'],
        ]);
        if ($request->parent_id) {
            abort_unless(ProjectChat::whereKey($request->parent_id)->where('project_id', $project->id)->exists(), 422, 'Reply must belong to this project.');
        }

        $chat = ProjectChat::create([
            'project_id' => $projectId,
            'user_id' => auth()->id(),
            'message' => $request->message,
            'parent_id' => $request->parent_id,
        ]);

        $chat->load(['sender', 'replies.sender']);
        $chat->created_at_human = $chat->created_at->diffForHumans();

        // Optional: broadcast event here

        try {
            broadcast(new ProjectChatMessageSent($chat));
        } catch (\Throwable $exception) {
            report($exception);
        }

        return response()->json(['status' => 'sent', 'message' => $chat]);
    }

    public function markMessagesAsSeen($projectId)
    {
        $project = Projects::findOrFail($projectId);
        $this->ensureProjectAccess($project);
        $userId = auth()->id();
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

        if ($chat->user_id !== auth()->id()) {
            abort(403, 'Unauthorized');
        }

        $chat->delete();

        return response()->json(['status' => 'deleted']);
    }

    private function ensureProjectAccess(Projects $project): void
    {
        $user = auth()->user();
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
