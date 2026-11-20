<?php
namespace App\Modules\Accounting\Http\Controllers;

use App\Modules\Accounting\Services\{AccountingAccess as Access,ApprovalFlow as Flow,Ledger};
use App\Services\PlatformAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Storage};

class SignedCopyController
{
    public function store(Request $r,int $id)
    {
        $v=$r->validate(['company_id'=>'required|integer','version'=>'required|integer|min:1','document_id'=>'required_if:initial,1|nullable|integer','initial'=>'sometimes|boolean','note'=>'required|string|min:5|max:2000','signed_copy'=>'required|file|max:10240|mimes:jpg,jpeg,png,webp,pdf']);
        $company=(int)$v['company_id'];Access::company($r,$company,'workflow.upload_signed');
        abort_if(mb_strlen(trim($v['note']))<5,422,'Enter a note about the signed copy.');
        $file=$r->file('signed_copy');$path=null;
        try{
            DB::transaction(function()use($r,$v,$company,$id,$file,&$path){
                Ledger::lock($company);
                $initial=$id===0;
                if($initial){
                    abort_unless($r->boolean('initial')&&!empty($v['document_id']),422,'Select the saved voucher.');
                    $id=Flow::submit($r,$company,'journal',(int)$v['document_id'],(int)$v['version'],'Prepared for CEO paper approval.');
                }
                $a=DB::table('accounting_approvals')->where('company_id',$company)->where('id',$id)->first();abort_unless($a,404);
                abort_unless($a->workflow_mode==='paper'&&$a->document_type==='journal'&&in_array($a->stage,['paper_ceo','manager_review'],true),409,'This voucher is not awaiting a CEO-signed copy.');
                abort_unless($initial||$a->version===(int)$v['version'],409,'The approval changed. Refresh before uploading.');
                $document=Flow::document($company,'journal',$a->document_id);$row=$document['row'];
                abort_unless($row->status==='draft'&&$row->version===$a->document_version,409,'The voucher changed. Submit the latest saved version.');
                Ledger::checkDate(Ledger::book($company),$row->posting_date);
                $mime=$file->getMimeType();abort_unless(in_array($mime,['image/jpeg','image/png','image/webp','application/pdf'],true),422,'Upload a JPG, PNG, WebP image or PDF.');
                $path=$file->store('accounting-signed-copies/'.$company,'local');abort_unless($path,500,'Unable to save the signed copy.');
                $name=mb_substr(str_replace(["\r","\n",'\\','/'],'_',basename($file->getClientOriginalName())),0,240);
                $copy=DB::table('accounting_signed_copies')->insertGetId(['company_id'=>$company,'approval_id'=>$id,'submission_version'=>$a->submission_version,'document_version'=>$row->version,'path'=>$path,'original_name'=>$name,'mime'=>$mime,'size'=>$file->getSize(),'sha256'=>hash_file('sha256',$file->getRealPath()),'uploaded_by'=>$r->user()?->id,'uploaded_name'=>PlatformAccess::super($r)?'Super Admin':$r->user()->name,'created_at'=>now()]);
                DB::table('accounting_approvals')->where('id',$id)->update(['stage'=>'manager_review','version'=>$a->version+1,'updated_at'=>now()]);
                Flow::event($r,$id,$a->stage==='manager_review'?'signed_copy_replaced':'signed_copy_submitted',$a->stage,'manager_review',trim($v['note']));
                Access::audit($r,$company,'CEO-signed voucher copy attached',$document['detail']['number'],['copy_id'=>$copy,'document_version'=>$row->version,'submission_version'=>$a->submission_version]);
            });
        }catch(\Throwable $e){if($path)Storage::disk('local')->delete($path);throw $e;}
        return ['message'=>'Signed copy attached. Non-posted voucher sent to Finance Head for verification.'];
    }

    public function show(Request $r,int $id)
    {
        $company=(int)$r->validate(['company_id'=>'required|integer'])['company_id'];Access::company($r,$company,'workflow.view');
        $copy=DB::table('accounting_signed_copies')->where('company_id',$company)->where('id',$id)->first();abort_unless($copy,404);
        $approval=DB::table('accounting_approvals')->find($copy->approval_id);
        if (\App\Modules\Accounting\Services\ImprestFunds::restricted($r)) {
            abort_unless($approval&&$approval->document_type==='journal',403);
            \App\Modules\Accounting\Services\ImprestFunds::authorizeJournal($r,$company,$approval->document_id);
        }
        abort_unless(Storage::disk('local')->exists($copy->path),404,'This signed copy is unavailable. Upload a new copy after returning the voucher.');
        Access::audit($r,$company,'Signed voucher copy viewed','Signed copy #'.$id,['approval_id'=>$copy->approval_id]);
        $headers=['Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff','Content-Security-Policy'=>"frame-ancestors 'self'"];
        if($r->boolean('download'))return Storage::disk('local')->download($copy->path,$copy->original_name,$headers);
        return response()->file(Storage::disk('local')->path($copy->path),$headers+['Content-Type'=>$copy->mime]);
    }
}
