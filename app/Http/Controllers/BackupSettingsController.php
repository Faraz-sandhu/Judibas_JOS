<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
class BackupSettingsController extends Controller { private function admin(Request $r):void{abort_unless($r->session()->get('judibas_admin'),403);}
 public function show(Request $r){$this->admin($r);$s=DB::table('platform_backup_settings')->first();return response()->json($s?:['enabled'=>false,'frequency'=>'every_2_days','time'=>'02:00','timezone'=>'Asia/Karachi','recipients'=>'','retention'=>14]);}
 public function save(Request $r){$this->admin($r);$v=$r->validate(['enabled'=>'required|boolean','frequency'=>'required|in:daily,every_2_days,weekly,every_15_days,monthly','time'=>'required|date_format:H:i','timezone'=>'required|string|max:64','recipients'=>'required|string|max:2000','retention'=>'required|integer|min:1|max:365']);DB::table('platform_backup_settings')->updateOrInsert(['id'=>1],$v+['updated_at'=>now(),'created_at'=>now()]);return response()->json(['message'=>'Backup settings saved.']);}
 public function run(Request $r){$this->admin($r);Artisan::queue('judibas:backup --force');return response()->json(['message'=>'Backup started. You will receive an email when it completes.']);}
}
