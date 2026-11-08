<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE communication_e2ee_messages ADD COLUMN position bigint GENERATED ALWAYS AS IDENTITY UNIQUE');
        DB::statement('CREATE INDEX communication_e2ee_thread_position ON communication_e2ee_messages(thread_id, position)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX communication_e2ee_thread_position');
        DB::statement('ALTER TABLE communication_e2ee_messages DROP COLUMN position');
    }
};
