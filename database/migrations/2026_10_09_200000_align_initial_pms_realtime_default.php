<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
 public function up():void {if(config('broadcasting.default')==='reverb')DB::table('pms_realtime_settings')->where('enabled',false)->whereNull('app_id')->whereNull('app_key')->whereNull('app_secret')->whereColumn('created_at','updated_at')->update(['enabled'=>true,'updated_at'=>now()]);}
 public function down():void { /* Data correction only: preserve any administrator settings changed after migration. */ }
};
