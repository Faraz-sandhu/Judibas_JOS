<?php
namespace Tests\Feature;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\{DB,Event,Notification};
class PmsClientHierarchyTest extends TestCase {
 use DatabaseTransactions;
 protected function setUp():void {parent::setUp();$this->seed(\App\Modules\Pms\Database\Seeders\PmsFoundationSeeder::class);Event::fake();Notification::fake();$this->withSession(['judibas_admin'=>true]);}
 private function company(string $name):array {$platform=DB::table('communication_companies')->insertGetId(['name'=>$name,'created_at'=>now(),'updated_at'=>now()]);$pms=DB::table('pms_companies')->insertGetId(['name'=>$name,'status'=>true,'platform_company_id'=>$platform,'created_at'=>now(),'updated_at'=>now()]);return [$platform,$pms];}
 private function client(int $company,string $name):int {return $this->postJson('/pms/api/clients',['company_id'=>$company,'name'=>$name,'status'=>true])->assertCreated()->json('client.id');}
 private function department(string $name):int {return DB::table('pms_departments')->insertGetId(['dept_name'=>$name,'status'=>1,'created_at'=>now(),'updated_at'=>now()]);}
 private function project(int $client,string $name,array $departments):int {$workflow=DB::table('pms_workflows')->where('is_default',true)->value('id');return $this->postJson('/pms/api/projects',['client_id'=>$client,'name'=>$name,'start_date'=>'2026-10-12','department_ids'=>$departments,'workflow_ids'=>[$workflow]])->assertCreated()->json('project_id');}
 private function task(int $project,int $department,string $title,?int $sprint=null):int {$w=DB::table('pms_project_workflow')->where('project_id',$project)->value('workflow_id');$col=DB::table('pms_workflow_columns')->where('workflow_id',$w)->orderBy('position')->value('id');return $this->postJson('/pms/api/tasks',['title'=>$title,'project_id'=>$project,'department_id'=>$department,'workflow_id'=>$w,'workflow_column_id'=>$col,'status'=>'pending','sprint_id'=>$sprint])->assertOk()->json('task.id');}
 private function employee(int $company):User {$user=User::factory()->create(['is_active'=>true,'status'=>1]);DB::table('user_product_access')->insert(['user_id'=>$user->id,'product_slug'=>'projects']);DB::table('communication_company_memberships')->insert(['user_id'=>$user->id,'company_id'=>$company]);$role=DB::table('pms_roles')->where('role_key','developer')->value('id');DB::table('pms_role_users')->insert(['user_id'=>$user->id,'role_id'=>$role]);return $user;}
 public function test_client_crud_company_ownership_and_delete_guard():void {
  [, $company]=$this->company('Harbor Company');$client=$this->client($company,'Harbor Retail');$department=$this->department('Harbor Design');$project=$this->project($client,'Harbor Website',[$department]);
  $this->assertDatabaseHas('pms_projects',['id'=>$project,'company_id'=>$company,'client_id'=>$client]);
  $this->postJson('/pms/api/clients/'.$client,['company_id'=>$company,'name'=>'Harbor Stores','status'=>true,'email'=>'contact@harbor.example'])->assertOk();
  $this->getJson('/pms/api/projects?client='.$client)->assertOk()->assertJsonCount(1,'data.projects')->assertJsonFragment(['name'=>'Harbor Website']);
  [, $otherCompany]=$this->company('Harbor Services');$this->postJson('/pms/api/clients/'.$client,['company_id'=>$otherCompany,'name'=>'Harbor Stores','status'=>true])->assertUnprocessable();
  $workflow=DB::table('pms_project_workflow')->where('project_id',$project)->value('workflow_id');$this->task($project,$department,'Harbor design brief');$this->postJson('/pms/api/projects/'.$project,['client_id'=>$client,'name'=>'Harbor Website','start_date'=>'2026-10-12','workflow_ids'=>[$workflow],'department_ids'=>[],'sync_departments'=>true])->assertUnprocessable();
  $this->deleteJson('/pms/api/clients/'.$client)->assertUnprocessable();
  $this->deleteJson('/pms/api/projects/'.$project)->assertOk();$this->deleteJson('/pms/api/clients/'.$client)->assertOk();
 }
 public function test_assigned_employee_only_sees_company_client_project_and_department_with_their_cards():void {
  [$platform,$company]=$this->company('Cedar Company');[$otherPlatform,$otherCompany]=$this->company('Northstar Company');$client=$this->client($company,'Cedar Retail');$hiddenClient=$this->client($company,'Cedar Logistics');$otherClient=$this->client($otherCompany,'Northstar Retail');$dev=$this->department('Cedar Engineering');$seo=$this->department('Cedar SEO');
  $project=$this->project($client,'Cedar Portal',[$dev,$seo]);$hidden=$this->project($hiddenClient,'Cedar ERP',[$dev]);$other=$this->project($otherClient,'Northstar Portal',[$dev]);$task=$this->task($project,$dev,'Build customer checkout');$seoTask=$this->task($project,$seo,'Research search keywords');$hiddenTask=$this->task($hidden,$dev,'Build stock dashboard');$otherTask=$this->task($other,$dev,'Build unrelated portal');
  DB::table('pms_projects')->whereIn('id',[$project,$hidden,$other])->update(['approval'=>'approved']);$user=$this->employee($platform);
  foreach([$task,$otherTask] as $id)DB::table('pms_task_user')->insert(['task_id'=>$id,'user_id'=>$user->id]);DB::table('pms_project_user')->insert(['project_id'=>$hidden,'user_id'=>$user->id]);
  $this->withSession(['judibas_admin'=>false])->actingAs($user);
  $r=$this->getJson('/pms/api/hierarchy')->assertOk()->assertJsonPath('show_companies',false);$r->assertJsonFragment(['name'=>'Cedar Retail'])->assertJsonFragment(['name'=>'Cedar Portal'])->assertJsonMissing(['name'=>'Cedar Logistics'])->assertJsonMissing(['name'=>'Northstar Company']);
  $this->assertSame([$dev],$r->json('companies.0.clients.0.projects.0.departments.*.id'));
  $this->getJson('/pms/api/clients')->assertOk()->assertJsonMissing(['name'=>'Cedar Logistics']);
  $this->getJson('/pms/api/projects/'.$project.'?department='.$dev)->assertOk()->assertJsonCount(1,'data.boardTasks');
  $this->getJson('/pms/api/projects/'.$project.'?department='.$seo)->assertNotFound();
  foreach([$hidden,$other] as $id)$this->getJson('/pms/api/projects/'.$id)->assertNotFound();
  foreach([$seoTask,$hiddenTask,$otherTask] as $id)$this->getJson('/pms/api/tasks/'.$id)->assertNotFound();
  $this->postJson('/pms/api/clients',['company_id'=>$company,'name'=>'Forbidden','status'=>true])->assertForbidden();
 }
 public function test_department_sprints_and_tasks_are_isolated_and_reject_cross_department_moves():void {
  [, $company]=$this->company('Bluebird Company');$client=$this->client($company,'Bluebird Foods');$dev=$this->department('Bluebird Development');$design=$this->department('Bluebird Design');$project=$this->project($client,'Bluebird Store',[$dev,$design]);
  $a=$this->postJson('/pms/api/projects/'.$project.'/sprints',['name'=>'Checkout delivery','department_id'=>$dev])->assertCreated()->json('sprint.id');
  $b=$this->postJson('/pms/api/projects/'.$project.'/sprints',['name'=>'Brand delivery','department_id'=>$design])->assertCreated()->json('sprint.id');
  $task=$this->task($project,$dev,'Checkout API',$a);$this->task($project,$design,'Store logo',$b);$this->task($project,$dev,'Payment backlog');
  $r=$this->getJson('/pms/api/projects/'.$project.'?department='.$dev.'&sprint='.$a)->assertOk()->assertJsonCount(1,'data.sprints')->assertJsonCount(1,'data.boardTasks')->assertJsonFragment(['title'=>'Checkout API'])->assertJsonMissing(['title'=>'Store logo']);
  $this->getJson('/pms/api/projects/'.$project.'?department='.$dev.'&sprint=backlog')->assertOk()->assertJsonFragment(['title'=>'Payment backlog'])->assertJsonMissing(['title'=>'Checkout API']);
  $this->getJson('/pms/api/projects/'.$project.'?department='.$dev.'&sprint='.$b)->assertNotFound();
  $foreign=$this->department('Bluebird Sales');$this->postJson('/pms/api/projects/'.$project.'/sprints',['name'=>'Invalid department','department_id'=>$foreign])->assertUnprocessable();
  $this->patchJson('/pms/api/tasks/'.$task.'/board',['sprint_id'=>$b])->assertUnprocessable();
  $this->deleteJson('/pms/api/sprints/'.$a)->assertOk();$this->assertDatabaseHas('pms_tasks',['id'=>$task,'department_id'=>$dev,'sprint_id'=>null]);
 }
 public function test_project_manager_with_view_all_sees_every_company_without_project_membership():void {
  [, $company]=$this->company('Summit Company');$client=$this->client($company,'Summit Health');$department=$this->department('Summit Marketing');$project=$this->project($client,'Summit Campaign',[$department]);$task=$this->task($project,$department,'Campaign launch');
  $u=User::factory()->create(['is_active'=>true,'status'=>1]);DB::table('user_product_access')->insert(['user_id'=>$u->id,'product_slug'=>'projects']);DB::table('pms_role_users')->insert(['user_id'=>$u->id,'role_id'=>DB::table('pms_roles')->where('role_key','project_manager')->value('id')]);
  $this->withSession(['judibas_admin'=>false])->actingAs($u);$this->getJson('/pms/api/hierarchy')->assertOk()->assertJsonPath('show_companies',true)->assertJsonFragment(['name'=>'Summit Company']);$this->getJson('/pms/api/projects/'.$project.'?department='.$department)->assertOk()->assertJsonFragment(['title'=>'Campaign launch']);$this->getJson('/pms/api/tasks/'.$task)->assertOk();
 }
 public function test_subtask_assignment_grants_only_its_parent_project_in_the_selected_company():void {
  [$platform,$company]=$this->company('Orion Company');$client=$this->client($company,'Orion Travel');$department=$this->department('Orion SEO');$project=$this->project($client,'Orion Search',[$department]);$task=$this->task($project,$department,'Search campaign');$sub=$this->postJson('/pms/api/subtasks',['task_id'=>$task,'title'=>'Review keyword list'])->assertOk()->json('subtask.id');
  if(!$sub)$sub=DB::table('pms_subtasks')->where('task_id',$task)->value('id');DB::table('pms_projects')->where('id',$project)->update(['approval'=>'approved']);$user=$this->employee($platform);DB::table('pms_subtask_user')->insert(['user_id'=>$user->id,'subtask_id'=>$sub]);
  $this->withSession(['judibas_admin'=>false])->actingAs($user);$this->getJson('/pms/api/hierarchy')->assertOk()->assertJsonFragment(['name'=>'Orion Search']);$this->getJson('/pms/api/projects/'.$project.'?department='.$department)->assertOk()->assertJsonFragment(['title'=>'Search campaign']);$this->getJson('/pms/api/tasks/'.$task)->assertOk()->assertJsonFragment(['title'=>'Review keyword list']);
 }
 public function test_legacy_sprint_split_preserves_both_departments_work():void {
  [, $company]=$this->company('Evergreen Company');$client=$this->client($company,'Evergreen Retail');$a=$this->department('Evergreen Development');$b=$this->department('Evergreen Design');$project=$this->project($client,'Evergreen Website',[$a,$b]);$first=$this->task($project,$a,'Build web layout');$second=$this->task($project,$b,'Design web layout');$sprint=$this->postJson('/pms/api/projects/'.$project.'/sprints',['name'=>'Legacy shared sprint','status'=>'active'])->assertCreated()->json('sprint.id');DB::table('pms_tasks')->whereIn('id',[$first,$second])->update(['sprint_id'=>$sprint]);
  $migration=require database_path('migrations/2026_10_12_130000_preserve_pms_department_work.php');$migration->up();$aSprint=DB::table('pms_tasks')->where('id',$first)->value('sprint_id');$bSprint=DB::table('pms_tasks')->where('id',$second)->value('sprint_id');$this->assertNotEquals($aSprint,$bSprint);$this->assertDatabaseHas('pms_sprints',['id'=>$aSprint,'department_id'=>$a,'status'=>'active']);$this->assertDatabaseHas('pms_sprints',['id'=>$bSprint,'department_id'=>$b,'status'=>'active']);$this->getJson('/pms/api/projects/'.$project.'?department='.$a.'&sprint='.$aSprint)->assertOk()->assertJsonFragment(['title'=>'Build web layout']);$this->getJson('/pms/api/projects/'.$project.'?department='.$b.'&sprint='.$bSprint)->assertOk()->assertJsonFragment(['title'=>'Design web layout']);
 }

 public function test_dashboard_companies_are_the_pms_company_catalogue_and_names_stay_synced():void {
  $legacy=DB::table('pms_companies')->insertGetId(['name'=>'Legacy Sample Vendor','status'=>true,'created_at'=>now(),'updated_at'=>now()]);
  $platform=$this->postJson('/communication/api/companies',['name'=>'Horizon Shared Company'])->assertOk()->json('id');
  $this->assertDatabaseHas('pms_companies',['platform_company_id'=>$platform,'name'=>'Horizon Shared Company']);
  $companies=$this->getJson('/pms/api/clients')->assertOk()->json('companies');$this->assertNotContains($legacy,array_column($companies,'id'));
  $this->postJson('/communication/api/companies/'.$platform,['name'=>'Horizon Group'])->assertOk();$this->getJson('/pms/api/clients')->assertOk()->assertJsonFragment(['name'=>'Horizon Group'])->assertJsonMissing(['name'=>'Horizon Shared Company']);
  $this->getJson('/pms/api/hierarchy')->assertOk()->assertJsonPath('show_companies',true)->assertJsonFragment(['name'=>'Horizon Group'])->assertJsonMissing(['name'=>'Legacy Sample Vendor']);
 }

 public function test_eye_boards_include_every_child_department_sprint_and_backlog_without_other_scopes():void {
  [, $company]=$this->company('Vista Company');[, $otherCompany]=$this->company('Meridian Company');$client=$this->client($company,'Vista Retail');$secondClient=$this->client($company,'Vista Health');$otherClient=$this->client($otherCompany,'Meridian Retail');$dev=$this->department('Vista Development');$seo=$this->department('Vista SEO');$a=$this->project($client,'Vista Portal',[$dev,$seo]);$b=$this->project($client,'Vista Store',[$dev]);$c=$this->project($secondClient,'Vista Campaign',[$seo]);$foreign=$this->project($otherClient,'Meridian Portal',[$dev]);$sprint=$this->postJson('/pms/api/projects/'.$a.'/sprints',['name'=>'Vista Search Release','department_id'=>$seo,'status'=>'active'])->assertCreated()->json('sprint.id');
  $this->task($a,$dev,'Build Vista sign-in');$this->task($a,$seo,'Audit Vista search',$sprint);$this->task($b,$dev,'Build Vista checkout');$this->task($c,$seo,'Research Vista keywords');$this->task($foreign,$dev,'Build Meridian checkout');
  $base='/pms/api/hierarchy-board?';
  $this->getJson($base.'scope=company&id='.$company)->assertOk()->assertJsonCount(4,'data.tasks')->assertJsonFragment(['title'=>'Audit Vista search'])->assertJsonFragment(['title'=>'Research Vista keywords'])->assertJsonMissing(['title'=>'Build Meridian checkout']);
  $this->getJson($base.'scope=client&id='.$client)->assertOk()->assertJsonCount(3,'data.tasks')->assertJsonMissing(['title'=>'Research Vista keywords']);
  $this->getJson($base.'scope=project&id='.$a)->assertOk()->assertJsonCount(2,'data.tasks')->assertJsonFragment(['title'=>'Build Vista sign-in'])->assertJsonFragment(['title'=>'Audit Vista search']);
  $this->getJson($base.'scope=company&id='.$company.'&department='.$seo.'&sprint='.$sprint)->assertOk()->assertJsonCount(1,'data.tasks')->assertJsonFragment(['title'=>'Audit Vista search']);
  $this->getJson($base.'scope=project&id='.$a.'&sprint=backlog')->assertOk()->assertJsonCount(1,'data.tasks')->assertJsonFragment(['title'=>'Build Vista sign-in']);
  $this->getJson($base.'scope=client&id='.$client.'&project='.$c)->assertUnprocessable();$this->getJson($base.'scope=company&id='.$otherCompany.'&sprint='.$sprint)->assertUnprocessable();
 }
 public function test_eye_boards_apply_company_and_card_assignment_permissions():void {
  [$platform,$company]=$this->company('Aspen Company');[, $foreignCompany]=$this->company('Birch Company');$client=$this->client($company,'Aspen Stores');$otherClient=$this->client($foreignCompany,'Birch Stores');$dep=$this->department('Aspen Graphic');$project=$this->project($client,'Aspen Branding',[$dep]);$foreign=$this->project($otherClient,'Birch Branding',[$dep]);$mine=$this->task($project,$dep,'Prepare Aspen logo');$hidden=$this->task($project,$dep,'Prepare Aspen brochures');$outside=$this->task($foreign,$dep,'Prepare Birch logo');$u=$this->employee($platform);DB::table('pms_task_user')->insert(['user_id'=>$u->id,'task_id'=>$mine]);$this->withSession(['judibas_admin'=>false])->actingAs($u);
  foreach(['company'=>$company,'client'=>$client,'project'=>$project] as $scope=>$id)$this->getJson('/pms/api/hierarchy-board?scope='.$scope.'&id='.$id)->assertOk()->assertJsonCount(1,'data.tasks')->assertJsonFragment(['title'=>'Prepare Aspen logo'])->assertJsonMissing(['title'=>'Prepare Aspen brochures']);
  foreach(['company'=>$foreignCompany,'client'=>$otherClient,'project'=>$foreign] as $scope=>$id)$this->getJson('/pms/api/hierarchy-board?scope='.$scope.'&id='.$id)->assertNotFound();
  $this->patchJson('/pms/api/hierarchy-board/tasks/'.$hidden.'/status',['status'=>'completed'])->assertNotFound();
 }
 public function test_combined_board_drag_keeps_the_tasks_own_workflow_and_department():void {
  [, $company]=$this->company('Willow Company');$client=$this->client($company,'Willow Foods');$dep=$this->department('Willow Marketing');$project=$this->project($client,'Willow Campaign',[$dep]);$task=$this->task($project,$dep,'Prepare campaign calendar');$workflow=DB::table('pms_tasks')->where('id',$task)->value('workflow_id');
  $this->patchJson('/pms/api/hierarchy-board/tasks/'.$task.'/status',['status'=>'in_progress'])->assertOk();$this->assertDatabaseHas('pms_tasks',['id'=>$task,'workflow_id'=>$workflow,'department_id'=>$dep,'status'=>'in_progress']);$this->patchJson('/pms/api/hierarchy-board/tasks/'.$task.'/status',['status'=>'completed'])->assertOk();$this->assertDatabaseHas('pms_tasks',['id'=>$task,'workflow_id'=>$workflow,'department_id'=>$dep,'status'=>'completed']);
 }

}
