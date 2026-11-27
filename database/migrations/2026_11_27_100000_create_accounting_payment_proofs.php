<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void{Schema::create('accounting_payment_proofs',function(Blueprint $t){$t->id();$t->foreignId('company_id')->constrained('communication_companies');$t->foreignId('journal_id')->constrained('accounting_journals');$t->unsignedInteger('document_version');$t->string('document_hash',64);$t->string('path');$t->string('original_name',240);$t->string('mime',100);$t->unsignedBigInteger('size');$t->string('sha256',64);$t->unsignedBigInteger('uploaded_by')->nullable();$t->string('uploaded_name');$t->timestamp('removed_at')->nullable();$t->timestamps();$t->index(['company_id','journal_id','document_hash']);});}
 public function down():void{Schema::dropIfExists('accounting_payment_proofs');}
};
