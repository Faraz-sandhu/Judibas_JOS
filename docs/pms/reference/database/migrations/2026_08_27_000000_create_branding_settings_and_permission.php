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
        Schema::create('branding_settings', function (Blueprint $table) {
            $table->id();
            $table->string('sidebar_logo')->nullable();
            $table->string('sidebar_logo_text')->nullable();
            $table->string('login_logo')->nullable();
            $table->string('login_cover')->nullable();
            $table->string('favicon')->nullable();
            $table->timestamps();
        });
        DB::table('branding_settings')->insert(['created_at' => now(), 'updated_at' => now()]);
        DB::table('permissions')->updateOrInsert(
            ['permission_key' => 'branding-settings'],
            ['permission_name' => 'Branding Settings', 'created_at' => now(), 'updated_at' => now()]
        );
        $permissionId = DB::table('permissions')->where('permission_key', 'branding-settings')->value('id');
        foreach (DB::table('roles')->where('role_key', 'admin')->pluck('id') as $roleId) {
            DB::table('permission_roles')->updateOrInsert(['role_id' => $roleId, 'permission_id' => $permissionId]);
        }
        Cache::forget('authorization.permission_definitions');
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('permission_key', 'branding-settings')->value('id');
        DB::table('permission_roles')->where('permission_id', $permissionId)->delete();
        DB::table('permissions')->where('id', $permissionId)->delete();
        Schema::dropIfExists('branding_settings');
        Cache::forget('authorization.permission_definitions');
    }
};
