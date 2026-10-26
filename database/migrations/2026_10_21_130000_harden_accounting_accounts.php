<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounting_accounts', function (Blueprint $t) {
            $t->unsignedInteger('version')->default(1);
            $t->unique(['company_id', 'id'], 'accounting_company_account_unique');
            $t->foreign(['company_id', 'parent_id'], 'accounting_company_parent_foreign')->references(['company_id', 'id'])->on('accounting_accounts')->restrictOnDelete();
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE accounting_accounts ADD CONSTRAINT accounting_root_type_check CHECK (root_type IN ('Asset','Liability','Equity','Income','Expense'))");
            DB::statement("ALTER TABLE accounting_accounts ADD CONSTRAINT accounting_code_check CHECK (code = upper(code) AND code ~ '^[A-Z0-9][A-Z0-9.\\-]*$')");
            DB::statement("ALTER TABLE accounting_accounts ADD CONSTRAINT accounting_structure_check CHECK ((is_system AND is_group AND parent_id IS NULL AND account_type = 'General') OR (NOT is_system AND parent_id IS NOT NULL))");
            DB::statement("ALTER TABLE accounting_accounts ADD CONSTRAINT accounting_group_type_check CHECK (NOT is_group OR account_type = 'General')");
            DB::statement('ALTER TABLE accounting_accounts ADD CONSTRAINT accounting_self_parent_check CHECK (parent_id IS NULL OR parent_id <> id)');
            DB::statement('CREATE UNIQUE INDEX accounting_one_root_per_type ON accounting_accounts (company_id, root_type) WHERE is_system');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS accounting_one_root_per_type');
            foreach (['accounting_root_type_check', 'accounting_code_check', 'accounting_structure_check', 'accounting_group_type_check', 'accounting_self_parent_check'] as $constraint) {
                DB::statement('ALTER TABLE accounting_accounts DROP CONSTRAINT IF EXISTS '.$constraint);
            }
        }
        Schema::table('accounting_accounts', function (Blueprint $t) {
            $t->dropForeign('accounting_company_parent_foreign');
            $t->dropUnique('accounting_company_account_unique');
            $t->dropColumn('version');
        });
    }
};
