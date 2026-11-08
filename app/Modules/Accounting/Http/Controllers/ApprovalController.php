<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Modules\Accounting\Services\{AccountingAccess as Access, ApprovalFlow as Flow, Ledger, Permissions};
use App\Services\PlatformAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApprovalController
{
    private function decorate(Request $r, object $approval): array
    {
        $document=Flow::document($approval->company_id,$approval->document_type,$approval->document_id);
        $events=DB::table('accounting_approval_events')->where('approval_id',$approval->id)->orderBy('id')->get();
        $permission=Flow::PERMISSIONS[$approval->stage]??null;
        $paper=$approval->workflow_mode==='paper';$copy=$paper?Flow::signedCopy($approval):null;
        $copies=DB::table('accounting_signed_copies')->where('company_id',$approval->company_id)->where('approval_id',$approval->id)->orderByDesc('id')->get(['id','original_name','mime','size','uploaded_name','created_at','document_version','submission_version'])->map(fn($c)=>(array)$c+['url'=>'/accounting/api/signed-copies/'.$c->id.'?company_id='.$approval->company_id,'current'=>$c->submission_version===$approval->submission_version&&$c->document_version===$approval->document_version]);
        $samePreparer=$r->user()?->id&&$approval->prepared_by===$r->user()->id;
        $sameCeo=$r->user()?->id&&$approval->ceo_by===$r->user()->id;
        $sameUploader=$copy&&$r->user()?->id&&$copy->uploaded_by===$r->user()->id;
        $separation=PlatformAccess::administrator($r)||(!($samePreparer&&in_array($approval->stage,['finance_review','ceo_review','manager_review']))&&!($sameCeo&&$approval->stage==='manager_review')&&!($paper&&$sameUploader&&$approval->stage==='manager_review'));
        $canAct=$approval->stage!=='paper_ceo'&&(!$paper||$copy)&&$permission&&Permissions::can($r,$permission)&&$separation&&($approval->stage!=='manager_review'||Permissions::can($r,$document['permission'].'.post'));
        return (array)$approval+['stage_label'=>$paper&&$approval->stage==='manager_review'?'Finance Head verification':(Flow::STAGES[$approval->stage]??$approval->stage),'document'=>$document['detail'],'events'=>$events,
            'signed_copies'=>$copies,'can_upload_signed'=>$paper&&$approval->stage==='paper_ceo'&&Permissions::can($r,'workflow.upload_signed'),'can_approve'=>(bool)$canAct,'can_return'=>(bool)($permission&&Permissions::can($r,$permission)),
            'can_withdraw'=>in_array($approval->stage,array_keys(Flow::PERMISSIONS))&&(PlatformAccess::administrator($r)||($samePreparer&&Permissions::can($r,'workflow.submit'))),
            'separation_notice'=>$permission&&!$separation?'The preparer or person who uploaded the signed copy cannot perform final verification. A different Finance Head must review it.':null];
    }

    public function index(Request $r)
    {
        $v=$r->validate(['company_id'=>'required|integer','id'=>'nullable|integer|min:1','stage'=>'nullable|string|max:30','page'=>'nullable|integer|min:1']);$company=(int)$v['company_id'];
        Access::company($r,$company,'workflow.view');
        $rows=DB::table('accounting_approvals')->where('company_id',$company)->when($v['id']??null,fn($q,$id)=>$q->where('id',$id))->when($v['stage']??null,fn($q,$s)=>$q->where('stage',$s))->orderByDesc('updated_at')->orderByDesc('id')->paginate(15);
        $rows->setCollection($rows->getCollection()->map(fn($a)=>$this->decorate($r,$a)));
        return ['entries'=>$rows,'stages'=>Flow::STAGES,'enabled'=>Flow::enabled($company)];
    }

    public function document(Request $r)
    {
        $v=$r->validate(['company_id'=>'required|integer','type'=>'required|in:journal,invoice,payment','id'=>'required|integer']);$company=(int)$v['company_id'];
        Access::company($r,$company,'workflow.view');$document=Flow::document($company,$v['type'],(int)$v['id']);
        $approval=Flow::approval($company,$v['type'],(int)$v['id']);
        return ['print_url'=>$v['type']==='journal'&&Permissions::can($r,$document['permission'].'.print')?'/accounting/'.(isset(\App\Modules\Accounting\Services\VoucherTypes::TYPES[$document['row']->kind])?'vouchers':'journals').'/'.$v['id'].'/print?company_id='.$company.'&version='.$document['row']->version:null,'workflow_mode'=>$v['type']==='journal'?'paper':'online','enabled'=>Flow::enabled($company,$v['type'],(int)$v['id']),'approval'=>$approval?$this->decorate($r,$approval):null,
            'can_submit'=>Flow::enabled($company,$v['type'],(int)$v['id'])&&$document['row']->status==='draft'&&(!$approval||in_array($approval->stage,['returned','withdrawn']))&&Permissions::can($r,'workflow.submit')&&Permissions::can($r,$document['permission'].'.view')];
    }

    public function submit(Request $r)
    {
        $v=$r->validate(['company_id'=>'required|integer','type'=>'required|in:journal,invoice,payment','id'=>'required|integer','version'=>'required|integer|min:1','note'=>'required|string|min:5|max:2000']);$company=(int)$v['company_id'];
        Access::company($r,$company,'workflow.submit');abort_if(mb_strlen(trim($v['note']))<5,422,'Enter an explanatory note.');
        DB::transaction(function()use($r,$v,$company){Ledger::lock($company);Flow::submit($r,$company,$v['type'],(int)$v['id'],(int)$v['version'],trim($v['note']));});
        return ['message'=>$v['type']==='journal'?'Non-posted voucher prepared for CEO paper approval. Print it and attach the CEO-signed copy when it returns.':'Submitted to Finance review. No ledger entry has been posted.'];
    }

    public function action(Request $r,int $id)
    {
        $v=$r->validate(['company_id'=>'required|integer','version'=>'required|integer|min:1','action'=>'required|in:approve,post,return,withdraw','verified_signed_copy'=>'sometimes|accepted','note'=>'required|string|min:5|max:2000']);$company=(int)$v['company_id'];
        Access::company($r,$company,'workflow.view');abort_if(mb_strlen(trim($v['note']))<5,422,'Enter an explanatory note.');
        DB::transaction(function()use($r,$v,$company,$id){
            Ledger::lock($company);$a=DB::table('accounting_approvals')->where('company_id',$company)->where('id',$id)->first();abort_unless($a,404);
            abort_unless($a->version===(int)$v['version'],409,'This approval changed. Refresh before acting.');
            abort_unless(isset(Flow::PERMISSIONS[$a->stage]),409,'This approval is no longer awaiting a decision.');
            $document=Flow::document($company,$a->document_type,$a->document_id);$row=$document['row'];
            abort_unless($row->status==='draft'&&$row->version===$a->document_version,409,'The document changed. Return it for preparation and submit the latest draft.');
            $next='';$values=[];
            if($v['action']==='withdraw'){
                abort_unless(PlatformAccess::administrator($r)||($r->user()?->id===$a->prepared_by&&Permissions::can($r,'workflow.submit')),403);
                $next='withdrawn';
            }else{
                Permissions::require($r,Flow::PERMISSIONS[$a->stage]);
                if($v['action']==='return')$next='returned';
                else{
                    $allowed=$this->decorate($r,$a);abort_unless($allowed['can_approve'],403,'The preparer and CEO cannot perform incompatible approval stages. Use a different authorised reviewer.');
                    if($a->stage==='manager_review'){
                        abort_unless($v['action']==='post',422,'Use final posting for the Finance Manager stage.');
                        Permissions::require($r,$document['permission'].'.post');
                        if($a->workflow_mode==='paper'){
                            abort_unless($r->boolean('verified_signed_copy'),422,'Confirm that you checked the CEO stamp/signature, voucher details and amounts against the attached copy.');
                            $copy=Flow::signedCopy($a);abort_unless($copy,422,'A current signed copy is required.');
                            $values+=['verified_copy_id'=>$copy->id,'verified_by'=>$r->user()?->id,'verified_at'=>now()];
                        }
                        $input=['company_id'=>$company,'version'=>$row->version];
                        if($a->document_type==='payment')$input=json_decode($row->payload,true);
                        $post=Request::create('/accounting/approval-post','POST',$input);$post->setLaravelSession($r->session());$post->setUserResolver(fn()=>$r->user());
                        $post->attributes->set('accounting_approval',[$a->id,$a->document_type,$a->document_id,$row->version]);
                        if($a->document_type==='journal')app(JournalController::class)->post($post,$row->id);
                        elseif($a->document_type==='invoice')app(TradeController::class)->postInvoice($post,$row->id);
                        else{Flow::requirePosting($post,$company,'payment',$row->id,$row->version);app(TradeController::class)->payment($post,$row->invoice_id);DB::table('accounting_payment_requests')->where('id',$row->id)->update(['status'=>'posted','version'=>$row->version+1,'updated_at'=>now()]);}
                        $next='posted';
                    }else{
                        abort_unless($v['action']==='approve',422,'This stage requires approval before posting.');
                        $next=['finance_review'=>'ceo_review','ceo_review'=>'finance_verify','finance_verify'=>'manager_review'][$a->stage];
                        if($a->stage==='ceo_review')$values['ceo_by']=$r->user()?->id;
                    }
                }
            }
            if($a->document_type==='payment'&&in_array($next,['returned','withdrawn']))DB::table('accounting_payment_requests')->where('id',$row->id)->update(['status'=>'cancelled','version'=>$row->version+1,'updated_at'=>now()]);
            DB::table('accounting_approvals')->where('id',$id)->update($values+['stage'=>$next,'version'=>$a->version+1,'updated_at'=>now()]);
            Flow::event($r,$id,$v['action'],$a->stage,$next,trim($v['note']));Access::audit($r,$company,'Approval '.$v['action'],$document['detail']['number'],['from'=>$a->stage,'to'=>$next,'note'=>$v['note']]);
        });
        return ['message'=>$v['action']==='post'?'Approved document posted to the ledger.':'Approval decision saved.'];
    }
}
