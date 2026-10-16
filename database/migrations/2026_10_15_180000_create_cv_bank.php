<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void { Schema::create('cv_bank_jobs',function(Blueprint $t){$t->id();$t->string('title');$t->string('department');$t->text('description')->nullable();$t->boolean('active')->default(true);$t->timestamps();}); Schema::create('cv_bank_applications',function(Blueprint $t){$t->id();$t->foreignId('job_id')->nullable()->constrained('cv_bank_jobs')->nullOnDelete();$t->string('department');$t->string('name');$t->string('email');$t->string('phone',50)->nullable();$t->text('cover_letter')->nullable();$t->string('cv_path');$t->string('status')->default('new');$t->text('notes')->nullable();$t->timestamps();$t->index(['department','status']);}); }
 public function down():void {Schema::dropIfExists('cv_bank_applications');Schema::dropIfExists('cv_bank_jobs');}
};
