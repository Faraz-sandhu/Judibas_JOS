<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
 public function up():void { if(DB::getDriverName()==='pgsql'){DB::statement('ALTER TABLE accounting_journals DROP CONSTRAINT IF EXISTS accounting_journal_kind');DB::statement("ALTER TABLE accounting_journals ADD CONSTRAINT accounting_journal_kind CHECK (kind IN ('general','opening','reversal','cash_receipt','cash_payment','bank_receipt','bank_payment','expense','income','contra'))");} }
 public function down():void { /* Preserve existing contra financial documents. */ }
};
