<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
class CvBankController extends Controller {
 private function authorizeAdmin(Request $r): void { abort_unless(\App\Services\PlatformAccess::product($r,'cv-bank'),403); }
 public function dashboard(Request $r){$this->authorizeAdmin($r);return app(PortalController::class)->index($r,'cv-bank');}
 public function data(Request $r){$this->authorizeAdmin($r);return response()->json(['career_settings'=>$this->careerSettings(),'themes'=>config('cv-bank.themes'),'can_edit_careers'=>$this->canEditCareers($r),'can_assign_editors'=>\App\Services\PlatformAccess::administrator($r),'career_editors'=>\App\Services\PlatformAccess::administrator($r)?DB::table('cv_bank_career_editors')->pluck('user_id'):[],'eligible_editors'=>\App\Services\PlatformAccess::administrator($r)?DB::table('users')->where('is_active',true)->whereIn('id',DB::table('user_product_access')->where('product_slug','cv-bank')->select('user_id'))->orderBy('name')->get(['id','name','email']):[],'departments'=>DB::table('cv_bank_departments')->orderBy('name')->get(),'jobs'=>DB::table('cv_bank_jobs')->orderByDesc('active')->orderBy('title')->get(),'applications'=>DB::table('cv_bank_applications')->leftJoin('cv_bank_jobs','cv_bank_jobs.id','=','cv_bank_applications.job_id')->orderByDesc('cv_bank_applications.id')->get(['cv_bank_applications.*','cv_bank_jobs.title as job_title'])]);}
 public function saveJob(Request $r){$this->authorizeAdmin($r);$v=$r->validate(['id'=>'nullable|integer|exists:cv_bank_jobs,id','title'=>'required|string|max:160','department'=>['required','string',\Illuminate\Validation\Rule::exists('cv_bank_departments','name')->where('active',true)],'description'=>'nullable|string|max:5000','active'=>'sometimes|boolean']);$id=$v['id']??null;unset($v['id']);$v['active']=(bool)($v['active']??true);$v['updated_at']=now();if($id)DB::table('cv_bank_jobs')->where('id',$id)->update($v);else{$v['created_at']=now();DB::table('cv_bank_jobs')->insert($v);}return back()->with('success','Job saved.');}
 public function deleteJob(Request $r,int $id){$this->authorizeAdmin($r);DB::table('cv_bank_jobs')->where('id',$id)->update(['active'=>false,'updated_at'=>now()]);return back()->with('success','Job archived.');}
 public function restoreJob(Request $r, int $id)
 {
     $this->authorizeAdmin($r);
     $job = DB::table('cv_bank_jobs')->find($id);
     abort_unless($job, 404);
     if (!DB::table('cv_bank_departments')->where('name', $job->department)->where('active', true)->exists()) {
         return back()->with('error', 'Activate this job’s department before restoring the job.');
     }
     DB::table('cv_bank_jobs')->where('id', $id)->update(['active'=>true, 'updated_at'=>now()]);
     return back()->with('success', 'Job restored and visible on the careers page.');
 }
 public function updateApplication(Request $r,int $id){$this->authorizeAdmin($r);$v=$r->validate(['status'=>'required|in:new,reviewing,shortlisted,rejected,hired','notes'=>'nullable|string|max:5000']);DB::table('cv_bank_applications')->where('id',$id)->update($v+['updated_at'=>now()]);return back()->with('success','Application updated.');}
 public function saveDepartment(Request $r){
 $this->authorizeAdmin($r);
 $v=$r->validate(['id'=>'nullable|integer|exists:cv_bank_departments,id','name'=>['required','string','max:100',\Illuminate\Validation\Rule::unique('cv_bank_departments','name')->ignore($r->input('id'))],'active'=>'required|boolean']);
 DB::transaction(function()use($v){$id=$v['id']??null;$values=['name'=>trim($v['name']),'active'=>(bool)$v['active'],'updated_at'=>now()];if($id){$old=DB::table('cv_bank_departments')->find($id);DB::table('cv_bank_departments')->where('id',$id)->update($values);DB::table('cv_bank_jobs')->where('department',$old->name)->update(['department'=>$values['name']]);}else{DB::table('cv_bank_departments')->insert($values+['created_at'=>now()]);}});
 return back()->with('success','Department saved.');
 }
 private function careerSettings(): array
 {
     $row=DB::table('cv_bank_settings')->find(1);
     return array_merge(config('cv-bank.content_defaults'),json_decode($row->page_content??'{}',true)??[],['theme'=>$row->theme??'studio','headline'=>$row->headline??'Good people. Meaningful work.','intro'=>$row->intro??'Explore our open roles and find your next chapter. No perfect match? Share your CV with a department and let us get to know you.']);
 }
 private function canEditCareers(Request $r): bool
 {
     return \App\Services\PlatformAccess::administrator($r) || (\App\Services\PlatformAccess::product($r,'cv-bank') && DB::table('cv_bank_career_editors')->where('user_id',$r->user()?->id)->exists());
 }
 public function saveSettings(Request $r)
 {
     $this->authorizeAdmin($r);
     abort_unless($this->canEditCareers($r),403);
     $rules=['theme'=>['sometimes','required',\Illuminate\Validation\Rule::in(array_column(config('cv-bank.themes'),'id'))],'headline'=>'sometimes|required|string|max:160','intro'=>'sometimes|required|string|max:1200','career_editors'=>'sometimes|array','career_editors.*'=>['integer','distinct',\Illuminate\Validation\Rule::exists('users','id')->where('is_active',true)]];
     foreach(config('cv-bank.content_defaults') as $key=>$default) {
         $rules[$key]=is_bool($default)?'sometimes|required|boolean':(in_array($key,['footer_link_label','footer_link_url'],true)?'sometimes|nullable|string|max:500':'sometimes|required|string|max:500');
     }
     $safeLink=function($attribute,$value,$fail){
         if(is_string($value) && (preg_match('/[\\\\\x00-\x20\x7f]/',$value) || !(preg_match('#^/(?!/)#',$value) || (filter_var($value,FILTER_VALIDATE_URL) && in_array(strtolower(parse_url($value,PHP_URL_SCHEME)),['https','http'],true))))) $fail('Use a valid https:// address or a site path starting with /.');
     };
     $rules['header_brand_url']=['sometimes','required','string','max:500',$safeLink];
     $rules['footer_link_url']=['sometimes','nullable','string','max:500',$safeLink];
     $v=$r->validate($rules);
     $current=$this->careerSettings();
     $merged=array_merge($current,$v);
     if(!empty($merged['footer_link_label'])!==!empty($merged['footer_link_url'])) {
         throw \Illuminate\Validation\ValidationException::withMessages(['footer_link_url'=>'Enter both a footer link label and its destination, or leave both blank.']);
     }
     if(array_key_exists('career_editors',$v)) {
         abort_unless(\App\Services\PlatformAccess::administrator($r),403);
         $eligible=DB::table('user_product_access')->where('product_slug','cv-bank')->pluck('user_id');
         abort_if(collect($v['career_editors'])->diff($eligible)->isNotEmpty(),422,'Editors must have CV Bank access.');
     }
     DB::transaction(function()use($v,$merged){
         $content=array_intersect_key($merged,config('cv-bank.content_defaults'));
         DB::table('cv_bank_settings')->updateOrInsert(['id'=>1],['theme'=>$merged['theme'],'headline'=>$merged['headline'],'intro'=>$merged['intro'],'page_content'=>json_encode($content),'updated_at'=>now()]);
         if(array_key_exists('career_editors',$v)) {
             DB::table('cv_bank_career_editors')->delete();
             foreach($v['career_editors'] as $id) DB::table('cv_bank_career_editors')->insert(['user_id'=>$id]);
         }
     });
     return $r->expectsJson()?response()->json(['message'=>'Careers page settings saved.','settings'=>$this->careerSettings()]):back()->with('success','Careers page published.');
 }
 public function careers(Request $r)
 {
     $settings=$this->careerSettings();
     $preview=$r->filled('theme');
     if($preview) {
         abort_unless($this->canEditCareers($r),403);
         $r->validate(['theme'=>['required',\Illuminate\Validation\Rule::in(array_column(config('cv-bank.themes'),'id'))]]);
         $settings['theme']=$r->query('theme');
     }
     return response()->view('cv-bank.careers',['settings'=>$settings,'preview'=>$preview,'departments'=>DB::table('cv_bank_departments')->where('active',true)->orderBy('name')->get(),'jobs'=>DB::table('cv_bank_jobs')->whereIn('department',DB::table('cv_bank_departments')->where('active',true)->select('name'))->where('active',true)->orderBy('title')->get()])->header('Cache-Control','private, no-store');
 }
 public function apply(Request $r){$v=$r->validate(['job_id'=>'nullable|integer|exists:cv_bank_jobs,id','department'=>['required','string',\Illuminate\Validation\Rule::exists('cv_bank_departments','name')->where('active',true)],'name'=>'required|string|max:160','email'=>'required|email|max:190','phone'=>'nullable|string|max:50','cover_letter'=>'nullable|string|max:10000','cv'=>'required|file|mimes:pdf,doc,docx|max:10240']);if(!empty($v['job_id'])){ $job=DB::table('cv_bank_jobs')->where('active',true)->find($v['job_id']);abort_unless($job && $job->department===$v['department'],422,'Select the department matching the open position.'); }$v['cv_path']=$r->file('cv')->store('cv-bank','local');unset($v['cv']);DB::table('cv_bank_applications')->insert($v+['status'=>'new','created_at'=>now(),'updated_at'=>now()]);return back()->with('success','Application submitted successfully. Our team will review your CV.');}
 public function preview(Request $r, int $id)
 {
     $this->authorizeAdmin($r);
     $row = DB::table('cv_bank_applications')->find($id);
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
 public function download(Request $r,int $id){$this->authorizeAdmin($r);$row=DB::table('cv_bank_applications')->find($id);abort_unless($row,404);return Storage::disk('local')->download($row->cv_path,'CV-'.$row->name.'.'.pathinfo($row->cv_path,PATHINFO_EXTENSION));}
}
