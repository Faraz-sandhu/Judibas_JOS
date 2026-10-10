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
        Schema::create('mail_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('enabled')->default(false);
            $table->string('host')->nullable();
            $table->unsignedSmallInteger('port')->default(587);
            $table->string('scheme')->nullable();
            $table->string('username')->nullable();
            $table->text('password')->nullable();
            $table->string('from_address')->nullable();
            $table->string('from_name')->nullable();
            $table->timestamps();
        });
        DB::table('mail_settings')->insert(['enabled' => false, 'port' => 587, 'created_at' => now(), 'updated_at' => now()]);

        DB::table('permissions')->updateOrInsert(
            ['permission_key' => 'mail-settings'],
            ['permission_name' => 'Email / SMTP Settings', 'created_at' => now(), 'updated_at' => now()]
        );
        $permissionId = DB::table('permissions')->where('permission_key', 'mail-settings')->value('id');
        foreach (DB::table('roles')->where('role_key', 'admin')->pluck('id') as $roleId) {
            DB::table('permission_roles')->updateOrInsert(['role_id' => $roleId, 'permission_id' => $permissionId]);
        }
        Cache::forget('authorization.permission_definitions');
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('permission_key', 'mail-settings')->value('id');
        DB::table('permission_roles')->where('permission_id', $permissionId)->delete();
        DB::table('permissions')->where('id', $permissionId)->delete();
        Schema::dropIfExists('mail_settings');
        Cache::forget('authorization.permission_definitions');
    }
};
