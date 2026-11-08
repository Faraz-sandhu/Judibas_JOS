<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::create('communication_protection_history', function (Blueprint $t) {
            $t->foreignId('conversation_id')->constrained('communication_conversations')->cascadeOnDelete();
            $t->uuid('key_id');
            $t->text('protection');
            $t->text('recovery')->nullable();
            $t->timestamp('created_at');
            $t->primary(['conversation_id','key_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('communication_protection_history'); }
};
