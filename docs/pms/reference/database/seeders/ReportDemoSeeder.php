<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Department;
use App\Models\Projects;
use App\Models\Role;
use App\Models\Tasks;
use App\Models\TimerLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ReportDemoSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $this->removePreviousLabeledRecords();
            $admin = User::whereHas('roles', fn ($q) => $q->where('role_key', 'admin'))->firstOrFail();
            $developerRole = Role::where('role_key', 'developer')->first();

            $departments = collect([
                'Development' => 'Web and application engineering',
                'Graphic Design' => 'Product design and visual communication',
                'SEO' => 'Organic search and content performance',
            ])->mapWithKeys(fn ($description, $name) => [$name => Department::updateOrCreate(
                ['dept_name' => $name], ['description' => $description, 'status' => 1]
            )]);

            $people = collect([
                ['name' => 'Ayesha', 'surname' => 'Khan', 'email' => 'ayesha.khan@northstar.test', 'designation' => 'Senior Developer', 'department' => 'Development'],
                ['name' => 'Hamza', 'surname' => 'Ali', 'email' => 'hamza.ali@crestline.test', 'designation' => 'Product Designer', 'department' => 'Graphic Design'],
                ['name' => 'Sara', 'surname' => 'Ahmed', 'email' => 'sara.ahmed@mediora.test', 'designation' => 'SEO Specialist', 'department' => 'SEO'],
                ['name' => 'Usman', 'surname' => 'Raza', 'email' => 'usman.raza@northstar.test', 'designation' => 'Backend Developer', 'department' => 'Development'],
                ['name' => 'Hira', 'surname' => 'Malik', 'email' => 'hira.malik@crestline.test', 'designation' => 'Visual Designer', 'department' => 'Graphic Design'],
            ])->map(function (array $person) use ($developerRole, $departments) {
                $user = User::updateOrCreate(['email' => $person['email']], [
                    'name' => $person['name'], 'surname' => $person['surname'], 'designation' => $person['designation'],
                    'password' => 'Testing@123', 'status' => 1,
                ]);
                if ($developerRole) $user->roles()->syncWithoutDetaching([$developerRole->id]);
                $user->departments()->syncWithoutDetaching([$departments[$person['department']]->id]);

                return $user;
            });

            $companies = collect([
                'Northstar Retail Limited' => ['email' => 'operations@northstar-retail.test', 'website' => 'https://northstar-retail.test'],
                'Crestline Properties' => ['email' => 'office@crestline-properties.test', 'website' => 'https://crestline-properties.test'],
                'Mediora Health Systems' => ['email' => 'contact@mediora-health.test', 'website' => 'https://mediora-health.test'],
            ])->mapWithKeys(fn ($details, $name) => [$name => Company::updateOrCreate(['name' => $name], $details + ['status' => true])]);

            $projectDefinitions = [
                ['company' => 'Northstar Retail Limited', 'name' => 'E-commerce Platform Upgrade', 'start' => -55, 'end' => 25, 'people' => [0, 3, 4]],
                ['company' => 'Crestline Properties', 'name' => 'Property Launch Campaign', 'start' => -35, 'end' => 40, 'people' => [1, 2, 4]],
                ['company' => 'Mediora Health Systems', 'name' => 'Patient Portal Optimization', 'start' => -42, 'end' => 18, 'people' => [0, 2, 3]],
            ];
            $projects = collect($projectDefinitions)->map(function ($data) use ($companies, $people, $admin) {
                $project = Projects::updateOrCreate(['name' => $data['name']], [
                    'company_id' => $companies[$data['company']]->id,
                    'description' => 'Client delivery project covering coordinated work across multiple departments.',
                    'start_date' => now()->addDays($data['start'])->toDateString(), 'end_date' => now()->addDays($data['end'])->toDateString(),
                ]);
                $project->forceFill(['status' => 'progress', 'approval' => 'approved', 'is_general' => false])->save();
                $assigned = collect($data['people'])->mapWithKeys(fn ($index) => [$people[$index]->id => ['assigned_by' => $admin->id]])->all();
                $project->users()->syncWithoutDetaching($assigned);

                return $project;
            });

            $tasks = [
                [0, 'Development', 0, 'Build secure checkout API', 'completed', -14, -20, 7.5],
                [0, 'Development', 3, 'Improve product search performance', 'in_progress', 5, -5, 5.0],
                [0, 'Graphic Design', 4, 'Prepare responsive storefront assets', 'in_review', 3, -3, 4.25],
                [0, 'SEO', 2, 'Create product metadata strategy', 'pending', 9, -2, 2.0],
                [1, 'Graphic Design', 1, 'Design property launch landing page', 'completed', -6, -12, 6.5],
                [1, 'Graphic Design', 4, 'Produce social media campaign banners', 'in_progress', 4, -4, 3.75],
                [1, 'SEO', 2, 'Research local property keywords', 'completed', -9, -16, 8.0],
                [1, 'Development', 3, 'Configure lead capture integration', 'pending', 2, -1, 1.5],
                [2, 'Development', 0, 'Optimize patient dashboard queries', 'in_progress', -2, -7, 6.0],
                [2, 'Development', 3, 'Implement appointment reminders', 'in_review', 7, -3, 4.75],
                [2, 'SEO', 2, 'Audit healthcare service pages', 'completed', -11, -18, 7.25],
                [2, 'Graphic Design', 1, 'Refresh portal onboarding illustrations', 'pending', null, -2, 2.5],
            ];

            foreach ($tasks as $index => [$projectIndex, $department, $personIndex, $title, $status, $dueOffset, $workOffset, $hours]) {
                $dueDate = $dueOffset === null ? null : now()->addDays($dueOffset);
                $task = Tasks::updateOrCreate(['project_id' => $projects[$projectIndex]->id, 'title' => $title], [
                    'department_id' => $departments[$department]->id, 'description' => 'Client deliverable prepared for reporting and workload analysis.',
                    'status' => $status, 'due_date' => $dueDate, 'created_by' => $admin->id,
                    'completed_by' => $status === 'completed' ? $admin->id : null, 'priority' => ($index % 4),
                ]);
                $person = $people[$personIndex];
                $task->users()->syncWithoutDetaching([$person->id => ['assigned_by' => $admin->id]]);
                $start = now()->addDays($workOffset)->setTime(9 + ($index % 4), 0, 0);
                TimerLog::updateOrCreate(
                    ['task_id' => $task->id, 'subtask_id' => null, 'user_id' => $person->id, 'start_time' => $start],
                    ['end_time' => $start->copy()->addMinutes((int) round($hours * 60))]
                );
                if ($status === 'completed') {
                    $completedAt = in_array($index, [0, 6], true) ? Carbon::parse($dueDate)->subDay() : Carbon::parse($dueDate)->addDay();
                    DB::table('tasks')->where('id', $task->id)->update(['updated_at' => $completedAt]);
                }
            }
        });

        $this->command?->info('Realistic report records ready: 3 companies, 3 projects, 3 departments, 5 employees, and 12 timed tasks.');
    }

    private function removePreviousLabeledRecords(): void
    {
        Projects::whereIn('name', ['Demo Commerce Redesign', 'Demo Growth Campaign'])->delete();
        User::whereIn('email', ['ayesha.report-demo@test.local', 'hamza.report-demo@test.local', 'sara.report-demo@test.local'])->delete();
        Department::whereIn('dept_name', ['Demo Development', 'Demo Design', 'Demo SEO'])->delete();
        Company::where('name', 'Demo Acme Corporation')->delete();
    }
}
