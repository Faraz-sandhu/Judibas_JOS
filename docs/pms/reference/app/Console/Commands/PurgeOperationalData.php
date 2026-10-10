<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class PurgeOperationalData extends Command
{
    private const CONFIRMATION = 'DELETE-OPERATIONAL-DATA';

    /**
     * Tables that contain workspace activity or records belonging to projects.
     * Identity, authorization, company, department, workflow, and settings tables
     * are intentionally absent from this list.
     */
    private const TABLES = [
        'notifications',
        'sub_task_pending_summaries',
        'timer_logs',
        'task_estimate_histories',
        'task_attachments',
        'task_comments',
        'submissions',
        'subtask_user',
        'task_user',
        'project_user',
        'project_chats',
        'project_workflow',
        'subtasks',
        'tasks',
        'sprints',
        'summaries',
        'projects',
    ];

    protected $signature = 'pms:purge-operational-data
        {--execute : Permanently delete the displayed records}
        {--confirmation= : Must exactly equal DELETE-OPERATIONAL-DATA}
        {--backup-confirmed : Confirm that a current database backup has been created}
        {--delete-files : Also delete project/task uploads from the public disk}';

    protected $description = 'Safely remove project and testing activity while preserving users and configuration';

    public function handle(): int
    {
        $tables = collect(self::TABLES)->filter(fn (string $table) => Schema::hasTable($table));
        $counts = $tables->mapWithKeys(fn (string $table) => [$table => DB::table($table)->count()]);

        $this->newLine();
        $this->warn('Operational data cleanup preview');
        $this->table(['Table', 'Rows to delete'], $counts->map(fn (int $count, string $table) => [$table, $count])->values());
        $this->line('Total rows to delete: <fg=yellow>'.$counts->sum().'</>');
        $this->newLine();
        $this->info('Preserved: users, user departments, roles, permissions, departments, companies, workflows, invitations, invitation email-delivery history, queued jobs, direct messages, branding, SMTP settings, sessions, and migrations.');
        $this->warn('All in-app notification records will be deleted.');

        if (! $this->option('execute')) {
            $this->comment('Dry run only. No data was changed.');
            $this->line('After making a verified backup, run with:');
            $this->line('php artisan pms:purge-operational-data --execute --backup-confirmed --confirmation='.self::CONFIRMATION.' --delete-files');

            return self::SUCCESS;
        }

        if (! $this->option('backup-confirmed')) {
            $this->error('Cleanup refused: create and verify a database backup, then pass --backup-confirmed.');

            return self::FAILURE;
        }

        if ($this->option('confirmation') !== self::CONFIRMATION) {
            $this->error('Cleanup refused: the confirmation text is missing or incorrect.');

            return self::FAILURE;
        }

        try {
            Schema::disableForeignKeyConstraints();
            DB::transaction(function () use ($tables): void {
                foreach ($tables as $table) {
                    DB::table($table)->delete();
                }
            }, 3);
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Cleanup failed and database changes were rolled back: '.$exception->getMessage());

            return self::FAILURE;
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        if ($this->option('delete-files')) {
            foreach (['projects', 'tasks', 'task_attachment'] as $directory) {
                Storage::disk('public')->deleteDirectory($directory);
            }
            $this->info('Project and task upload directories were removed.');
        } else {
            $this->warn('Database records were removed, but uploaded project/task files were retained.');
        }

        Cache::flush();
        $this->newLine();
        $this->info('Operational data cleanup completed successfully.');

        return self::SUCCESS;
    }
}
