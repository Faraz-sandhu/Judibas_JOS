<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communication_message_edits', function (Blueprint $t) {
            $t->id();
            $t->foreignId('message_id')->constrained('communication_messages')->cascadeOnDelete();
            $t->foreignId('editor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('editor_name');
            // Ordinary-message versions are encrypted at rest; protected messages remain non-editable.
            $t->text('previous_body');
            $t->text('updated_body');
            $t->timestamp('edited_at');
            $t->index(['message_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_message_edits');
    }
};
