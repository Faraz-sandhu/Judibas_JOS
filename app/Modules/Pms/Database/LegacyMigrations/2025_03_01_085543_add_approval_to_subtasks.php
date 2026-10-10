<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pms_subtasks', function (Blueprint $table) {
            $table->enum('approval', ['pending', 'approved', 'rejected'])->default('pending');
            $table->longText('approval_note')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pms_subtasks', function (Blueprint $table) {
            $table->dropColumn('approval');
            $table->dropColumn('approval_note');
        });
    }
};
