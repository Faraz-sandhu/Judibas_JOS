<?php

namespace App\Modules\Pms\Console\Commands;

use App\Modules\Pms\Models\Tasks;
use Illuminate\Console\Command;

class DeleteApprovedTasks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pms:delete-approved-tasks';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete tasks and subtasks where approval is approved';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $tasks = Tasks::where('approval', 'approved')->get();

        foreach ($tasks as $task) {
            $allSubtasksApproved = $task->subtasks()->where('approval', '!=', 'approved')->exists();

            if ($allSubtasksApproved) {
                continue;
            }
            $task->subtasks()->delete();

            $task->delete();

            $this->info("Task ID {$task->id} and its subtasks have been deleted successfully.");
        }
    }

}
