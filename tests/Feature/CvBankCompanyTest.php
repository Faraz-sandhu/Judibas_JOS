<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\{DB,Storage};
use Illuminate\Http\UploadedFile;
use App\Services\{UserProductAccess,CvBankAccess};
use App\Models\User;
class CvBankCompanyTest extends TestCase {
 use DatabaseTransactions;
 private array $companies=[],$departments=[],$jobs=[],$slugs=[];
 protected function setUp():void {
  parent::setUp();Storage::fake('local');$this->withSession(['judibas_admin'=>true]);
  foreach(['Cruxlo','Skyinfinit'] as $name){
   $id=DB::table('communication_companies')->insertGetId(['name'=>'CV scope '.$name,'created_at'=>now(),'updated_at'=>now()]);$this->companies[]=$id;$slug='cv-'.strtolower($name).'-'.$id;$this->slugs[]=$slug;
   $this->postJson('/cv-bank/company-pages',['company_id'=>$id,'slug'=>$slug,'published'=>true])->assertOk();
   $this->postJson('/cv-bank/departments',['company_id'=>$id,'name'=>'Development','active'=>true])->assertOk();$d=DB::table('cv_bank_departments')->where('company_id',$id)->value('id');$this->departments[]=$d;
   $job=$this->postJson('/cv-bank/jobs',['company_id'=>$id,'title'=>$name.' Developer','department_id'=>$d,'description'=>'Build useful products.','locations'=>['Faisalabad, Pakistan','Multan, Pakistan'],'work_mode'=>'hybrid','employment_type'=>'full_time','experience'=>'2-4 years','requirements'=>'Laravel and Vue','responsibilities'=>'Build and review software.'])->assertOk()->json('job');$this->jobs[]=$job['id'];
  }
 }
 private function hr(array $companies):User {$u=User::factory()->create(['is_active'=>true,'judibas_administrator'=>false]);UserProductAccess::sync($u,['product_slugs'=>['cv-bank'],'company_ids'=>$companies]);return $u;}
 private function applicant(int $index=0,?array $extra=null){return $this->post('/careers/'.$this->slugs[$index].'/apply',array_replace(['name'=>'Test Candidate','email'=>'candidate@example.test','department_id'=>$this->departments[$index],'job_id'=>$this->jobs[$index],'cv'=>UploadedFile::fake()->create('candidate.pdf',4,'application/pdf')],$extra??[]));}
 public function test_workspace_entry_redirects_guests_and_keeps_product_authorization():void {
  $this->withoutVite();
  $this->withSession(['judibas_admin'=>false])->get('/cv-bank')->assertRedirect('/login');
  $this->getJson('/cv-bank/data')->assertForbidden();
  $unassigned=User::factory()->create(['is_active'=>true,'judibas_administrator'=>false]);
  $this->actingAs($unassigned)->get('/cv-bank')->assertForbidden();
  $hr=$this->hr([$this->companies[0]]);
  $this->actingAs($hr)->get('/cv-bank')->assertOk();
  $this->withSession(['judibas_admin'=>true])->get('/cv-bank')->assertOk();
 }
 public function test_company_departments_jobs_and_public_detail_apply_flow():void {
  $this->get('/careers/'.$this->slugs[0])->assertOk()->assertSee('Cruxlo Developer')->assertDontSee('Skyinfinit Developer')->assertSee('View details')->assertSee('/careers/'.$this->slugs[0].'/jobs/'.$this->jobs[0],false);
  $this->get('/careers/'.$this->slugs[0].'/jobs/'.$this->jobs[0])->assertOk()->assertSee('Faisalabad, Pakistan')->assertSee('Multan, Pakistan')->assertSee('Laravel and Vue')->assertSee('Build and review software.')->assertSee('?job_id='.$this->jobs[0].'#apply',false);
  $this->get('/careers/'.$this->slugs[0].'?job_id='.$this->jobs[0])->assertOk()->assertSee('action="/careers/'.$this->slugs[0].'/apply"',false);
  $this->applicant(0,['company_id'=>$this->companies[1],'preferred_location'=>'Multan, Pakistan'])->assertRedirect('/careers/'.$this->slugs[0].'#apply');
  $row=DB::table('cv_bank_applications')->where('company_id',$this->companies[0])->first();$this->assertSame($this->departments[0],$row->department_id);$this->assertSame($this->jobs[0],$row->job_id);$this->assertSame('Multan, Pakistan',$row->preferred_location);Storage::disk('local')->assertExists($row->cv_path);
  $this->get('/careers/'.$this->slugs[0].'/jobs/'.$this->jobs[1])->assertNotFound();
 }
 public function test_hr_is_scoped_for_data_mutations_private_cv_files_and_settings():void {
  $this->applicant(1)->assertRedirect();$app=DB::table('cv_bank_applications')->where('company_id',$this->companies[1])->first();
  $hr=$this->hr([$this->companies[0]]);$this->withSession(['judibas_admin'=>false])->actingAs($hr);
  $this->getJson('/cv-bank/data?company_id='.$this->companies[0])->assertOk()->assertJsonCount(1,'companies')->assertJsonCount(1,'jobs')->assertJsonCount(1,'departments')->assertJsonCount(0,'applications')->assertJsonPath('can_edit_careers',true);
  $this->getJson('/cv-bank/data?company_id='.$this->companies[1])->assertForbidden();
  $this->postJson('/cv-bank/departments',['company_id'=>$this->companies[0],'id'=>$this->departments[1],'name'=>'Stolen','active'=>true])->assertNotFound();
  $this->postJson('/cv-bank/jobs',['company_id'=>$this->companies[0],'id'=>$this->jobs[1]])->assertNotFound();
  $this->deleteJson('/cv-bank/jobs/'.$this->jobs[1])->assertNotFound();$this->postJson('/cv-bank/jobs/'.$this->jobs[1].'/restore')->assertNotFound();
  $this->patchJson('/cv-bank/applications/'.$app->id,['status'=>'hired'])->assertNotFound();$this->get('/cv-bank/applications/'.$app->id.'/preview')->assertNotFound();$this->get('/cv-bank/applications/'.$app->id.'/cv')->assertNotFound();
  $this->postJson('/cv-bank/settings',['company_id'=>$this->companies[1],'theme'=>'bloom'])->assertForbidden();
  $this->postJson('/cv-bank/company-pages',['company_id'=>$this->companies[0],'slug'=>'changed','published'=>true])->assertForbidden();
  $this->postJson('/cv-bank/departments',['company_id'=>$this->companies[0],'name'=>'HR team','active'=>true])->assertOk();
  $this->postJson('/cv-bank/settings',['company_id'=>$this->companies[0],'theme'=>'bloom','header_brand'=>'Cruxlo Careers'])->assertOk();
  $this->get('/careers/'.$this->slugs[0])->assertOk()->assertSee('theme-bloom',false)->assertSee('Cruxlo Careers');$this->get('/careers/'.$this->slugs[1])->assertOk()->assertSee('theme-studio',false)->assertDontSee('Cruxlo Careers');
 }
 public function test_cross_company_job_department_and_application_spoofing_is_rejected():void {
  $this->postJson('/cv-bank/jobs',['company_id'=>$this->companies[0],'title'=>'Bad job','department_id'=>$this->departments[1],'locations'=>['Lahore']])->assertUnprocessable();
  $this->applicant(0,['preferred_location'=>'Unlisted city'])->assertUnprocessable();$this->applicant(0,['job_id'=>$this->jobs[1]])->assertUnprocessable();$this->applicant(0,['department_id'=>$this->departments[1]])->assertUnprocessable();
  $this->assertSame(0,DB::table('cv_bank_applications')->whereIn('company_id',$this->companies)->count());$this->assertCount(0,Storage::disk('local')->allFiles('cv-bank'));
 }
 public function test_general_applications_work_without_jobs_and_closed_jobs_cannot_accept_applications():void {
  $this->deleteJson('/cv-bank/jobs/'.$this->jobs[0])->assertOk();$this->get('/careers/'.$this->slugs[0])->assertOk()->assertDontSee('Cruxlo Developer')->assertSee('General application');
  $this->applicant(0)->assertUnprocessable();$this->applicant(0,['job_id'=>null])->assertRedirect();
  $this->assertDatabaseHas('cv_bank_applications',['company_id'=>$this->companies[0],'job_id'=>null,'department_id'=>$this->departments[0]]);
  DB::table('cv_bank_jobs')->where('id',$this->jobs[1])->update(['closing_date'=>'2000-01-01']);$this->get('/careers/'.$this->slugs[1].'/jobs/'.$this->jobs[1])->assertNotFound();$this->applicant(1)->assertUnprocessable();
 }
 public function test_company_slugs_publication_and_settings_are_independent():void {
  $this->postJson('/cv-bank/company-pages',['company_id'=>$this->companies[1],'slug'=>$this->slugs[0],'published'=>true])->assertUnprocessable();
  $this->postJson('/cv-bank/company-pages',['company_id'=>$this->companies[1],'slug'=>'invalid slug','published'=>true])->assertUnprocessable();
  $this->postJson('/cv-bank/company-pages',['company_id'=>$this->companies[1],'slug'=>$this->slugs[1],'published'=>false])->assertOk();
  $this->get('/careers/'.$this->slugs[1])->assertNotFound();$this->get('/careers/'.$this->slugs[1].'/jobs/'.$this->jobs[1])->assertNotFound();$this->applicant(1)->assertNotFound();
  $this->get('/careers/'.$this->slugs[1].'?theme=atlas')->assertOk()->assertSee('Design preview');
  foreach(config('cv-bank.themes') as $theme)$this->get('/careers/'.$this->slugs[0].'?theme='.$theme['id'])->assertOk()->assertSee('/careers/'.$this->slugs[0].'/jobs/'.$this->jobs[0],false);
 }
 public function test_private_cv_review_and_permission_revocation():void {
  $this->applicant(0)->assertRedirect();$app=DB::table('cv_bank_applications')->where('company_id',$this->companies[0])->first();$hr=$this->hr([$this->companies[0],$this->companies[1]]);$this->withSession(['judibas_admin'=>false])->actingAs($hr);
  $this->get('/cv-bank/applications/'.$app->id.'/preview')->assertOk()->assertHeader('Content-Type','application/pdf');$this->get('/cv-bank/applications/'.$app->id.'/cv')->assertOk();$this->patchJson('/cv-bank/applications/'.$app->id,['status'=>'shortlisted','notes'=>'Good candidate'])->assertOk();
  UserProductAccess::sync($hr,['product_slugs'=>['cv-bank'],'company_ids'=>[$this->companies[1]]]);$this->get('/cv-bank/applications/'.$app->id.'/cv')->assertNotFound();
  DB::table('user_product_access')->where('user_id',$hr->id)->delete();$this->getJson('/cv-bank/data')->assertForbidden();
 }
 public function test_remote_job_empty_locations_and_department_rename_stay_in_company():void {
  $this->postJson('/cv-bank/jobs',['company_id'=>$this->companies[0],'id'=>$this->jobs[0],'title'=>'Remote Developer','department_id'=>$this->departments[0],'work_mode'=>'remote','locations'=>[]])->assertOk();
  $this->postJson('/cv-bank/departments',['company_id'=>$this->companies[0],'id'=>$this->departments[0],'name'=>'Engineering','active'=>true])->assertOk();
  $this->assertDatabaseHas('cv_bank_jobs',['id'=>$this->jobs[0],'department'=>'Engineering']);$this->assertDatabaseHas('cv_bank_jobs',['id'=>$this->jobs[1],'department'=>'Development']);
  $this->get('/careers/'.$this->slugs[0].'/jobs/'.$this->jobs[0])->assertOk()->assertSee('Engineering')->assertSee('Remote');
 }
 public function test_cv_bank_assignment_requires_a_company_and_legacy_careers_survives():void {
  $this->get('/careers')->assertOk();$this->get('/careers/judibas')->assertOk();
  $this->expectException(\Illuminate\Validation\ValidationException::class);UserProductAccess::validateSelection(['product_slugs'=>['cv-bank'],'company_ids'=>[]]);
 }
 public function test_database_rejects_department_links_across_company_boundaries():void {
  try {DB::transaction(fn()=>DB::table('cv_bank_jobs')->where('id',$this->jobs[0])->update(['department_id'=>$this->departments[1]]));$this->fail('Cross-company department was accepted.');}
  catch(\Illuminate\Database\QueryException $e){$this->assertSame('23503',$e->errorInfo[0]);}
  $this->postJson('/cv-bank/jobs',['company_id'=>$this->companies[0],'title'=>'Multi-city role','department_id'=>$this->departments[0],'locations'=>"Lahore, Pakistan\nMultan, Pakistan"])->assertOk()->assertJsonCount(2,'job.locations');
 }

}
