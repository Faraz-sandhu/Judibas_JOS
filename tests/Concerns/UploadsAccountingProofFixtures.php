<?php
namespace Tests\Concerns;
use App\Modules\Accounting\Services\PaymentProofs;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB,Storage};
/** Existing posting fixtures provide a genuine proof. Dedicated proof-policy tests do not use this trait. */
trait UploadsAccountingProofFixtures {
 public function postJson($uri,array $data=[],array $headers=[],$options=0){
  $id=null;$company=(int)($data['company_id']??0);
  if(preg_match('~^/accounting/api/(?:journals|imprest)/(\d+)/post$~',$uri,$match))$id=(int)$match[1];
  elseif(preg_match('~^/accounting/api/approvals/(\d+)/actions$~',$uri,$match)&&in_array($data['action']??'', ['approve','post'],true)){
   $a=DB::table('accounting_approvals')->where('id',(int)$match[1])->where('company_id',$company)->first();
   if($a&&$a->document_type==='journal'&&in_array($a->stage,['officer_confirm','manager_review'],true))$id=$a->document_id;
  }
  if($id){$j=DB::table('accounting_journals')->where('company_id',$company)->where('id',$id)->first();
   if($j&&$j->status==='draft'&&PaymentProofs::required($j)&&!PaymentProofs::current($j)){
    $response=$this->post('/accounting/api/journals/'.$id.'/payment-proofs',['company_id'=>$company,'version'=>$j->version,'payment_proof'=>UploadedFile::fake()->create('fixture-payment-proof.pdf',10,'application/pdf')],['Accept'=>'application/json']);
    if($response->isSuccessful()){$path=DB::table('accounting_payment_proofs')->where('company_id',$company)->where('journal_id',$id)->orderByDesc('id')->value('path');$this->beforeApplicationDestroyed(fn()=>Storage::disk('local')->delete($path));}
   }
  }
  return parent::postJson($uri,$data,$headers,$options);
 }
}
