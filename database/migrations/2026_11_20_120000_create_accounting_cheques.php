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
        Schema::create('accounting_cheques', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained('communication_companies');
            $t->uuid('request_key')->unique();
            $t->uuid('payment_key')->unique();
            $t->foreignId('invoice_id')->constrained('accounting_invoices');
            $t->foreignId('party_id')->constrained('accounting_parties');
            $t->foreignId('bank_account_id')->constrained('accounting_accounts');
            $t->string('direction');
            $t->string('cheque_number', 100);
            $t->string('drawee_bank', 150);
            $t->date('cheque_date');
            $t->date('recorded_date');
            $t->bigInteger('amount_minor');
            $t->string('currency', 3);
            $t->string('status')->default('pending');
            $t->unsignedInteger('version')->default(1);
            $t->date('cleared_date')->nullable();
            $t->text('notes')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users');
            $t->timestamps();
            $t->unique(['company_id', 'direction', 'drawee_bank', 'cheque_number'], 'accounting_cheque_identity');
        });
        Schema::create('accounting_cheque_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('cheque_id')->constrained('accounting_cheques');
            $t->string('action');
            $t->date('event_date');
            $t->text('note')->nullable();
            $t->foreignId('actor_id')->nullable()->constrained('users');
            $t->timestamp('created_at');
        });
        foreach (DB::table('accounting_roles')->where('protected', true)->get() as $role) {
            $presets = Permissions::presets();
            $extra = array_filter($presets[$role->slug][1] ?? [], fn ($p) => str_starts_with($p, 'cheques.'));
            $keys = json_decode($role->permissions, true) ?? [];
            DB::table('accounting_roles')->where('id', $role->id)->update(['permissions' => json_encode(array_values(array_unique(array_merge($keys, $extra))))]);
        }
    }

    public function down(): void
    { /* Retain cheque and clearance history. */
    }
};
