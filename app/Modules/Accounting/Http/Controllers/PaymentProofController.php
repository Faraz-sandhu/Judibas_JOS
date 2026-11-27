<?php
namespace App\Modules\Accounting\Http\Controllers;
use App\Modules\Accounting\Services\{AccountingAccess as Access,Ledger,Permissions,ApprovalFlow,PaymentProofs,ImprestFunds,AdminRecordHistory};
use App\Services\PlatformAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Storage};
class PaymentProofController {
 private function journal(Request $r,int $company,int $id):object{
  Access::company($r,$company);$j=DB::table('accounting_journals')->where('company_id',$company)->where('id',$id)->first();abort_unless($j,404);
  $canView=Permissions::can($r,Permissions::journalGroup($j->kind).'.view')||Permissions::can($r,'workflow.view');
  $movement=ImprestFunds::movement($id);
  if($movement){$fund=DB::table('accounting_imprest_funds')->where('company_id',$company)->where('id',$movement->fund_id)->first();$canView=$canView||(Permissions::can($r,'imprest.view')&&$fund&&(Permissions::can($r,'imprest.manage')||Permissions::can($r,'imprest.post')||$fund->user_id===$r->user()?->id));}
  abort_unless($canView,403);
  if(ImprestFunds::restricted($r))ImprestFunds::authorizeJournal($r,$company,$id);return $j;
 }
 private function canUpload(Request $r,object $j):bool{
  if(PlatformAccess::super($r))return true;
  if($j->status!=='draft')return false;
  $a=ApprovalFlow::approval($j->company_id,'journal',$j->id);
  if($a&&!in_array($a->stage,['returned','withdrawn'],true))return $a->stage==='officer_confirm'&&ApprovalFlow::headReviewer($r,'workflow.finance_verify');
  $movement=ImprestFunds::movement($j->id);if($movement)return Permissions::can($r,'imprest.manage')||($movement->type==='spending'&&Permissions::can($r,'imprest.create'));
  return Permissions::can($r,Permissions::journalGroup($j->kind).'.update')||Permissions::can($r,Permissions::journalGroup($j->kind).'.create');
 }
 public function index(Request $r,int $id){
  $company=(int)$r->validate(['company_id'=>'required|integer'])['company_id'];$j=$this->journal($r,$company,$id);$current=PaymentProofs::current($j);
  $proofs=DB::table('accounting_payment_proofs')->where('company_id',$company)->where('journal_id',$id)->whereNull('removed_at')->orderByDesc('id')->get(['id','original_name','mime','size','uploaded_name','created_at','document_version'])->map(fn($p)=>(array)$p+['current'=>$current&&$p->id===$current->id,'url'=>'/accounting/api/payment-proofs/'.$p->id.'?company_id='.$company]);
  return ['show_on_voucher'=>!(in_array($j->kind,['general','opening'],true)&&!ImprestFunds::movement($j->id)),'required'=>PaymentProofs::required($j),'has_current'=>(bool)$current,'can_upload'=>$this->canUpload($r,$j),'is_super_admin'=>PlatformAccess::super($r),'proofs'=>$proofs];
 }
 public function store(Request $r,int $id){
  $v=$r->validate(['company_id'=>'required|integer','version'=>'required|integer|min:1','payment_proof'=>'required|file|max:10240|mimes:jpg,jpeg,png,webp,pdf']);$company=(int)$v['company_id'];$path=null;
  try{DB::transaction(function()use($r,$v,$company,$id,&$path){
   Ledger::lock($company);$j=$this->journal($r,$company,$id);AdminRecordHistory::assertLive('journal',$company,$id);abort_unless($this->canUpload($r,$j),403,'Payment proof must be uploaded by the authorised Judibas Finance Officer at their confirmation stage.');abort_unless($j->version===(int)$v['version'],409,'The voucher changed. Reopen the saved voucher before attaching proof.');abort_if(ImprestFunds::movement($id)?->void,422,'This voucher was cancelled.');
   $file=$r->file('payment_proof');$mime=$file->getMimeType();abort_unless(in_array($mime,['image/jpeg','image/png','image/webp','application/pdf'],true),422,'Choose a JPG, PNG, WebP or PDF payment proof.');$path=$file->store('accounting-payment-proofs/'.$company,'local');abort_unless($path,500,'Unable to save payment proof.');
   $name=mb_substr(str_replace(["\r","\n",'\\','/'],'_',basename($file->getClientOriginalName())),0,240);
   DB::table('accounting_payment_proofs')->insert(['company_id'=>$company,'journal_id'=>$id,'document_version'=>$j->version,'document_hash'=>PaymentProofs::fingerprint($j),'path'=>$path,'original_name'=>$name,'mime'=>$mime,'size'=>$file->getSize(),'sha256'=>hash_file('sha256',$file->getRealPath()),'uploaded_by'=>$r->user()?->id,'uploaded_name'=>PlatformAccess::super($r)?'Super Admin':$r->user()->name,'created_at'=>now(),'updated_at'=>now()]);
   Access::audit($r,$company,'Payment proof attached',$j->number,['document_version'=>$j->version]);
  });}catch(\Throwable $e){if($path)Storage::disk('local')->delete($path);throw $e;}
  return ['message'=>'Payment proof attached to the current saved voucher.'];
 }
 public function show(Request $r,int $id){
  $company=(int)$r->validate(['company_id'=>'required|integer'])['company_id'];$p=DB::table('accounting_payment_proofs')->where('company_id',$company)->where('id',$id)->first();abort_unless($p,404);$this->journal($r,$company,$p->journal_id);abort_if($p->removed_at&&!PlatformAccess::super($r),404);abort_unless(Storage::disk('local')->exists($p->path),404);
  $headers=['Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff','Content-Security-Policy'=>"frame-ancestors 'self'"];
  Access::audit($r,$company,'Payment proof viewed','#'.$id);
  return $r->boolean('download')?Storage::disk('local')->download($p->path,$p->original_name,$headers):response()->file(Storage::disk('local')->path($p->path),$headers+['Content-Type'=>$p->mime]);
 }
 public function remove(Request $r,int $id){
  abort_unless(PlatformAccess::super($r),403,'Only Super Admin can remove payment proof.');$v=$r->validate(['company_id'=>'required|integer','reason'=>'required|string|min:5|max:2000']);$company=(int)$v['company_id'];
  DB::transaction(function()use($r,$v,$company,$id){Ledger::lock($company);$p=DB::table('accounting_payment_proofs')->where('company_id',$company)->where('id',$id)->whereNull('removed_at')->first();abort_unless($p,404);$j=$this->journal($r,$company,$p->journal_id);$current=PaymentProofs::current($j);DB::table('accounting_payment_proofs')->where('id',$id)->update(['removed_at'=>now(),'updated_at'=>now()]);
   $a=ApprovalFlow::approval($company,'journal',$j->id);if($current?->id===$id&&PaymentProofs::required($j)&&$a&&in_array($a->stage,['officer_confirm','manager_review'],true)){
    DB::table('accounting_approvals')->where('id',$a->id)->update(['stage'=>'officer_confirm','version'=>$a->version+1,'updated_at'=>now()]);ApprovalFlow::event($r,$a->id,'payment_proof_removed',$a->stage,'officer_confirm',$v['reason']);
   }AdminRecordHistory::record($r,$company,'payment-proof',$p,'delete',$v['reason']);
  });return ['message'=>'Payment proof removed from active attachments; audit history retained.'];
 }
}
