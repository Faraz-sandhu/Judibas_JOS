<?php

namespace App\Modules\Pms\Http\Controllers;
use App\Http\Controllers\Controller;
use App\Modules\Pms\Services\PmsAccess;

use Illuminate\Http\Request;
use App\Modules\Pms\Services\SubmissionService;
use App\Modules\Pms\Models\Projects;
use App\Modules\Pms\Models\Tasks;
use App\Modules\Pms\Models\Subtasks;
use App\Modules\Pms\Models\Submission;

class SubmissionController extends Controller
{
    protected $submissionService;

    public function __construct(SubmissionService $submissionService)
    {
        $this->submissionService = $submissionService;
    }

    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
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


    public function store(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'type' => 'required',
            'main_id' => 'required',
            'file' => 'nullable|mimes:pdf,doc,docx,zip,rar,jpg,jpeg,png|max:10240',
            'title' => 'required',
            'description' => 'nullable',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()]);
        }
        $type = $request->type;
        $id = $request->main_id;
        $submittable = $this->resolveSubmittable($type, $id);
        $this->authorizeSubmission($submittable);
        $submission = $this->submissionService->createSubmission(
            $submittable,
            $request->only(['description', 'status', 'title']),
            $request->file('file')
        );

        return response()->json(['status' => true, 'message' => 'Submission successfully.']);
        // return redirect()->back()->with('success', 'Submission created successfully.');
    }

    public function update(Request $request, Submission $submission)
    {
        $this->authorizeSubmission($submission->submittable);

        $submission = $this->submissionService->updateSubmission(
            $submission,
            $request->only(['description', 'status']),
            $request->file('file')
        );

        return redirect()->back()->with('success', 'Submission updated successfully.');
    }

    protected function resolveSubmittable($type, $id)
    {
        switch ($type) {
            case 'project':
                return Projects::findOrFail($id);
            case 'task':
                return Tasks::findOrFail($id);
            case 'subtask':
                return Subtasks::findOrFail($id);
            default:
                abort(404, 'Invalid submission type');
        }
    }

    protected function authorizeResourceAccess($resource, bool $management = false): void
    {
        $user = \App\Modules\Pms\Services\PmsAuth::user();

        if ($user->hasRole('admin')) {
            return;
        }

        $task = $resource instanceof Subtasks ? $resource->task : ($resource instanceof Tasks ? $resource : null);
        $project = $resource instanceof Projects ? $resource : $task?->project;

        abort_unless($project, 404);

        if ($user->hasRole('project_manager')) {
            abort_unless($project->users()->where('users.id', $user->id)->exists(), 403);

            return;
        }

        if ($user->hasRole('team_leader')) {
            $hasProjectAccess = $project->users()->where('users.id', $user->id)->exists();
            $hasDepartmentAccess = ! $task || $user->departments()
                ->where('pms_departments.id', $task->department_id)
                ->exists();

            abort_unless($hasProjectAccess && $hasDepartmentAccess, 403);

            return;
        }

        abort_if($management, 403);
        abort_unless($resource->users()->where('users.id', $user->id)->exists(), 403);
    }

    protected function authorizeSubmission($submittable)
    {
        // Check if user is assigned to the submittable entity
        $userId = auth()->id();
        $isAssigned = $submittable->users()->where('user_id', $userId)->exists();
        if (!$isAssigned) {
            abort(403, 'You are not authorized to submit to this');
        }
    }


    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }



    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        if (\App\Modules\Pms\Services\PmsAuth::user()->hasRole('admin')) {
            $submission = Submission::findOrFail($id);
            $this->authorizeResourceAccess($submission->submittable, true);
            $submission->delete();
            return response()->json(['status' => true, 'message' => 'Submission deleted successfully.']);
        } else {
            return response()->json(['status' => false, 'message' => 'You are not authorized to delete this submission.']);
        }
    }


    public function getProjectOrTaskDetails(Request $request)
    {
        $type = $request->input('type');
        $id = $request->input('id');
        if (!in_array($type, ['project', 'task', 'subtask'])) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid type provided',
            ], 400);
        }

        try {
            $data = null;
            $message = '';

            if ($type === 'project') {
                $data = Projects::with(['tasks', 'tasks.subtasks'])->findOrFail($id);
                $this->authorizeResourceAccess($data);

                // Count incomplete tasks
                $incompleteTasks = $data->tasks->where('status', '!=', 'completed')->count();

                // Count incomplete subtasks across all tasks
                $incompleteSubtasks = $data->tasks->reduce(function ($carry, $task) {
                    return $carry + $task->subtasks->where('status', '!=', 'completed')->count();
                }, 0);

                if ($incompleteTasks > 0 || $incompleteSubtasks > 0) {
                    $message = "This project has {$incompleteTasks} task(s) and {$incompleteSubtasks} subtask(s) that are not completed";
                } else {
                    $message = 'All tasks and subtasks in this project are completed.';
                }
            } elseif ($type === 'task') {
                $data = Tasks::with('subtasks')->findOrFail($id);
                $this->authorizeResourceAccess($data);

                // Check task status
                $taskIncomplete = $data->status !== 'completed';
                // Count incomplete subtasks
                $incompleteSubtasks = $data->subtasks->where('status', '!=', 'completed')->count();

                if ($taskIncomplete || $incompleteSubtasks > 0) {
                    $taskMessage = $taskIncomplete ? 'This task is not completed' : 'This task is completed';
                    $subtaskMessage = $incompleteSubtasks > 0 ? " and has {$incompleteSubtasks} subtask(s) that are not completed." : '.';
                    $message = $taskMessage . $subtaskMessage;
                } else {
                    $message = 'This task and all its subtasks are completed.';
                }
            } elseif ($type === 'subtask') {
                $data = Subtasks::findOrFail($id);
                $this->authorizeResourceAccess($data);

                $message = $data->status !== 'completed'
                    ? 'This subtask is not completed.'
                    : 'This subtask is completed.';
            }
            if (\App\Modules\Pms\Services\PmsAuth::user()->hasRole('admin') || \App\Modules\Pms\Services\PmsAuth::user()->hasRole('project_manager') || \App\Modules\Pms\Services\PmsAuth::user()->hasRole('team_leader')) {
                $submissionCount = $data->submissions()->count();
            } else {
                $submissionCount = $data->submissions()->where('user_id', \App\Modules\Pms\Services\PmsAuth::user()->id)->count();
            }

            return response()->json([
                'status' => true,
                'data' => $data,
                'submission_count' => $submissionCount ?? 0,
                'message' => $message,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => false,
                'message' => ucfirst($type) . ' not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'An error occurred: ' . $e->getMessage(),
            ], 500);
        }
    }

    // public function getSubmission(Request $request)
    // {
    //     $type = $request->type;
    //     if ($type == 'project') {
    //         $type = 'App\Modules\Pms\Models\Projects';
    //     } elseif ($type == 'task') {
    //         $type = 'App\Modules\Pms\Models\Tasks';
    //     } elseif ($type == 'subtask') {
    //         $type = 'App\Modules\Pms\Models\Subtasks';
    //     }
    //     $id = $request->id;
    //     if (\App\Modules\Pms\Services\PmsAuth::user()->hasRole('admin')) {
    //         $data = Submission::where('submittable_type', $type)->where('submittable_id', $id)
    //             ->with('user')->get();
    //     } else {
    //         $data = Submission::where('submittable_type', $type)->where('submittable_id', $id)
    //             ->where('user_id', \App\Modules\Pms\Services\PmsAuth::user()->id)->with('user')->get();
    //     }
    //     return response()->json([
    //         'status' => true,
    //         'data' => $data,
    //         'message' => 'data fetched successfully',
    //     ]);
    // }

    public function getSubmission(Request $request)
    {
        $type = $request->type;
        if ($type == 'project') {
            $type = 'App\Modules\Pms\Models\Projects';
        } elseif ($type == 'task') {
            $type = 'App\Modules\Pms\Models\Tasks';
        } elseif ($type == 'subtask') {
            $type = 'App\Modules\Pms\Models\Subtasks';
        }

        $id = $request->id;
        $resource = $this->resolveSubmittable($request->type, $id);
        $this->authorizeResourceAccess($resource);

        $query = Submission::where('submittable_type', $type)->where('submittable_id', $id)
            ->with('user');

        if (!\App\Modules\Pms\Services\PmsAuth::user()->hasRole('admin') && !\App\Modules\Pms\Services\PmsAuth::user()->hasRole('project_manager') && !\App\Modules\Pms\Services\PmsAuth::user()->hasRole('team_leader')) {
            $query->where('user_id', \App\Modules\Pms\Services\PmsAuth::user()->id);
        }
        $submissions = $query->get();

        if ($request->expectsJson()) return response()->json(['submissions'=>$submissions,'can_submit'=>auth()->id() !== null && $resource->users()->where('users.id',auth()->id())->exists(),'can_manage'=>\App\Modules\Pms\Services\PmsAuth::user()->hasRole('admin') || \App\Modules\Pms\Services\PmsAuth::user()->hasRole('project_manager') || \App\Modules\Pms\Services\PmsAuth::user()->hasRole('team_leader'),'can_delete'=>\App\Modules\Pms\Services\PmsAuth::user()->hasRole('admin')]);
        // Generate table HTML
        $tableHtml = '<div class="table-responsive">';
        $tableHtml .= '<table class="table table-bordered table-striped table-hover table-sm">';
        $tableHtml .= '<thead><tr>';
        $tableHtml .= '<th>ID</th>';
        $tableHtml .= '<th>Title</th>';
        $tableHtml .= '<th>Description</th>';
        $tableHtml .= '<th>Attachment</th>';
        $tableHtml .= '<th>Submitted By</th>';
        $tableHtml .= '<th>Status</th>';
        $tableHtml .= '<th>Date</th>';
        if (\App\Modules\Pms\Services\PmsAuth::user()->hasRole('admin') || \App\Modules\Pms\Services\PmsAuth::user()->hasRole('project_manager') || \App\Modules\Pms\Services\PmsAuth::user()->hasRole('team_leader')) {
            $tableHtml .= '<th>Actions</th>';
        }
        $tableHtml .= '</tr></thead><tbody>';

        if ($submissions->isEmpty()) {
            $tableHtml .= '<tr><td colspan="8" class="text-center">No submissions found.</td></tr>';
        } else {
            foreach ($submissions as $submission) {
                $tableHtml .= '<tr>';
                $tableHtml .= '<td>' . $submission->id . '</td>';
                $tableHtml .= '<td>' . $submission->title . '</td>';
                $tableHtml .= '<td>' . htmlspecialchars($submission->description ?? 'No details') . '</td>';
                $tableHtml .= '<td>' . ($submission->file_path ? '<a href="' . asset($submission->file_path) . '" target="_blank">Attachment</a>' : 'N/A') . '</td>';
                $tableHtml .= '<td clas="text-center">
                <ul class="list-unstyled m-0 avatar-group d-flex align-items-center"><li data-bs-toggle="tooltip" data-popup="tooltip-custom" 
                data-bs-placement="top" data-user-id="3" data-project-id="1" class=" avatar avatar-xs pull-up" 
                title="' . $submission->user->name . '">
                <img src="' . $submission->user->profile_img . '" alt="Avatar" class="rounded-circle">
                </li></ul></td>';
                $badge = '<span class="badge submission-status-badge bg-label-' . ($submission->status === 'pending' ? 'warning' : ($submission->status === 'approved' ? 'success' : 'danger')) . '"
                ' . (\App\Modules\Pms\Services\PmsAuth::user()->hasRole('admin') || \App\Modules\Pms\Services\PmsAuth::user()->hasRole('project_manager') || \App\Modules\Pms\Services\PmsAuth::user()->hasRole('team_leader')  ? 'style="cursor:pointer;"' : '') . '>' . ucfirst($submission->status) . '</span>';
                $tableHtml .= '<td>' . $badge . '</td>';
                $tableHtml .= '<td>' . $submission->created_at->format('Y-m-d') . '</td>';
                if (\App\Modules\Pms\Services\PmsAuth::user()->hasRole('admin') || \App\Modules\Pms\Services\PmsAuth::user()->hasRole('project_manager') || \App\Modules\Pms\Services\PmsAuth::user()->hasRole('team_leader')) {
                    $tableHtml .= '<td> <a href="javascript:void(0)" class="btn text-danger text-decoration-none delete-submission"  data-submission-id="' . $submission->id . '">
                    <i class="fas fa-trash"></i></a></td>';
                }
                $tableHtml .= '</tr>';
            }
        }
        $tableHtml .= '</tbody></table>';
        $tableHtml .= '</div>';

        return response()->json([
            'status' => true,
            'data' => $tableHtml,
            'message' => 'Data fetched successfully',
        ]);
    }

    public function updateStatus(Request $request)
    {
        // Validate input
        $request->validate([
            'id' => 'required|exists:pms_submissions,id',
            'status' => 'required|in:pending,approved,rejected',
        ]);

        // Check if user is admin
        if (!\App\Modules\Pms\Services\PmsAuth::user()->hasRole('admin') && !\App\Modules\Pms\Services\PmsAuth::user()->hasRole('project_manager') && !\App\Modules\Pms\Services\PmsAuth::user()->hasRole('team_leader')) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized: Only admins can update submission status.',
            ], 403);
        }

        // Find and update the submission
        $submission = Submission::findOrFail($request->id);
        $this->authorizeResourceAccess($submission->submittable, true);
        $submission->update(['status' => $request->status]);

        return response()->json([
            'status' => true,
            'message' => 'Status updated successfully',
        ]);
    }
}
