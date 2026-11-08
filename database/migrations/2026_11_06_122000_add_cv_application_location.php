<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\{Blueprint};
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {Schema::table('cv_bank_applications',fn(Blueprint $t)=>$t->string('preferred_location',160)->nullable());}
 public function down():void {Schema::table('cv_bank_applications',fn(Blueprint $t)=>$t->dropColumn('preferred_location'));}
};
