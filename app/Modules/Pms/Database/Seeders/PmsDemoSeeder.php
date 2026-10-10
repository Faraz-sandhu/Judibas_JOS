<?php

namespace App\Modules\Pms\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Optional local demonstration data. Never called by the production seeder. */
class PmsDemoSeeder extends Seeder
{
    private function record(string $table, array $key, array $values = []): mixed
    {
        $existing = DB::table($table)->where($key)->first();
        if ($existing) return $existing->id ?? null;
        $timestamps = array_intersect_key(['created_at' => now(), 'updated_at' => now()], array_flip(\Illuminate\Support\Facades\Schema::getColumnListing($table)));
        if (!\Illuminate\Support\Facades\Schema::hasColumn($table, 'id')) {
            DB::table($table)->insert($key + $values + $timestamps);
            return null;
        }
        return DB::table($table)->insertGetId($key + $values + $timestamps);
    }

    public function run(): void
    {
        DB::transaction(function () {
            $users = [];
            $departments = [];
            $tasks = [];
            $projects = [];
            foreach ([1, 2] as $i) {
                $department = $this->record('pms_departments', ['dept_name' => $i === 1 ? 'Engineering' : 'Creative Design'], ['description' => 'Sample department for PMS functionality checks.', 'status' => 1]);
                $departments[] = $department;
                $role = DB::table('pms_roles')->where('role_key', $i === 1 ? 'project_manager' : 'developer')->value('id');
                if (!$role) throw new \RuntimeException('Run the PMS foundation seeder before adding demo data.');
                $user = $this->record('users', ['email' => $i === 1 ? 'pms.manager@example.test' : 'pms.employee@example.test'], ['name' => $i === 1 ? 'Ayesha Khan' : 'Hamza Malik', 'surname' => '', 'designation' => $i === 1 ? 'Project Manager' : 'Developer', 'password' => Hash::make('DemoPass123!'), 'status' => 1, 'is_active' => true, 'email_verified_at' => now()]);
                $users[] = $user;
                $this->record('user_product_access', ['user_id' => $user, 'product_slug' => 'projects']);
                $this->record('pms_role_users', ['user_id' => $user, 'role_id' => $role]);
                $this->record('pms_user_departments', ['user_id' => $user, 'dept_id' => $department]);
                $company = $this->record('pms_companies', ['name' => ($i === 1 ? 'Cedar Retail' : 'Northstar Foods')], ['email' => 'client'.$i.'@example.test', 'website' => 'https://example.com', 'status' => true]);
                $workflow = $this->record('pms_workflows', ['name' => ($i === 1 ? 'Engineering Delivery' : 'Design Delivery')], ['department_id' => $department, 'description' => 'Sample delivery workflow', 'is_active' => true, 'is_default' => false, 'transition_mode' => 'any']);
                $columns = [];
                foreach (['To Do', 'In Progress', 'Review', 'Completed'] as $position => $name) {
                    $columns[] = $this->record('pms_workflow_columns', ['workflow_id' => $workflow, 'name' => $name], ['position' => $position, 'color' => ['secondary', 'primary', 'warning', 'success'][$position], 'is_initial' => $position === 0, 'is_completed' => $position === 3]);
                }
                $project = $this->record('pms_projects', ['name_key' => ($i === 1 ? 'cedar customer portal' : 'northstar brand refresh')], ['name' => ($i === 1 ? 'Cedar Customer Portal' : 'Northstar Brand Refresh'), 'description' => 'Sample project: test boards, assignments, approvals, discussions and reports.', 'company_id' => $company, 'start_date' => now()->subDays(3)->toDateString(), 'end_date' => now()->addDays(10)->toDateString(), 'status' => $i === 1 ? 'progress' : 'delivered', 'approval' => 'approved', 'url' => 'https://example.com']);
                $projects[] = $project;
                $this->record('pms_department_project', ['department_id' => $department, 'project_id' => $project]);
                $this->record('pms_project_workflow', ['project_id' => $project, 'workflow_id' => $workflow]);
                $sprint = $this->record('pms_sprints', ['project_id' => $project, 'name' => ($i === 1 ? 'Customer Portal Launch' : 'Brand Identity Delivery')], ['goal' => 'Check the migrated PMS delivery flow', 'start_date' => now()->subDay()->toDateString(), 'end_date' => now()->addWeek()->toDateString(), 'status' => $i === 1 ? 'active' : 'completed', 'created_by' => $user]);
                $task = $this->record('pms_tasks', ['project_id' => $project, 'title' => ($i === 1 ? 'Build customer sign-in flow' : 'Create brand identity guidelines')], ['description' => 'Review this sample issue and test its actions.', 'department_id' => $department, 'workflow_id' => $workflow, 'workflow_column_id' => $columns[$i === 1 ? 1 : 3], 'sprint_id' => $sprint, 'status' => $i === 1 ? 'in_progress' : 'completed', 'approval' => 'pending', 'priority' => 1, 'due_date' => now()->addDays(2), 'created_by' => 'Super Admin', 'original_estimate_minutes' => 120, 'invest_time' => '00:30:00', 'completed_by' => $i === 2 ? $user : null]);
                $tasks[] = $task;
                $subtask = $this->record('pms_subtasks', ['task_id' => $task, 'title' => ($i === 1 ? 'Validate sign-in form inputs' : 'Prepare logo usage examples')], ['description' => 'Sample checklist item', 'status' => $i === 1 ? 'pending' : 'completed', 'approval' => 'pending', 'priority' => 0, 'due_date' => now()->addDays(2), 'created_by' => 'Super Admin', 'invest_time' => '00:15:00']);
                $this->record('pms_task_comments', ['task_id' => $task, 'body' => 'Demo: please check the acceptance criteria.'], ['user_id' => $user, 'author_name' => $i === 1 ? 'Ayesha Khan' : 'Hamza Malik']);
                $this->record('pms_project_chats', ['project_id' => $project, 'message' => 'Demo: welcome to the project discussion.'], ['user_id' => $user, 'author_name' => $i === 1 ? 'Ayesha Khan' : 'Hamza Malik', 'seen_by' => json_encode([])]);
                $this->record('pms_timer_logs', ['user_id' => $user, 'task_id' => $task, 'subtask_id' => null], ['start_time' => now()->subHour(), 'end_time' => now()->subMinutes(30)]);
                $this->record('pms_timer_logs', ['user_id' => $user, 'task_id' => $task, 'subtask_id' => $subtask], ['start_time' => now()->subMinutes(25), 'end_time' => now()->subMinutes(10)]);
                $this->record('pms_summaries', ['user_id' => $user, 'project_id' => $project, 'date' => now()->toDateString()], ['task' => json_encode([['task' => ($i === 1 ? 'Build customer sign-in flow' : 'Create brand identity guidelines'), 'invest_time' => '00:30:00', 'subtasks' => []]])]);
                $notification = ['type' => 'App\\Modules\\Pms\\Notifications\\TaskNotification', 'notifiable_type' => \App\Modules\Pms\Models\User::class, 'notifiable_id' => $user];
                if (!DB::table('pms_notifications')->where($notification)->where('data->demo', true)->exists()) DB::table('pms_notifications')->insert($notification + ['id' => (string) Str::uuid(), 'data' => json_encode(['demo' => true, 'subject' => 'Demo task assigned', 'message' => ($i === 1 ? 'Build customer sign-in flow' : 'Create brand identity guidelines').' is ready for review.', 'task_id' => $task, 'project_id' => $project]), 'created_at' => now(), 'updated_at' => now()]);
                $this->record('pms_task_estimate_histories', ['task_id' => $task, 'new_minutes' => 120], ['user_id' => $user, 'old_minutes' => null]);
                $path = 'pms/demo/sample-'.$i.'.txt';
                Storage::disk('public')->put($path, 'Demo PMS attachment '.$i.'. This is sample content for download testing.');
                $this->record('pms_task_attachments', ['task_id' => $task, 'path' => $path], ['user_id' => $user, 'original_name' => 'demo-attachment-'.$i.'.txt', 'mime_type' => 'text/plain', 'size' => Storage::disk('public')->size($path)]);
                $this->record('pms_submissions', ['user_id' => $user, 'submittable_type' => \App\Modules\Pms\Models\Tasks::class, 'submittable_id' => $task, 'title' => 'Demo submission '.$i], ['description' => 'Sample deliverable for approval testing', 'file_path' => $path, 'status' => 'pending']);
                $this->record('pms_invitations', ['invitee_email' => 'pms.invitee'.$i.'@example.test'], ['sender_id' => $user, 'invitable_type' => \App\Modules\Pms\Models\User::class, 'invitable_id' => $user, 'invitee_name' => ($i === 1 ? 'Sara Ahmed' : 'Omar Hassan'), 'purpose' => 'employee_onboarding', 'role_id' => $role, 'department_id' => $department, 'role' => 'employee', 'token' => Str::random(32), 'expires_at' => now()->addWeek(), 'delivery_status' => 'manual']);
                if ($i === 2) $this->record('pms_sub_task_pending_summaries', ['sub_task_id' => $subtask], ['user_id' => $user, 'task_id' => $task, 'sub_task_title' => 'Prepare logo usage examples', 'date' => now()->toDateString(), 'author_name' => 'Hamza Malik', 'invest_time' => '00:15:00']);
            }
            foreach ($projects as $index => $project) {
                foreach ($users as $user) {
                    $this->record('pms_project_user', ['project_id' => $project, 'user_id' => $user]);
                    $this->record('pms_task_user', ['task_id' => $tasks[$index], 'user_id' => $user], ['assigned_by' => $users[0]]);
                    $subtask = DB::table('pms_subtasks')->where('task_id', $tasks[$index])->value('id');
                    $this->record('pms_subtask_user', ['subtask_id' => $subtask, 'user_id' => $user], ['assigned_by' => $users[0]]);
                }
            }
            foreach ([0, 1] as $index) {
                $key = ['from_id' => $users[$index], 'to_id' => $users[1 - $index], 'body' => 'Demo: '.($index === 0 ? 'Can you check the assigned task?' : 'Yes, I will review it.')];
                if (!DB::table('pms_ch_messages')->where($key)->exists()) DB::table('pms_ch_messages')->insert($key + ['id' => (string) Str::uuid(), 'seen' => false, 'created_at' => now(), 'updated_at' => now()]);
            }
            $this->record('pms_task_handoffs', ['source_task_id' => $tasks[0], 'destination_task_id' => $tasks[1]], ['source_department_id' => $departments[0], 'destination_department_id' => $departments[1], 'source_project_id' => $projects[0], 'destination_project_id' => $projects[1], 'handed_off_by' => $users[0], 'notes' => 'Demo: development to design handoff.']);
        });
        $this->command?->info('PMS demo data added. Employee password: DemoPass123!');
    }
}
