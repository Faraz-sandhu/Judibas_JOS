<?php

namespace App\Modules\Pms\Database\Seeders;

use App\Modules\Pms\Models\{Client, Company, Department, Projects, Workflow};
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PmsSidebarDemoSeeder extends Seeder
{
    public function run(): void
    {
        abort_unless(app()->environment(['local','testing']),403,'Local sidebar testing only.');
        DB::transaction(function () {
            $company=Company::withoutGlobalScopes()->where('name','Skyinfinit')->firstOrFail();
            $client=Client::firstOrCreate(['company_id'=>$company->id,'name'=>'Demo Meridian Business Solutions'],[
                'email'=>'meridian.sidebar@example.test','description'=>'Fictional sidebar test client. No real customer or user assignments.','status'=>true,
            ]);
            $departments=[];$workflows=[];
            foreach(['Software Development','UI/UX Design','Quality Assurance','DevOps and Cloud Infrastructure'] as $name){
                $department=Department::firstOrCreate(['dept_name'=>$name],['description'=>$name.' team for software delivery.','status'=>1]);
                $departments[]=$department->id;
                $workflow=Workflow::firstOrCreate(['name'=>'Demo '.$name.' Workflow','department_id'=>$department->id],[
                    'description'=>'Fictional sidebar demo workflow.','is_active'=>true,'is_default'=>false,'transition_mode'=>'any',
                ]);
                if(!$workflow->columns()->exists())foreach(['To Do','In Progress','Review','Completed'] as $position=>$column){
                    $workflow->columns()->create(['name'=>$column,'position'=>$position,'color'=>['secondary','primary','warning','success'][$position],'is_initial'=>$position===0,'is_completed'=>$position===3]);
                }
                $workflows[]=$workflow->id;
            }
            foreach(['Demo Customer Relationship Management Platform','Demo Employee Attendance and Payroll Portal'] as $name){
                $project=Projects::withoutGlobalScopes()->firstOrCreate(['name_key'=>Projects::normalizeName($name)],[
                    'name'=>$name,'company_id'=>$company->id,'client_id'=>$client->id,
                    'description'=>'Fictional local sidebar test project for software development, design, testing and deployment.',
                    'start_date'=>today()->toDateString(),'end_date'=>today()->addWeeks(3)->toDateString(),
                ]);
                abort_unless($project->company_id===$company->id&&$project->client_id===$client->id,422,'A demo name is already in use elsewhere.');
                $project->forceFill(['approval'=>'approved','is_general'=>false])->save();
                $project->departments()->syncWithoutDetaching($departments);
                $project->workflows()->syncWithoutDetaching($workflows);
                $this->command?->info($company->name.' -> '.$client->name.' -> '.$project->name.' -> '.$project->departments()->count().' departments');
            }
        });
    }
}
