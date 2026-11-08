<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema,DB};
return new class extends Migration {
 public function up():void {
  $company=DB::table('communication_companies')->whereRaw('LOWER(name) = ?', ['judibas'])->value('id');
  if(!$company)$company=DB::table('communication_companies')->insertGetId(['name'=>'Judibas','created_at'=>now(),'updated_at'=>now()]);
  Schema::create('cv_bank_company_pages',function(Blueprint $t){$t->id();$t->foreignId('company_id')->unique()->constrained('communication_companies')->restrictOnDelete();$t->string('slug',100)->unique();$t->boolean('published')->default(true);$t->timestamps();});
  DB::table('cv_bank_company_pages')->insert(['company_id'=>$company,'slug'=>'judibas','published'=>true,'created_at'=>now(),'updated_at'=>now()]);
  foreach(['cv_bank_departments','cv_bank_jobs','cv_bank_applications','cv_bank_settings'] as $table){
   Schema::table($table,function(Blueprint $t)use($company){$t->foreignId('company_id')->default($company)->constrained('communication_companies')->restrictOnDelete();});
  }
  if(!DB::table('cv_bank_settings')->where('id',1)->exists())DB::table('cv_bank_settings')->insert(['id'=>1,'company_id'=>$company,'theme'=>'studio','headline'=>'Good people. Meaningful work.','intro'=>'Explore our open roles and find your next chapter.','created_at'=>now(),'updated_at'=>now()]);
  Schema::table('cv_bank_departments',function(Blueprint $t){$t->dropUnique(['name']);$t->unique(['company_id','name']);$t->unique(['company_id','id']);});
  Schema::table('cv_bank_settings',fn(Blueprint $t)=>$t->unique('company_id'));
  Schema::table('cv_bank_career_editors',function(Blueprint $t)use($company){$t->dropPrimary();$t->foreignId('company_id')->default($company)->constrained('communication_companies')->restrictOnDelete();$t->primary(['company_id','user_id']);});
  foreach(['cv_bank_jobs','cv_bank_applications'] as $table){
   Schema::table($table,fn(Blueprint $t)=>$t->unsignedBigInteger('department_id')->nullable());
   foreach(DB::table($table)->get(['id','department']) as $row){
    $department=DB::table('cv_bank_departments')->where('company_id',$company)->where('name',$row->department)->value('id');
    if(!$department)$department=DB::table('cv_bank_departments')->insertGetId(['company_id'=>$company,'name'=>$row->department,'active'=>false,'created_at'=>now(),'updated_at'=>now()]);
    DB::table($table)->where('id',$row->id)->update(['department_id'=>$department]);
   }
   Schema::table($table,function(Blueprint $t){$t->unsignedBigInteger('department_id')->nullable(false)->change();$t->foreign(['company_id','department_id'])->references(['company_id','id'])->on('cv_bank_departments')->restrictOnDelete();});
  }
  Schema::table('cv_bank_jobs',function(Blueprint $t){$t->unique(['company_id','id']);$t->json('locations')->nullable();$t->string('employment_type',30)->default('full_time');$t->string('work_mode',20)->default('onsite');$t->string('experience',160)->nullable();$t->text('responsibilities')->nullable();$t->text('requirements')->nullable();$t->date('closing_date')->nullable();$t->index(['company_id','active']);});
  Schema::table('cv_bank_applications',function(Blueprint $t){$t->foreign(['company_id','job_id'])->references(['company_id','id'])->on('cv_bank_jobs')->restrictOnDelete();$t->index(['company_id','status']);});
 }
 public function down():void {
  foreach(['cv_bank_departments','cv_bank_jobs','cv_bank_applications','cv_bank_settings','cv_bank_career_editors','cv_bank_company_pages'] as $table)if(DB::table($table)->distinct()->count('company_id')>1)throw new \RuntimeException('Cannot collapse multi-company CV Bank data. Export and consolidate it before rolling back.');
  Schema::table('cv_bank_applications',function(Blueprint $t){$t->dropForeign(['company_id','job_id']);$t->dropIndex(['company_id','status']);});
  Schema::table('cv_bank_jobs',function(Blueprint $t){$t->dropIndex(['company_id','active']);$t->dropUnique(['company_id','id']);$t->dropColumn(['locations','employment_type','work_mode','experience','responsibilities','requirements','closing_date']);});
  foreach(['cv_bank_jobs','cv_bank_applications'] as $table)Schema::table($table,function(Blueprint $t){$t->dropForeign(['company_id','department_id']);$t->dropColumn('department_id');});
  // Company-specific department names cannot safely be collapsed back into one global unique list.
  Schema::table('cv_bank_departments',function(Blueprint $t){$t->dropUnique(['company_id','name']);$t->dropUnique(['company_id','id']);$t->unique('name');});
  Schema::table('cv_bank_settings',fn(Blueprint $t)=>$t->dropUnique(['company_id']));
  Schema::table('cv_bank_career_editors',function(Blueprint $t){$t->dropPrimary();$t->dropForeign(['company_id']);$t->dropColumn('company_id');});
  Schema::table('cv_bank_career_editors',fn(Blueprint $t)=>$t->primary('user_id'));
  foreach(['cv_bank_departments','cv_bank_jobs','cv_bank_applications','cv_bank_settings'] as $table)Schema::table($table,function(Blueprint $t){$t->dropForeign(['company_id']);$t->dropColumn('company_id');});
  Schema::dropIfExists('cv_bank_company_pages');
 }
};
