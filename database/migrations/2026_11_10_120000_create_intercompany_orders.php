<?php

use App\Modules\Accounting\Services\Permissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_company_parties', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained('communication_companies')->restrictOnDelete();
            $t->foreignId('counterparty_company_id')->constrained('communication_companies')->restrictOnDelete();
            $t->string('kind', 20);
            $t->foreignId('party_id')->constrained('accounting_parties')->restrictOnDelete();
            $t->unique(['company_id', 'counterparty_company_id', 'kind'], 'accounting_company_party_unique');
        });
        Schema::create('accounting_intercompany_orders', function (Blueprint $t) {
            $t->id();
            $t->uuid('request_key')->unique();
            $t->foreignId('seller_company_id')->constrained('communication_companies')->restrictOnDelete();
            $t->foreignId('buyer_company_id')->constrained('communication_companies')->restrictOnDelete();
            $t->foreignId('customer_id')->constrained('accounting_parties')->restrictOnDelete();
            $t->foreignId('supplier_id')->constrained('accounting_parties')->restrictOnDelete();
            $t->date('order_date');
            $t->date('due_date');
            $t->string('currency', 3);
            $t->string('status', 20)->default('draft');
            $t->unsignedInteger('version')->default(1);
            $t->json('lines');
            $t->bigInteger('quoted_minor');
            $t->bigInteger('total_minor');
            $t->text('terms')->nullable();
            $t->char('payload_hash', 64);
            $t->unsignedBigInteger('created_by')->nullable();
            $t->foreignId('sales_invoice_id')->nullable()->unique()->constrained('accounting_invoices')->restrictOnDelete();
            $t->foreignId('purchase_invoice_id')->nullable()->unique()->constrained('accounting_invoices')->restrictOnDelete();
            $t->timestamps();
            $t->index(['seller_company_id', 'order_date']);
            $t->index(['buyer_company_id', 'order_date']);
        });
        foreach (Permissions::presets() as $slug => [$name,$keys]) {
            DB::table('accounting_roles')->where('slug', $slug)->where('protected', true)->update(['permissions' => json_encode($keys), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_intercompany_orders');
        Schema::dropIfExists('accounting_company_parties');
    }
};
