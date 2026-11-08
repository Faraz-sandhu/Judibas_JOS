<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use App\Modules\Accounting\Services\Permissions;

return new class extends Migration {
    public function up(): void
    {
        if(DB::getDriverName()==='pgsql'){
            DB::statement('ALTER TABLE accounting_journals DROP CONSTRAINT accounting_journal_kind');
            DB::statement("ALTER TABLE accounting_journals ADD CONSTRAINT accounting_journal_kind CHECK (kind IN ('general','opening','reversal','cash_receipt','cash_payment','bank_receipt','bank_payment','expense','income'))");
        }
        foreach(Permissions::presets() as $slug=>[$name,$permissions]){
            DB::table('accounting_roles')->where('slug',$slug)->where('protected',true)->update(['permissions'=>json_encode($permissions),'updated_at'=>now()]);
        }
    }
    public function down(): void {throw new RuntimeException('Voucher records and their access history must be preserved.');}
};
