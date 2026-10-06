<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communication_story_views', function (Blueprint $t) {
            $t->id();
            $t->foreignId('story_id')->constrained('communication_stories')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->timestamp('first_viewed_at');
            $t->timestamp('last_viewed_at');
            $t->unique(['story_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_story_views');
    }
};
