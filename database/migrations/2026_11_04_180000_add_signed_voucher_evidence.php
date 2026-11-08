<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};
use App\Modules\Accounting\Services\Permissions;

return new class extends Migration {
    public function up():void
    {
        Schema::table('accounting_approvals',function(Blueprint $t){$t->string('workflow_mode',20)->default('online');$t->unsignedInteger('submission_version')->default(1);});
        Schema::create('accounting_signed_copies',function(Blueprint $t){
            $t->id();$t->foreignId('company_id')->constrained('communication_companies')->restrictOnDelete();
            $t->foreignId('approval_id')->constrained('accounting_approvals')->restrictOnDelete();
            $t->unsignedInteger('submission_version');$t->unsignedInteger('document_version');$t->string('path');$t->string('original_name');$t->string('mime',100);$t->unsignedBigInteger('size');$t->string('sha256',64);
            $t->unsignedBigInteger('uploaded_by')->nullable();$t->string('uploaded_name');$t->timestamp('created_at');
            $t->index(['company_id','approval_id','document_version']);
        });
        Schema::table('accounting_approvals',function(Blueprint $t){$t->foreignId('verified_copy_id')->nullable()->constrained('accounting_signed_copies')->restrictOnDelete();$t->unsignedBigInteger('verified_by')->nullable();$t->timestamp('verified_at')->nullable();});
        foreach(Permissions::presets() as $slug=>[$name,$permissions])DB::table('accounting_roles')->where('slug',$slug)->where('protected',true)->update(['permissions'=>json_encode($permissions),'updated_at'=>now()]);
        // Existing posted history is retained; pending vouchers now require a signed copy.
        foreach(DB::table('accounting_approvals')->where('document_type','journal')->whereIn('stage',['finance_review','ceo_review','finance_verify','manager_review'])->get() as $a){
            DB::table('accounting_approvals')->where('id',$a->id)->update(['workflow_mode'=>'paper','stage'=>'paper_ceo','version'=>$a->version+1,'updated_at'=>now()]);
            DB::table('accounting_approval_events')->insert(['approval_id'=>$a->id,'action'=>'paper_workflow_enabled','from_stage'=>$a->stage,'to_stage'=>'paper_ceo','actor_name'=>'System','note'=>'Voucher approval now requires the CEO-stamped signed copy before Finance Head verification and posting.','created_at'=>now()]);
        }
    }
    public function down():void {throw new RuntimeException('Signed voucher evidence and approval history must be retained.');}
};
