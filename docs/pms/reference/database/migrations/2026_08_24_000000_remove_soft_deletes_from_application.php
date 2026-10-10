<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = [
        'role_users',
        'permission_roles',
        'summaries',
        'submissions',
        'departments',
        'permissions',
        'roles',
        'users',
    ];

    public function up(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'deleted_at')) {
            $deletedUserIds = DB::table('users')->whereNotNull('deleted_at')->pluck('id');

            if ($deletedUserIds->isNotEmpty()) {
                if (Schema::hasTable('invitations')) {
                    DB::table('invitations')
                        ->whereIn('sender_id', $deletedUserIds)
                        ->orWhereIn('recipient_id', $deletedUserIds)
                        ->delete();
                }

                if (Schema::hasTable('notifications')) {
                    DB::table('notifications')
                        ->where('notifiable_type', App\Models\User::class)
                        ->whereIn('notifiable_id', $deletedUserIds)
                        ->delete();
                }
            }
        }

        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'deleted_at')) {
                continue;
            }

            DB::table($table)->whereNotNull('deleted_at')->delete();

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn('deleted_at');
            });
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->tables) as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'deleted_at')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->softDeletes();
            });
        }
    }
};
