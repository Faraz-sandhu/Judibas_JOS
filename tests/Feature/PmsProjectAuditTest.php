<?php
namespace Tests\Feature;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
class PmsProjectAuditTest extends TestCase {
 use DatabaseTransactions;
 protected function setUp():void {parent::setUp();$this->seed(\App\Modules\Pms\Database\Seeders\PmsFoundationSeeder::class);Event::fake();Notification::fake();$this->withSession(['judibas_admin'=>true]);}
 private function project(string $name):int {$w=DB::table('pms_workflows')->where('is_default',true)->value('id');return $this->postJson('/pms/api/projects',['name'=>$name,'start_date'=>'2026-10-10','workflow_ids'=>[$w]])->assertCreated()->json('project_id');}
 public function test_project_task_list_includes_all_sprints_and_messages_are_disabled():void {
  $p=$this->project('Task list audit');$w=DB::table('pms_project_workflow')->where('project_id',$p)->value('workflow_id');$c=DB::table('pms_workflow_columns')->where('workflow_id',$w)->orderBy('position')->value('id');
  $s=$this->postJson('/pms/api/projects/'.$p.'/sprints',['name'=>'List sprint','status'=>'active'])->assertCreated()->json('sprint.id');
  foreach([null,$s] as $sprint)$this->postJson('/pms/api/tasks',['title'=>$sprint?'Sprint task':'Backlog task','project_id'=>$p,'workflow_id'=>$w,'workflow_column_id'=>$c,'sprint_id'=>$sprint,'status'=>'pending'])->assertOk();
  $this->getJson('/pms/api/projects/'.$p.'/task-list')->assertOk()->assertJsonCount(2,'data.tasks')->assertJsonFragment(['title'=>'Sprint task'])->assertJsonFragment(['title'=>'Backlog task']);
  $this->getJson('/pms/api/messages')->assertNotFound();
  $this->postJson('/pms/api/messages/1',['message'=>'Disabled'])->assertNotFound();
 }
 public function test_sprint_lifecycle_filters_and_cross_project_validation():void {
  $p=$this->project('Sprint audit');$other=$this->project('Other sprint audit');$w=DB::table('pms_project_workflow')->where('project_id',$p)->value('workflow_id');$c=DB::table('pms_workflow_columns')->where('workflow_id',$w)->orderBy('position')->value('id');
  $this->postJson('/pms/api/projects/'.$p.'/sprints',['name'=>'Invalid','start_date'=>'2026-10-11','end_date'=>'2026-10-10'])->assertUnprocessable();
  $s=$this->postJson('/pms/api/projects/'.$p.'/sprints',['name'=>'Sprint audit 1','goal'=>'Deliver audit','start_date'=>null,'end_date'=>null,'status'=>'planned'])->assertCreated()->json('sprint.id');
  $otherSprint=$this->postJson('/pms/api/projects/'.$other.'/sprints',['name'=>'Other','status'=>'active'])->assertCreated()->json('sprint.id');
  $t=$this->postJson('/pms/api/tasks',['title'=>'Sprint issue','project_id'=>$p,'workflow_id'=>$w,'workflow_column_id'=>$c,'status'=>'pending','sprint_id'=>$s])->assertOk()->json('task.id');
  $this->getJson('/pms/api/projects/'.$p.'?sprint='.$s)->assertOk()->assertJsonPath('data.selectedSprint.id',$s)->assertJsonFragment(['title'=>'Sprint issue']);
  $this->getJson('/pms/api/projects/'.$p.'?sprint=backlog')->assertOk()->assertJsonCount(0,'data.boardTasks');
  $this->patchJson('/pms/api/tasks/'.$t.'/board',['sprint_id'=>$otherSprint])->assertUnprocessable();
  $this->putJson('/pms/api/sprints/'.$s,['name'=>'Renamed audit','status'=>'active'])->assertOk();
  $this->assertDatabaseHas('pms_sprints',['id'=>$s,'name'=>'Renamed audit','status'=>'active']);
  $this->putJson('/pms/api/sprints/'.$s,['status'=>'completed'])->assertOk();
  $this->deleteJson('/pms/api/sprints/'.$s)->assertOk();
  $this->assertDatabaseHas('pms_tasks',['id'=>$t,'sprint_id'=>null]);
  $this->getJson('/pms/api/projects/'.$p.'?sprint=backlog')->assertOk()->assertJsonFragment(['title'=>'Sprint issue']);
 }
 public function test_issue_comments_attachments_and_validation_preserve_source_rules():void {
  Storage::fake('public');$p=$this->project('Files audit');$w=DB::table('pms_project_workflow')->where('project_id',$p)->value('workflow_id');$c=DB::table('pms_workflow_columns')->where('workflow_id',$w)->orderBy('position')->value('id');
  $t=$this->postJson('/pms/api/tasks',['title'=>'File issue','project_id'=>$p,'workflow_id'=>$w,'workflow_column_id'=>$c,'status'=>'pending'])->assertOk()->json('task.id');
  $this->postJson('/pms/api/tasks/'.$t.'/comments',['body'=>''])->assertUnprocessable();
  $comment=$this->postJson('/pms/api/tasks/'.$t.'/comments',['body'=>'Audit comment'])->assertCreated()->json('comment.id');
  $this->getJson('/pms/api/tasks/'.$t)->assertOk()->assertJsonFragment(['body'=>'Audit comment']);
  $this->deleteJson('/pms/api/comments/'.$comment)->assertOk();
  $this->post('/pms/api/tasks/'.$t.'/attachments',['attachments'=>[UploadedFile::fake()->create('notes.txt',1,'text/plain')]],['Accept'=>'application/json'])->assertCreated();
  $a=DB::table('pms_task_attachments')->where('task_id',$t)->first();Storage::disk('public')->assertExists($a->path);
  $this->deleteJson('/pms/api/attachments/'.$a->id)->assertOk();Storage::disk('public')->assertMissing($a->path);
  $this->post('/pms/api/tasks/'.$t.'/attachments',['attachments'=>[UploadedFile::fake()->create('bad.exe',1,'application/x-msdownload')]],['Accept'=>'application/json'])->assertUnprocessable();
 }
}
