<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained('communication_companies');
            $t->string('code', 50);
            $t->string('name', 150);
            $t->string('kind', 10)->default('service');
            $t->string('unit', 30)->default('Service');
            $t->text('description')->nullable();
            $t->bigInteger('sale_rate_minor')->default(0);
            $t->bigInteger('purchase_rate_minor')->default(0);
            $t->boolean('sellable')->default(true);
            $t->boolean('purchasable')->default(true);
            $t->foreignId('income_account_id')->nullable()->constrained('accounting_accounts');
            $t->foreignId('expense_account_id')->nullable()->constrained('accounting_accounts');
            $t->boolean('active')->default(true);
            $t->unsignedInteger('version')->default(1);
            $t->timestamps();
            $t->unique(['company_id', 'code']);
        });
        foreach (DB::table('accounting_roles')->where('protected', true)->get() as $role) {
            $keys = json_decode($role->permissions, true) ?? [];
            $extra = [];
            if (in_array('sales.view', $keys) || in_array('purchases.view', $keys) || in_array('sales_orders.view', $keys) || in_array('purchase_orders.view', $keys)) {
                $extra[] = 'items.view';
            }if (in_array('sales.create', $keys) || in_array('purchases.create', $keys)) {
                $extra = array_merge($extra, ['items.create', 'items.update']);
            }DB::table('accounting_roles')->where('id', $role->id)->update(['permissions' => json_encode(array_values(array_unique(array_merge($keys, $extra)))), 'version' => $role->version + 1, 'updated_at' => now()]);
        }
    }

    public function down(): void
    { /* Retain item catalogue and historical references. */
    }
};
