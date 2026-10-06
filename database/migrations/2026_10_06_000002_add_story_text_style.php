<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void { Schema::table('communication_stories', fn (Blueprint $table) => $table->json('text_style')->nullable()); }
 public function down(): void { Schema::table('communication_stories', fn (Blueprint $table) => $table->dropColumn('text_style')); }
};