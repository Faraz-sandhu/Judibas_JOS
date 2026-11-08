<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $role = DB::table('accounting_roles')->where('slug', 'viewer')->where('protected', true)->first();
        if ($role) {
            $permissions = array_values(array_unique(array_merge(json_decode($role->permissions, true), ['sales.print', 'purchases.print', 'journals.print', 'cash_receipts.print', 'cash_payments.print'])));
            DB::table('accounting_roles')->where('id', $role->id)->update(['permissions' => json_encode($permissions), 'updated_at' => now()]);
        }
    }
    public function down(): void {}
};
