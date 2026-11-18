<?php
namespace App\Http\Controllers;
use App\Services\{CvBankAccess as Access,PlatformAccess,CommunicationAccess};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Storage};
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
class CvBankController extends Controller {
 public function dashboard(Request $r){
  if(!PlatformAccess::super($r)&&!$r->user())return redirect('/login');
  Access::authorize($r);return app(PortalController::class)->index($r,'cv-bank');
 }
 public function data(Request $r){
  Access::authorize($r);$companies=Access::companies($r);$company=$companies->isNotEmpty()?Access::company($r):null;$id=$company?->id;
  $eligible=DB::table('users')->where('is_active',true)->whereIn('id',DB::table('user_product_access')->where('product_slug','cv-bank')->select('user_id'));
  \App\Services\ProductCompanyAccess::users($eligible,'cv-bank',$id?[$id]:[]);
  $eligibleIds=(clone $eligible)->pluck('id');
  foreach(['department_count'=>'cv_bank_departments','job_count'=>'cv_bank_jobs','application_count'=>'cv_bank_applications'] as $field=>$table){$counts=DB::table($table)->whereIn('company_id',$companies->pluck('id'))->selectRaw('company_id,COUNT(*) as total')->groupBy('company_id')->pluck('total','company_id');foreach($companies as $c)$c->$field=(int)($counts[$c->id]??0);}
  return response()->json(['companies'=>$companies,'today'=>now('Asia/Karachi')->toDateString(),'company_id'=>$id,'company_name'=>$company?->name,'career_url'=>$company?->career_url,'company_page'=>$id?DB::table('cv_bank_company_pages')->where('company_id',$id)->first():null,'can_manage_companies'=>PlatformAccess::super($r),'career_settings'=>$this->careerSettings($id),'themes'=>config('cv-bank.themes'),'can_edit_careers'=>$id!==null,'can_assign_editors'=>PlatformAccess::administrator($r),'career_editors'=>$id?DB::table('cv_bank_career_editors')->where('company_id',$id)->whereIn('user_id',$eligibleIds)->pluck('user_id'):[],'eligible_editors'=>PlatformAccess::administrator($r)?$eligible->orderBy('name')->get(['id','name','email']):[],
   'departments'=>$id?DB::table('cv_bank_departments')->where('company_id',$id)->orderBy('name')->get():[],
   'jobs'=>$id?DB::table('cv_bank_jobs')->where('company_id',$id)->orderByDesc('active')->orderBy('title')->get()->map(fn($j)=>$this->jobData($j,$company?->career_url)):[],
   'applications'=>$id?DB::table('cv_bank_applications as a')->leftJoin('cv_bank_jobs as j','j.id','=','a.job_id')->leftJoin('cv_bank_departments as d','d.id','=','a.department_id')->where('a.company_id',$id)->orderByDesc('a.id')->select(['a.*','a.department as department_snapshot','j.title as job_title'])->selectRaw('COALESCE(d.name,a.department) as department')->get():[]]);
 }
 private function respond(Request $r,int $company,string $message,array $extra=[]){return $r->expectsJson()?response()->json(['message'=>$message]+$extra):redirect(\App\Services\CompanyWorkspace::url('cv-bank',$company))->with('success',$message);}
 private function jobData(object $job,?string $url):object {$job->locations=json_decode($job->locations??'[]',true)?:[];$job->detail_url=$url?$url.'/jobs/'.$job->id:null;return $job;}
 private function department(Request $r,int $company):object {
  $r->validate(['department_id'=>'nullable|integer|min:1','department'=>'required_without:department_id|nullable|string|max:100']);
  $q=DB::table('cv_bank_departments')->where('company_id',$company)->where('active',true);
  if($r->filled('department_id'))$q->where('id',$r->integer('department_id'));else $q->where('name',$r->input('department'));
  $d=$q->first();abort_unless($d,422,'Choose an active department in this company.');return $d;
 }
 public function saveJob(Request $r){
  $company=Access::company($r);$old=$r->filled('id')?Access::row($r,'cv_bank_jobs',$r->integer('id')):null;abort_if($old&&$old->company_id!==$company->id,404);
  if(is_string($r->input('locations')))$r->merge(['locations'=>array_values(array_unique(array_filter(array_map('trim',preg_split('/[\r\n]+/',$r->input('locations'))))))]);
  $remote=$r->input('work_mode',$old->work_mode??'onsite')==='remote';
  $v=$r->validate(['title'=>'required|string|max:160','description'=>'nullable|string|max:10000','active'=>'sometimes|boolean','locations'=>[Rule::requiredIf(!$remote),'array',$remote?'min:0':'min:1','max:20'],'locations.*'=>'required|string|max:160','employment_type'=>'sometimes|required|in:full_time,part_time,contract,internship','work_mode'=>'sometimes|required|in:onsite,hybrid,remote','experience'=>'nullable|string|max:160','responsibilities'=>'nullable|string|max:10000','requirements'=>'nullable|string|max:10000','closing_date'=>'nullable|date_format:Y-m-d']);
  $d=$this->department($r,$company->id);
  abort_if($old&&$old->department_id!==$d->id&&DB::table('cv_bank_applications')->where('job_id',$old->id)->exists(),422,'This job already has applications. Create a new job to change its department.');
  $values=$v+['active'=>$old?->active??true,'employment_type'=>$old?->employment_type??'full_time','work_mode'=>$old?->work_mode??'onsite'];$values['locations']=json_encode($v['locations']??json_decode($old->locations??'[]',true));$values+=['company_id'=>$company->id,'department_id'=>$d->id,'department'=>$d->name,'updated_at'=>now()];
  if($old){DB::table('cv_bank_jobs')->where('id',$old->id)->update($values);$id=$old->id;}else $id=DB::table('cv_bank_jobs')->insertGetId($values+['created_at'=>now()]);
  return $this->respond($r,$company->id,'Job saved.',['job'=>$this->jobData(DB::table('cv_bank_jobs')->find($id),$company->career_url)]);
 }
 public function deleteJob(Request $r,int $id){$job=Access::row($r,'cv_bank_jobs',$id);DB::table('cv_bank_jobs')->where('id',$id)->update(['active'=>false,'updated_at'=>now()]);return $this->respond($r,$job->company_id,'Job archived.');}
 public function restoreJob(Request $r,int $id){$job=Access::row($r,'cv_bank_jobs',$id);abort_unless(DB::table('cv_bank_departments')->where('company_id',$job->company_id)->where('id',$job->department_id)->where('active',true)->exists(),422,'Activate this job department before restoring the job.');DB::table('cv_bank_jobs')->where('id',$id)->update(['active'=>true,'updated_at'=>now()]);return $this->respond($r,$job->company_id,'Job restored.');}
 public function updateApplication(Request $r,int $id){$row=Access::row($r,'cv_bank_applications',$id);$v=$r->validate(['status'=>'required|in:new,reviewing,shortlisted,rejected,hired','notes'=>'nullable|string|max:5000']);DB::table('cv_bank_applications')->where('id',$id)->update($v+['updated_at'=>now()]);return $this->respond($r,$row->company_id,'Application updated.');}
 public function saveDepartment(Request $r){
  $company=Access::company($r);$old=$r->filled('id')?Access::row($r,'cv_bank_departments',$r->integer('id')):null;abort_if($old&&$old->company_id!==$company->id,404);
  $r->merge(['name'=>trim((string)$r->input('name'))]);$v=$r->validate(['name'=>['required','string','max:100',Rule::unique('cv_bank_departments','name')->where('company_id',$company->id)->ignore($old?->id)],'active'=>'required|boolean']);
  DB::transaction(function()use($v,$old,$company){$values=$v+['company_id'=>$company->id,'updated_at'=>now()];if($old){DB::table('cv_bank_departments')->where('id',$old->id)->update($values);DB::table('cv_bank_jobs')->where('company_id',$company->id)->where('department_id',$old->id)->update(['department'=>$v['name']]);}else DB::table('cv_bank_departments')->insert($values+['created_at'=>now()]);});
  return $this->respond($r,$company->id,'Department saved.');
 }
 public function saveCompanyPage(Request $r){
  Access::authorize($r);abort_unless(PlatformAccess::super($r),403,'Only Super Admin can create or change company career URLs.');
  $company=Access::company($r);$old=DB::table('cv_bank_company_pages')->where('company_id',$company->id)->first();$r->merge(['slug'=>strtolower(trim((string)$r->input('slug')))]);
  $v=$r->validate(['slug'=>['required','string','max:100','regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',Rule::notIn(['apply','jobs']),Rule::unique('cv_bank_company_pages','slug')->ignore($old?->id)],'published'=>'required|boolean']);
  DB::table('cv_bank_company_pages')->updateOrInsert(['company_id'=>$company->id],$v+['updated_at'=>now()]+($old?[]:['created_at'=>now()]));
  return $this->respond($r,$company->id,'Company careers page saved.',['career_url'=>'/careers/'.$v['slug']]);
 }
 private function careerSettings(?int $company=null):array {
  $company??=Access::legacyId();$row=DB::table('cv_bank_settings')->where('company_id',$company)->first();$name=DB::table('communication_companies')->where('id',$company)->value('name')??'Judibas';
  $defaults=config('cv-bank.content_defaults');$slug=DB::table('cv_bank_company_pages')->where('company_id',$company)->value('slug');$defaults['header_brand_url']=$slug?'/careers/'.$slug:'/';$defaults['header_brand']=$name;$defaults['footer_brand']=$name;$defaults['footer_copyright']='Copyright {year} '.$name;
  return array_merge($defaults,json_decode($row->page_content??'{}',true)??[],['theme'=>$row->theme??'studio','headline'=>$row->headline??'Good people. Meaningful work.','intro'=>$row->intro??'Explore our open roles and find your next chapter. No perfect match? Share your CV with a department and let us get to know you.']);
 }
 private function canEditCareers(Request $r,?int $company=null):bool {if(!PlatformAccess::product($r,'cv-bank'))return false;return Access::companies($r)->contains('id',$company??Access::legacyId());}
 public function saveSettings(Request $r){
  $company=Access::company($r);$id=$company->id;abort_unless(DB::table('cv_bank_company_pages')->where('company_id',$id)->exists(),422,'Ask Super Admin to create this company careers page first.');
  $rules=['theme'=>['sometimes','required',Rule::in(array_column(config('cv-bank.themes'),'id'))],'headline'=>'sometimes|required|string|max:160','intro'=>'sometimes|required|string|max:1200','career_editors'=>'sometimes|array','career_editors.*'=>['integer','distinct',Rule::exists('users','id')->where('is_active',true)]];
  foreach(config('cv-bank.content_defaults') as $key=>$default)$rules[$key]=is_bool($default)?'sometimes|required|boolean':(in_array($key,['footer_link_label','footer_link_url'],true)?'sometimes|nullable|string|max:500':'sometimes|required|string|max:500');
  $safeLink=function($attribute,$value,$fail){if(is_string($value)&&(preg_match('/[\\\\\x00-\x20\x7f]/',$value)||!(preg_match('#^/(?!/)#',$value)||(filter_var($value,FILTER_VALIDATE_URL)&&in_array(strtolower(parse_url($value,PHP_URL_SCHEME)),['https','http'],true)))))$fail('Use a valid https:// address or a site path starting with /.');};
  $rules['header_brand_url']=['sometimes','required','string','max:500',$safeLink];$rules['footer_link_url']=['sometimes','nullable','string','max:500',$safeLink];$v=$r->validate($rules);$current=$this->careerSettings($id);$merged=array_merge($current,$v);
  if(!empty($merged['footer_link_label'])!==!empty($merged['footer_link_url']))throw \Illuminate\Validation\ValidationException::withMessages(['footer_link_url'=>'Enter both a footer link label and its destination, or leave both blank.']);
  if(array_key_exists('career_editors',$v)){
   abort_unless(PlatformAccess::administrator($r),403);$eligible=DB::table('users')->where('is_active',true)->whereIn('id',DB::table('user_product_access')->where('product_slug','cv-bank')->select('user_id'));\App\Services\ProductCompanyAccess::users($eligible,'cv-bank',[$id]);abort_if(collect($v['career_editors'])->diff($eligible->pluck('id'))->isNotEmpty(),422,'Editors must have CV Bank access and be assigned to this company.');
  }
  DB::transaction(function()use($v,$merged,$id){$old=DB::table('cv_bank_settings')->where('company_id',$id)->first();DB::table('cv_bank_settings')->updateOrInsert(['company_id'=>$id],['theme'=>$merged['theme'],'headline'=>$merged['headline'],'intro'=>$merged['intro'],'page_content'=>json_encode(array_intersect_key($merged,config('cv-bank.content_defaults'))),'updated_at'=>now()]+($old?[]:['created_at'=>now()]));if(array_key_exists('career_editors',$v)){DB::table('cv_bank_career_editors')->where('company_id',$id)->delete();foreach($v['career_editors'] as $user)DB::table('cv_bank_career_editors')->insert(['company_id'=>$id,'user_id'=>$user]);}});
  return $this->respond($r,$id,'Careers page settings saved.',['settings'=>$this->careerSettings($id)]);
 }
 private function publicJobs(int $company){return DB::table('cv_bank_jobs as j')->join('cv_bank_departments as d',function($q){$q->on('d.id','=','j.department_id')->on('d.company_id','=','j.company_id');})->where('j.company_id',$company)->where('j.active',true)->where('d.active',true)->where(fn($q)=>$q->whereNull('j.closing_date')->orWhere('j.closing_date','>=',now('Asia/Karachi')->toDateString()))->select('j.*');}
 public function careers(Request $r,?string $companySlug=null){
  $page=Access::page($companySlug);$settings=$this->careerSettings($page->company_id);$preview=$r->filled('theme');if($preview){abort_unless($this->canEditCareers($r,$page->company_id),403);$r->validate(['theme'=>['required',Rule::in(array_column(config('cv-bank.themes'),'id'))]]);$settings['theme']=$r->query('theme');}else abort_unless($page->published,404);
  $jobs=$this->publicJobs($page->company_id)->orderBy('j.title')->get()->map(fn($j)=>$this->jobData($j,'/careers/'.$page->slug));
  $selectedJob=null;if($r->filled('job_id')){$r->validate(['job_id'=>'integer|min:1']);$selectedJob=$jobs->firstWhere('id',$r->integer('job_id'));abort_unless($selectedJob,404,'This position is no longer accepting applications.');}
  return response()->view('cv-bank.careers',['companyPage'=>$page,'careerUrl'=>'/careers/'.$page->slug,'selectedJob'=>$selectedJob,'settings'=>$settings,'preview'=>$preview,'departments'=>DB::table('cv_bank_departments')->where('company_id',$page->company_id)->where('active',true)->orderBy('name')->get(),'jobs'=>$jobs])->header('Cache-Control','private, no-store');
 }
 public function jobDetails(Request $r,string $companySlug,int $id){
  $page=Access::page($companySlug);abort_unless($page->published,404);$job=$this->publicJobs($page->company_id)->where('j.id',$id)->first();abort_unless($job,404);$job=$this->jobData($job,'/careers/'.$page->slug);
  return response()->view('cv-bank.job-details',['companyPage'=>$page,'job'=>$job,'settings'=>$this->careerSettings($page->company_id),'careerUrl'=>'/careers/'.$page->slug])->header('Cache-Control','private, no-store');
 }
 public function apply(Request $r,?string $companySlug=null){
  $page=Access::page($companySlug);abort_unless($page->published,404);$d=$this->department($r,$page->company_id);
  $v=$r->validate(['job_id'=>'nullable|integer|min:1','name'=>'required|string|max:160','email'=>'required|email|max:190','phone'=>'nullable|string|max:50','cover_letter'=>'nullable|string|max:10000','preferred_location'=>'nullable|string|max:160','cv'=>'required|file|mimes:pdf,doc,docx|max:10240']);
  if(!empty($v['job_id'])){$job=$this->publicJobs($page->company_id)->where('j.id',$v['job_id'])->first();abort_unless($job&&$job->department_id===$d->id,422,'Select an open position and its department in this company.');$locations=json_decode($job->locations??'[]',true)?:[];if(!empty($v['preferred_location'])&&$locations){$location=collect($locations)->first(fn($location)=>strcasecmp(trim($location),trim($v['preferred_location']))===0);abort_unless($location,422,'Choose a preferred location listed for this job.');$v['preferred_location']=$location;}}
  $path=$r->file('cv')->store('cv-bank','local');unset($v['cv']);try{DB::table('cv_bank_applications')->insert($v+['company_id'=>$page->company_id,'department_id'=>$d->id,'department'=>$d->name,'cv_path'=>$path,'status'=>'new','created_at'=>now(),'updated_at'=>now()]);}catch(\Throwable $e){Storage::disk('local')->delete($path);throw $e;}
  return redirect('/careers/'.$page->slug.'#apply')->with('success','Application submitted successfully. Our team will review your CV.');
 }
 public function preview(Request $r, int $id)
 {
     $row = Access::row($r,'cv_bank_applications',$id);
     abort_unless($row && Storage::disk('local')->exists($row->cv_path), 404);
     $extension = strtolower(pathinfo($row->cv_path, PATHINFO_EXTENSION));
     if ($extension === 'pdf') {
         return response()->file(Storage::disk('local')->path($row->cv_path), [
             'Content-Type'=>'application/pdf', 'Content-Disposition'=>'inline; filename="cv.pdf"',
             'Cache-Control'=>'private, no-store', 'X-Content-Type-Options'=>'nosniff',
         ]);
     }
     $paragraphs = [];
     if ($extension === 'docx' && class_exists(\ZipArchive::class)) {
         $zip = new \ZipArchive();
         if ($zip->open(Storage::disk('local')->path($row->cv_path)) === true) {
             $stat = $zip->statName('word/document.xml');
             if ($stat && $stat['size'] <= 10 * 1024 * 1024) {
                 $xml = $zip->getFromName('word/document.xml');
                 $document = new \DOMDocument();
                 if (@$document->loadXML($xml, LIBXML_NONET)) {
                     $xpath = new \DOMXPath($document);
                     $xpath->registerNamespace('w','http://schemas.openxmlformats.org/wordprocessingml/2006/main');
                     foreach ($xpath->query('//w:body//w:p') as $paragraph) {
                         $text = '';
                         foreach ($xpath->query('.//w:t', $paragraph) as $node) $text .= $node->textContent;
                         if (trim($text) !== '') $paragraphs[] = $text;
                     }
                 }
             }
             $zip->close();
         }
     }
     return response()->view('cv-bank.preview', compact('row','paragraphs'))->header('Cache-Control','private, no-store');
 }
 public function download(Request $r,int $id){$row=Access::row($r,'cv_bank_applications',$id);abort_unless(Storage::disk('local')->exists($row->cv_path),404);return Storage::disk('local')->download($row->cv_path,'CV-'.Str::slug($row->name).'.'.pathinfo($row->cv_path,PATHINFO_EXTENSION),['Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff']);}
}
