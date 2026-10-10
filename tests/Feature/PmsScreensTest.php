<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
class PmsScreensTest extends TestCase {
 use DatabaseTransactions;
 public function test_super_admin_dashboard_reports_and_work_logs_are_connected():void {
  $this->withSession(['judibas_admin'=>true]);
  foreach(['/dashboard','/dashboard/table','/work-logs','/reports','/reports/overview','/reports/data','/team-activity','/team-space','/invitations','/invitations/sent','/invitations/received','/settings/mail','/settings/branding','/settings/realtime'] as $path)$this->getJson('/pms/api'.$path)->assertOk();
 }

 public function test_manual_super_admin_invitation_acceptance_grants_only_pms_and_cannot_be_reused():void {
  $this->withSession(['judibas_admin'=>true]);
  $role=\Illuminate\Support\Facades\DB::table('pms_roles')->where('role_key','developer')->value('id');
  $r=$this->postJson('/pms/api/invitations/employees',['email'=>'pms-invite-parity@example.test','name'=>'Invite Parity','role_id'=>$role,'delivery_method'=>'manual'])->assertCreated();
  $token=\App\Modules\Pms\Models\Invitation::where('invitee_email','pms-invite-parity@example.test')->value('token');
  $this->getJson('/pms/api/invitations/sent')->assertOk()->assertJsonFragment(['recipient_email'=>'pms-invite-parity@example.test']);
  $this->withSession(['judibas_admin'=>false])->getJson('/pms/invitation/'.$token)->assertOk();
  $this->postJson('/pms/invitation/'.$token,['name'=>'Invite Parity','password'=>'password123','password_confirmation'=>'password123'])->assertOk()->assertJsonPath('redirect_url','/pms');
  $user=\App\Models\User::where('email','pms-invite-parity@example.test')->firstOrFail();
  $this->assertDatabaseHas('user_product_access',['user_id'=>$user->id,'product_slug'=>'projects']);
  $this->assertDatabaseMissing('user_product_access',['user_id'=>$user->id,'product_slug'=>'communication']);
  $this->assertDatabaseHas('pms_role_users',['user_id'=>$user->id,'role_id'=>$role]);
  $this->getJson('/pms/api')->assertOk();
  $this->getJson('/pms/invitation/'.$token)->assertStatus(410);
 }
 public function test_dashboard_portfolio_keeps_completion_counts_and_team_space_project_links():void {
  \Illuminate\Support\Facades\Event::fake();
  \Illuminate\Support\Facades\Notification::fake();
  $this->withSession(['judibas_admin'=>true]);
  $workflow=\Illuminate\Support\Facades\DB::table('pms_workflows')->where('is_default',true)->value('id');
  $department=\Illuminate\Support\Facades\DB::table('pms_departments')->insertGetId(['dept_name'=>'Sidebar Parity Department','status'=>1,'created_at'=>now(),'updated_at'=>now()]);
  $project=$this->postJson('/pms/api/projects',['name'=>'Portfolio Parity Project','start_date'=>'2026-10-09','workflow_ids'=>[$workflow],'department_ids'=>[$department]])->assertCreated()->json('project_id');
  $column=\Illuminate\Support\Facades\DB::table('pms_workflow_columns')->where('workflow_id',$workflow)->where('is_completed',true)->value('id');
  $this->postJson('/pms/api/tasks',['title'=>'Completed portfolio issue','project_id'=>$project,'workflow_id'=>$workflow,'workflow_column_id'=>$column,'status'=>'completed'])->assertOk();
  $this->getJson('/pms/api/dashboard/table')->assertOk()->assertJsonFragment(['name'=>'Portfolio Parity Project','completion_percentage'=>100]);
  $tree=$this->getJson('/pms/api/team-space')->assertOk()->json('departments');
  $selected=collect($tree)->firstWhere('id',$department);
  $this->assertTrue($selected['can_open_board']);
  $this->assertContains($project,array_column($selected['projects'],'id'));
 }
}
