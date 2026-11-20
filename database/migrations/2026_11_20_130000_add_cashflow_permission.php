<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('accounting_roles')->where('protected', true)->get() as $role) {
            $keys = json_decode($role->permissions, true) ?? [];
            if (in_array('reports.ledger', $keys) && ! in_array('reports.cashflow', $keys)) {
                $keys[] = 'reports.cashflow';
                DB::table('accounting_roles')->where('id', $role->id)->update(['permissions' => json_encode($keys), 'version' => $role->version + 1, 'updated_at' => now()]);
            }
        }
    }

    public function down(): void {}
};
