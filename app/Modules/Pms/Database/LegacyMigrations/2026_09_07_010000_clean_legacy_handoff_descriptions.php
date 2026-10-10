<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $marker = '<p><strong>Department handoff</strong></p>';

        DB::table('pms_task_handoffs')
            ->whereNotNull('destination_task_id')
            ->orderBy('id')
            ->each(function ($handoff) use ($marker): void {
                $description = DB::table('pms_tasks')->where('id', $handoff->destination_task_id)->value('description');
                if (! is_string($description) || ! str_contains($description, $marker)) {
                    return;
                }

                DB::table('pms_tasks')->where('id', $handoff->destination_task_id)->update([
                    'description' => rtrim(strstr($description, $marker, true)),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        // The legacy presentation HTML cannot be reconstructed reliably.
    }
};
