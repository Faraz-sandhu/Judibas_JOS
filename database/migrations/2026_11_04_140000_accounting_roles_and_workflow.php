<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_roles', function (Blueprint $t) {
            $t->id(); $t->string('slug', 80)->unique(); $t->string('name', 100); $t->text('description')->nullable();
            $t->json('permissions'); $t->boolean('protected')->default(false); $t->boolean('active')->default(true); $t->unsignedInteger('version')->default(1); $t->timestamps();
        });
        foreach (\App\Modules\Accounting\Services\Permissions::presets() as $slug => [$name,$permissions]) {
            DB::table('accounting_roles')->insert(['slug'=>$slug,'name'=>$name,'permissions'=>json_encode($permissions),'protected'=>true,'active'=>true,'created_at'=>now(),'updated_at'=>now()]);
        }
        Schema::table('accounting_members', fn (Blueprint $t) => $t->foreign('role')->references('slug')->on('accounting_roles')->restrictOnDelete());
        Schema::table('accounting_books', fn (Blueprint $t) => $t->boolean('approval_required')->default(true));
        Schema::table('accounting_invoices', fn (Blueprint $t) => $t->unsignedBigInteger('created_by')->nullable());
        Schema::create('accounting_payment_requests', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('company_id'); $t->unsignedBigInteger('invoice_id'); $t->uuid('request_key'); $t->json('payload');
            $t->unsignedInteger('version')->default(1); $t->string('status', 20)->default('draft'); $t->unsignedBigInteger('created_by')->nullable();
            $t->foreign(['company_id','invoice_id'])->references(['company_id','id'])->on('accounting_invoices')->restrictOnDelete();
            $t->unique(['company_id','request_key']); $t->timestamps();
        });
        Schema::create('accounting_approvals', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained('communication_companies')->restrictOnDelete();
            $t->string('document_type', 30); $t->unsignedBigInteger('document_id'); $t->unsignedInteger('document_version');
            $t->string('stage', 30)->default('finance_review'); $t->unsignedInteger('version')->default(1);
            $t->unsignedBigInteger('prepared_by')->nullable(); $t->unsignedBigInteger('ceo_by')->nullable();
            $t->unique(['company_id','document_type','document_id']); $t->timestamps();
        });
        Schema::create('accounting_approval_events', function (Blueprint $t) {
            $t->id(); $t->foreignId('approval_id')->constrained('accounting_approvals')->restrictOnDelete();
            $t->string('action', 40); $t->string('from_stage', 30)->nullable(); $t->string('to_stage', 30);
            $t->unsignedBigInteger('actor_id')->nullable(); $t->string('actor_name'); $t->text('note'); $t->timestamp('created_at');
        });
    }
    public function down(): void { throw new RuntimeException('Accounting roles and approval history must be preserved.'); }
};
