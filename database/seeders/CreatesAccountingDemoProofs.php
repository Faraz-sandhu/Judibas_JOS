<?php
namespace Database\Seeders;
use Illuminate\Http\{Request,UploadedFile};
trait CreatesAccountingDemoProofs {
 protected function attachDemoPaymentProof(Request $request,array $journal):void{
  abort_unless(app()->environment(['local','testing']),403,'Fictional payment evidence is local/testing only.');
  $row=\Illuminate\Support\Facades\DB::table('accounting_journals')->find($journal['id']);if(!\App\Modules\Accounting\Services\PaymentProofs::required($row))return;
  $path=tempnam(sys_get_temp_dir(),'judibas-demo-proof-');
  try{$pdf=new \Dompdf\Dompdf;$pdf->loadHtml('<h1>TEST ONLY - FICTIONAL PAYMENT PROOF</h1><p>Voucher '.htmlspecialchars($journal['number'],ENT_QUOTES,'UTF-8').'</p><p>Sample evidence for local testing. No real payment or bank transaction is represented.</p>');$pdf->render();file_put_contents($path,$pdf->output());
   $request->merge(['company_id'=>$row->company_id,'version'=>$row->version]);$request->files->set('payment_proof',new UploadedFile($path,'TEST-ONLY-PAYMENT-PROOF.pdf','application/pdf',UPLOAD_ERR_OK,true));app(\App\Modules\Accounting\Http\Controllers\PaymentProofController::class)->store($request,$row->id);
  }finally{if(is_file($path))unlink($path);}
 }
}
