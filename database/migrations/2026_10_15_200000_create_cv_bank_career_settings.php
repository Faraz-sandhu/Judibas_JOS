<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cv_bank_settings', function (Blueprint $table) {
            $table->id();
            $table->string('theme')->default('studio');
            $table->string('headline', 160)->default('Good people. Meaningful work.');
            $table->text('intro')->nullable();
            $table->timestamps();
        });
        Schema::create('cv_bank_career_editors', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained('users')->cascadeOnDelete();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('cv_bank_career_editors');
        Schema::dropIfExists('cv_bank_settings');
    }
};
