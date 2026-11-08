<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
 public function up():void {if(DB::getDriverName()==='pgsql')DB::statement("SELECT setval(pg_get_serial_sequence('cv_bank_settings','id'), GREATEST(COALESCE((SELECT MAX(id) FROM cv_bank_settings),1),1),true)");}
 public function down():void {}
};
