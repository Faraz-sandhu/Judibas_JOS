<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('realtime_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('enabled')->default(true);
            $table->string('app_id')->nullable();
            $table->string('app_key')->nullable();
            $table->text('app_secret')->nullable();
            $table->string('cluster', 50)->nullable();
            $table->timestamps();
        });
        DB::table('realtime_settings')->insert(['enabled' => true, 'created_at' => now(), 'updated_at' => now()]);

        DB::table('permissions')->updateOrInsert(
            ['permission_key' => 'realtime-settings'],
            ['permission_name' => 'Realtime / Pusher Settings', 'created_at' => now(), 'updated_at' => now()]
        );
        $permissionId = DB::table('permissions')->where('permission_key', 'realtime-settings')->value('id');
        foreach (DB::table('roles')->where('role_key', 'admin')->pluck('id') as $roleId) {
            DB::table('permission_roles')->updateOrInsert(['role_id' => $roleId, 'permission_id' => $permissionId]);
        }
        Cache::forget('authorization.permission_definitions');
        Cache::forget('schema.has_realtime_settings');
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('permission_key', 'realtime-settings')->value('id');
        DB::table('permission_roles')->where('permission_id', $permissionId)->delete();
        DB::table('permissions')->where('id', $permissionId)->delete();
        Schema::dropIfExists('realtime_settings');
        Cache::forget('authorization.permission_definitions');
        Cache::forget('schema.has_realtime_settings');
        Cache::forget('realtime.settings');
    }
};
