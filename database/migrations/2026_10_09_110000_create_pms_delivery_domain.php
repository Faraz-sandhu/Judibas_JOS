<?php
use Illuminate\Database\Migrations\Migration;
return new class extends Migration {
 public function up():void {foreach(glob(app_path('Modules/Pms/Database/LegacyMigrations/*.php')) as $file){(require $file)->up();}}
 public function down():void {foreach(array_reverse(glob(app_path('Modules/Pms/Database/LegacyMigrations/*.php'))) as $file){(require $file)->down();}}
};
