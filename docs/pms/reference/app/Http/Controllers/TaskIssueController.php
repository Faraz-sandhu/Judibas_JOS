<?php

namespace App\Http\Controllers;

use App\Models\TaskAttachment;
use App\Models\TaskComment;
use App\Models\Tasks;
use App\Services\IssueNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class TaskIssueController extends Controller
{
    public function __construct(protected IssueNotificationService $issueNotifications) {}

    public function comment(Request $request, Tasks $task): JsonResponse
    {
        $this->ensureAccess($task);
        $validated = $request->validate(['body' => ['required', 'string', 'max:5000']]);
        $comment = $task->comments()->create($validated + ['user_id' => auth()->id()]);
        $this->issueNotifications->commented($task);

        return response()->json(['message' => 'Comment added.', 'comment' => $comment->load('user:id,name,profile_img')], 201);
    }

    public function deleteComment(TaskComment $comment): JsonResponse
    {
        $this->ensureAccess($comment->task);
        abort_unless(auth()->id() === $comment->user_id || auth()->user()->hasRole('admin'), 403);
        $comment->delete();

        return response()->json(['message' => 'Comment deleted.']);
    }

    public function attachments(Request $request, Tasks $task): JsonResponse
    {
        $this->ensureAccess($task);
        $validated = $request->validate([
            'attachments' => ['required', 'array', 'max:10'],
            'attachments.*' => ['file', 'max:10240', 'mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,csv,txt,jpg,jpeg,png,webp,gif,zip'],
        ]);

        $created = collect($validated['attachments'])->map(function ($file) use ($task) {
            $path = $file->store("tasks/{$task->id}", 'public');

            return $task->attachments()->create([
                'user_id' => auth()->id(),
                'original_name' => $file->getClientOriginalName(),
                'path' => $path,
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
            ]);
        });
        $this->issueNotifications->send($task, 'updated', $created->count().' attachment(s) added.');

        return response()->json(['message' => 'Attachments uploaded.', 'attachments' => $created], 201);
    }

    public function deleteAttachment(TaskAttachment $attachment): JsonResponse
    {
        $this->ensureAccess($attachment->task);
        abort_unless(auth()->id() === $attachment->user_id || auth()->user()->hasRole('admin'), 403);
        $sharedPath = $attachment->path;
        $attachment->delete();
        if (! TaskAttachment::where('path', $sharedPath)->exists()) {
            Storage::disk('public')->delete($sharedPath);
        }

        return response()->json(['message' => 'Attachment deleted.']);
    }

    private function ensureAccess(Tasks $task): void
    {
        Gate::authorize('project-view');
        $user = auth()->user();
        if ($user->hasRole('admin')) {
            return;
        }
        if ($user->hasRole('project_manager') || $user->hasRole('team_leader')) {
            abort_unless($task->project->users()->where('users.id', $user->id)->exists(), 403);

            return;
        }
        abort_unless(
            $task->users()->where('users.id', $user->id)->exists()
            || $task->subtasks()->whereHas('users', fn ($query) => $query->where('users.id', $user->id))->exists(),
            403
        );
    }
}
