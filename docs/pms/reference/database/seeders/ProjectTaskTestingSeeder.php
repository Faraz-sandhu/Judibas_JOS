<?php

namespace Database\Seeders;

use App\Models\Projects;
use App\Models\Tasks;
use App\Models\User;
use App\Models\Workflow;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProjectTaskTestingSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $this->removeExistingTaskData();

            $creator = User::whereHas('roles', fn ($query) => $query->where('role_key', 'admin'))->first()
                ?? User::firstOrFail();

            $projects = Projects::query()
                ->where('is_general', false)
                ->with([
                    'departments.users:id,name',
                    'users:id,name',
                    'workflows.columns',
                ])
                ->orderBy('id')
                ->get();

            $created = 0;
            foreach ($projects as $project) {
                $departments = $project->departments->isNotEmpty()
                    ? $project->departments
                    : collect([null]);

                foreach ($departments as $departmentIndex => $department) {
                    foreach ([0, 1] as $taskIndex) {
                        $status = $taskIndex === 0 ? 'pending' : 'in_progress';
                        $workflow = $project->workflows->first()
                            ?? Workflow::query()->where('is_default', true)->with('columns')->first();
                        $column = $workflow?->columns
                            ->first(fn ($item) => $status === 'pending' ? $item->is_initial : ! $item->is_initial && ! $item->is_completed)
                            ?? $workflow?->columns->first();
                        $details = $this->taskDetails(
                            $project->name,
                            $department?->dept_name,
                            $departmentIndex,
                            $taskIndex
                        );

                        $task = Tasks::create([
                            'project_id' => $project->id,
                            'department_id' => $department?->id,
                            'workflow_id' => $workflow?->id,
                            'workflow_column_id' => $column?->id,
                            'sprint_id' => null,
                            'title' => $details['title'],
                            'description' => $details['description'],
                            'due_date' => now()->addDays(5 + $departmentIndex * 3 + $taskIndex * 4),
                            'original_estimate_minutes' => $taskIndex === 0 ? 240 : 420,
                            'status' => $status,
                            'position' => $taskIndex,
                            'priority' => $taskIndex === 0 ? 1 : 2,
                            'approval' => 'approved',
                            'created_by' => $creator->id,
                        ]);

                        $assignee = $department?->users->first(fn ($user) => $project->users->contains('id', $user->id))
                            ?? $department?->users->first()
                            ?? $project->users->first();
                        if ($assignee) {
                            $task->users()->attach($assignee->id, [
                                'assigned_by' => $creator->id,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }
                        $created++;
                    }
                }
            }

            $this->command?->info("Task test data reset complete: {$created} realistic tasks created across {$projects->count()} projects.");
        });
    }

    private function removeExistingTaskData(): void
    {
        DB::table('task_handoffs')->delete();
        DB::table('invitations')->whereIn('invitable_type', [
            Tasks::class,
            'App\\Models\\Subtasks',
        ])->delete();
        DB::table('submissions')->whereIn('submittable_type', [
            Tasks::class,
            'App\\Models\\Subtasks',
        ])->delete();
        DB::table('notifications')->where(function ($query) {
            $query->where('data', 'like', '%"task"%')
                ->orWhere('data', 'like', '%"task_id"%')
                ->orWhere('data', 'like', '%"sub_task_id"%');
        })->delete();

        Tasks::query()->delete();
    }

    private function taskDetails(string $project, ?string $department, int $departmentIndex, int $taskIndex): array
    {
        $work = [
            'Development' => [
                ['Implement responsive customer dashboard', 'Build the approved dashboard screens with reusable components, responsive layouts, and validation.'],
                ['Integrate project workflow API', 'Connect the project interface to the workflow endpoints and handle loading, success, and error states.'],
            ],
            'Graphic Design' => [
                ['Prepare polished interface mockups', 'Create production-ready desktop and mobile mockups using the approved visual system.'],
                ['Finalize reusable design components', 'Review spacing, typography, colors, and component states before developer handoff.'],
            ],
            'SEO' => [
                ['Complete technical SEO audit', 'Review crawlability, metadata, internal linking, structured data, and page performance.'],
                ['Prepare keyword and content plan', 'Map priority search terms to landing pages and document actionable content recommendations.'],
            ],
        ];
        $fallback = [
            ['Review project requirements', 'Review the latest client requirements, document open questions, and confirm the delivery scope.'],
            ['Prepare delivery progress update', 'Summarize completed work, current blockers, owners, and the next delivery milestones.'],
        ];
        $choices = $work[$department] ?? $fallback;
        [$title, $description] = $choices[($taskIndex + $departmentIndex) % count($choices)];

        return [
            'title' => $title.' — '.$project,
            'description' => $description.' This task belongs specifically to '.$project.($department ? ' and the '.$department.' department.' : '.'),
        ];
    }
}
