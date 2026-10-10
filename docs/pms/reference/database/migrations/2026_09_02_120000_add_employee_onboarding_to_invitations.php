<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            $table->string('purpose', 30)->default('resource')->after('role')->index();
            $table->foreignId('role_id')->nullable()->after('purpose')->constrained('roles')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->after('role_id')->constrained('departments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            $table->dropForeign(['role_id']);
            $table->dropForeign(['department_id']);
            $table->dropIndex(['purpose']);
            $table->dropColumn(['purpose', 'role_id', 'department_id']);
        });
    }
};
