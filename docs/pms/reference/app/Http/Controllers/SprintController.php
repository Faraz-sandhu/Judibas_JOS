<?php

namespace App\Http\Controllers;

use App\Models\Projects;
use App\Models\Sprint;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SprintController extends Controller
{
    use AuthorizesRequests;

    public function store(Request $request, Projects $project): JsonResponse
    {
        $this->authorize('project-edit');
        $this->ensureProjectAccess($project);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'goal' => ['nullable', 'string', 'max:1000'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['nullable', Rule::in(['planned', 'active'])],
        ]);

        $sprint = DB::transaction(function () use ($validated, $project) {
            $status = $validated['status'] ?? 'planned';

            return $project->sprints()->create($validated + [
                'status' => $status,
                'created_by' => auth()->id(),
            ]);
        });

        return response()->json(['message' => 'Sprint created successfully.', 'sprint' => $sprint], 201);
    }

    public function update(Request $request, Sprint $sprint): JsonResponse
    {
        $this->authorize('project-edit');
        $this->ensureProjectAccess($sprint->project);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'goal' => ['nullable', 'string', 'max:1000'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['sometimes', Rule::in(['planned', 'active', 'completed'])],
        ]);

        DB::transaction(fn () => $sprint->update($validated));

        return response()->json(['message' => 'Sprint updated successfully.', 'sprint' => $sprint->fresh()]);
    }

    public function destroy(Sprint $sprint): JsonResponse
    {
        $this->authorize('project-edit');
        $this->ensureProjectAccess($sprint->project);

        DB::transaction(function () use ($sprint) {
            $sprint->tasks()->update(['sprint_id' => null]);
            $sprint->delete();
        });

        return response()->json(['message' => 'Sprint deleted; its tasks were moved to the backlog.']);
    }

    private function ensureProjectAccess(Projects $project): void
    {
        $user = auth()->user();
        if (! $user->hasRole('admin') && ! $project->users()->where('users.id', $user->id)->exists()) {
            abort(403, 'You do not have access to this project.');
        }
    }
}
