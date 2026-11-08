<?php
require __DIR__.'/../vendor/autoload.php';$app=require __DIR__.'/../bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\{DB,View};
use Illuminate\Http\Request;
use App\Http\Controllers\CvBankController;
DB::beginTransaction();
try{
 View::share('errors',new Illuminate\Support\ViewErrorBag());$session=app('session')->driver();$session->put('judibas_admin',true);$session->put('_token','cv-browser-token');
 $request=function(array $body=[]){$r=Request::create('/cv-fixture','POST',$body);$r->headers->set('Accept','application/json');$r->setLaravelSession(app('session')->driver());return $r;};
 $publicRequest=function(array $body=[])use($request){$r=$request($body);$r->query->replace($body);return $r;};
 $controller=app(CvBankController::class);$companies=[];$datasets=[];$public=[];
 foreach(['Alpha','Beta'] as $name){
  $id=DB::table('communication_companies')->insertGetId(['name'=>'Browser '.$name,'created_at'=>now(),'updated_at'=>now()]);$slug='cv-bank-browser-'.strtolower($name);
  $controller->saveCompanyPage($request(['company_id'=>$id,'slug'=>$slug,'published'=>true]));$controller->saveDepartment($request(['company_id'=>$id,'name'=>'Development','active'=>true]));
  $d=DB::table('cv_bank_departments')->where('company_id',$id)->first();$job=json_decode($controller->saveJob($request(['company_id'=>$id,'department_id'=>$d->id,'title'=>$name.' Laravel Developer','description'=>'Build thoughtful Laravel and Vue products with our team.','locations'=>['Faisalabad, Pakistan','Multan, Pakistan'],'employment_type'=>'full_time','work_mode'=>'hybrid','experience'=>'2-4 years','requirements'=>'Laravel, Vue and PostgreSQL.','responsibilities'=>'Build APIs and review code.']))->getContent(),true)['job'];
  $companies[]=['id'=>$id,'name'=>'Browser '.$name,'career_slug'=>$slug,'career_published'=>true,'career_url'=>'/careers/'.$slug];
  $settings=array_merge(config('cv-bank.content_defaults'),['header_brand'=>'Browser '.$name,'footer_brand'=>'Browser '.$name,'theme'=>'studio','headline'=>'Good people. Meaningful work.','intro'=>'Build useful software with our team.']);
  $datasets[$id]=['company_id'=>$id,'company_name'=>'Browser '.$name,'career_url'=>'/careers/'.$slug,'company_page'=>['company_id'=>$id,'slug'=>$slug,'published'=>true],'career_settings'=>$settings,'themes'=>config('cv-bank.themes'),'can_edit_careers'=>true,'can_assign_editors'=>true,'career_editors'=>[],'eligible_editors'=>[],'departments'=>[(array)$d],'jobs'=>[$job],'applications'=>[]];
  $base='/careers/'.$slug;
  $public[$base]=$controller->careers($publicRequest(),$slug)->getContent();$public[$base.'?job_id='.$job['id']]=$controller->careers($publicRequest(['job_id'=>$job['id']]),$slug)->getContent();$public[$base.'/jobs/'.$job['id']]=$controller->jobDetails($publicRequest(),$slug,$job['id'])->getContent();
  foreach(config('cv-bank.themes') as $theme)$public[$base.'?theme='.$theme['id']]=$controller->careers($publicRequest(['theme'=>$theme['id']]),$slug)->getContent();
 }
 if(!is_dir(__DIR__.'/../.preview'))mkdir(__DIR__.'/../.preview',0777,true);
 file_put_contents(__DIR__.'/../.preview/cv-bank-company-fixtures.json',json_encode(compact('companies','datasets','public'),JSON_THROW_ON_ERROR));
 echo "Rendered company-scoped career fixtures; all database changes rolled back.\n";
}finally{DB::rollBack();}
