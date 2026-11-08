<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};
use App\Modules\Accounting\Services\Permissions;

return new class extends Migration {
    public function up():void
    {
        Schema::create('accounting_activity_notifications',function(Blueprint $t){
            $t->id();$t->foreignId('company_id')->constrained('communication_companies')->restrictOnDelete();
            $t->foreignId('audit_id')->unique()->constrained('accounting_audits')->restrictOnDelete();
            $t->string('actor_key',80);$t->string('actor_name');$t->string('title');$t->string('subject');$t->string('section',40);$t->timestamp('created_at');
            $t->index(['company_id','id']);
        });
        Schema::create('accounting_notification_reads',function(Blueprint $t){
            $t->foreignId('notification_id')->constrained('accounting_activity_notifications')->restrictOnDelete();
            $t->string('viewer_key',80);$t->timestamp('read_at');$t->primary(['notification_id','viewer_key']);
        });
        foreach(Permissions::presets() as $slug=>[$name,$permissions])DB::table('accounting_roles')->where('slug',$slug)->where('protected',true)->update(['permissions'=>json_encode($permissions),'updated_at'=>now()]);
    }
    public function down():void {throw new RuntimeException('Accounting activity notification history must be retained.');}
};
