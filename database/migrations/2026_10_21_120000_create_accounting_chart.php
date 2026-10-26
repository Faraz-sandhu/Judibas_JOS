<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('product_settings')->where('slug', 'accounts')->where('name', 'Accounts')->update(['name' => 'Accounting']);
        Schema::create('accounting_members', function (Blueprint $t) {
            $t->foreignId('user_id')->primary()->constrained('users')->cascadeOnDelete();
            $t->string('role')->default('viewer');
            $t->timestamps();
        });
        Schema::create('accounting_accounts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained('communication_companies')->restrictOnDelete();
            $t->foreignId('parent_id')->nullable()->constrained('accounting_accounts')->restrictOnDelete();
            $t->string('code', 20);
            $t->string('name', 150);
            $t->string('root_type', 20);
            $t->string('account_type', 30)->default('General');
            $t->boolean('is_group')->default(false);
            $t->boolean('is_system')->default(false);
            $t->boolean('active')->default(true);
            $t->text('description')->nullable();
            $t->timestamps();
            $t->unique(['company_id', 'code']);
            $t->index(['company_id', 'parent_id']);
        });
        Schema::create('accounting_audits', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained('communication_companies')->restrictOnDelete();
            $t->unsignedBigInteger('actor_id')->nullable();
            $t->string('actor_name');
            $t->string('action');
            $t->string('subject');
            $t->json('details')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_audits');
        Schema::dropIfExists('accounting_accounts');
        Schema::dropIfExists('accounting_members');
    }
};
