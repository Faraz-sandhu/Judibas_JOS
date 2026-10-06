<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::table('communication_stories', function (Blueprint $table) { $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete(); $table->unsignedBigInteger('company_id')->nullable()->index(); }); }
    public function down(): void { Schema::table('communication_stories', function (Blueprint $table) { $table->dropConstrainedForeignId('author_id'); $table->dropColumn('company_id'); }); }
};