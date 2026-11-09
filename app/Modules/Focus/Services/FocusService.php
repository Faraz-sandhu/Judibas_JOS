<?php

namespace App\Modules\Focus\Services;

use App\Modules\Focus\Events\FocusChanged;
use App\Services\CommunicationAccess;
use App\Services\PlatformAccess;
use Carbon\CarbonImmutable as Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FocusService
{
    public const DEFAULTS = ['focus_minutes' => 25, 'short_break' => 5, 'long_break' => 15, 'long_after' => 4, 'auto_focus' => false, 'auto_break' => false, 'disable_breaks' => false, 'daily_goal' => 180, 'add_top' => true, 'completion_sound' => true, 'groups' => true, 'forest' => true, 'ranking' => true, 'noise' => 'none', 'noise_volume' => 30, 'alarm' => 'bell', 'break_alarm' => 'chime', 'alarm_volume' => 70, 'background' => 'plain', 'timezone' => 'Asia/Karachi', 'lists' => ['Tomorrow', 'This week', 'Planned', 'Events', 'Completed']];

    public static function owner(Request $r): string
    {
        abort_unless(PlatformAccess::product($r, 'focus-to-do'), 403);

        return PlatformAccess::super($r) ? 'super' : 'user:'.$r->user()->id;
    }

    public static function settings(string $owner): array
    {
        DB::table('focus_preferences')->insertOrIgnore(['owner' => $owner, 'settings' => json_encode(self::DEFAULTS), 'created_at' => now()->toIso8601String(), 'updated_at' => now()->toIso8601String()]);

        return array_replace(self::DEFAULTS, json_decode(DB::table('focus_preferences')->where('owner', $owner)->value('settings'), true));
    }

    public static function companies(Request $r): array
    {
        $q = DB::table('communication_companies');
        if (! PlatformAccess::administrator($r)) {
            $q->whereIn('id', CommunicationAccess::companies($r->user()->id));
        }

        return $q->orderBy('name')->get(['id', 'name'])->all();
    }

    public static function notify(string $owner, string $title, ?int $task = null): void
    {
        DB::table('focus_notifications')->insert(['owner' => $owner, 'title' => $title, 'task_id' => $task, 'created_at' => now()->toIso8601String(), 'updated_at' => now()->toIso8601String()]);
    }

    public static function changed(string $owner): void
    {
        try {
            event(new FocusChanged($owner));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** Social invalidations contain no task, note, message or session content. */
    public static function companyChanged(int $company): void
    {
        $people = DB::table('users')->where('is_active', true)->where(function ($q) use ($company) {
            $q->where('company_id', $company)->orWhereIn('id', DB::table('communication_company_memberships')->where('company_id', $company)->select('user_id'))->orWhere('judibas_administrator', true);
        })->where(function ($q) {
            $q->where('judibas_administrator', true)->orWhereIn('id', DB::table('user_product_access')->where('product_slug', 'focus-to-do')->select('user_id'));
        })->pluck('id')->map(fn ($id) => 'user:'.$id)->push('super')->unique()->all();
        foreach (array_chunk($people, 100) as $owners) {
            try {
                event(new FocusChanged($owners));
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }

    private static function socialChanged(string $owner): void
    {
        if (! str_starts_with($owner, 'user:')) {
            return;
        }
        $companies = CommunicationAccess::companies((int) substr($owner, 5));
        DB::afterCommit(function () use ($companies) {
            foreach ($companies as $company) {
                self::companyChanged($company);
            }
        });
    }

    public static function reminders(): void
    {
        DB::table('focus_tasks')->whereNull('completed_at')->whereNull('reminded_at')->where('remind_at', '<=', now()->toIso8601String())->orderBy('id')->chunkById(100, function ($tasks) {
            foreach ($tasks as $task) {
                $sent = DB::transaction(function () use ($task) {
                    if (! DB::table('focus_tasks')->where('id', $task->id)->whereNull('reminded_at')->update(['reminded_at' => now()->toIso8601String()])) {
                        return false;
                    }
                    self::notify($task->owner, 'Reminder: '.$task->title, $task->id);

                    return true;
                });
                if ($sent) {
                    self::changed($task->owner);
                }
            }
        });
    }

    public static function tick(): void
    {
        self::reminders();
        foreach (DB::table('focus_sessions')->whereNotNull('active_owner')->where('status', 'running')->pluck('owner') as $owner) {
            $before = DB::table('focus_sessions')->where('active_owner', $owner)->value('id');
            $after = self::timer($owner, 'sync');
            if (! $after || (int) $after->id !== (int) $before) {
                self::changed($owner);
            }
        }
    }

    public static function reward(string $owner, string $source, string $reason, int $grams): void
    {
        DB::table('focus_rewards')->insertOrIgnore(['owner' => $owner, 'source' => $owner.':'.$source, 'reason' => $reason, 'grams' => $grams, 'created_at' => now()->toIso8601String(), 'updated_at' => now()->toIso8601String()]);
    }

    /** Lock preferences first: serialises starts, completion, pause and retries per person. */
    public static function timer(string $owner, string $action, array $input = []): ?object
    {
        self::settings($owner);

        return DB::transaction(function () use ($owner, $action, $input) {
            $prefs = DB::table('focus_preferences')->where('owner', $owner)->lockForUpdate()->first();
            $s = array_replace(self::DEFAULTS, json_decode($prefs->settings, true));
            $active = DB::table('focus_sessions')->where('active_owner', $owner)->lockForUpdate()->first();
            if ($active && $active->status === 'running') {
                $seconds = min(max(0, Carbon::parse($active->segment_started_at)->diffInSeconds(Carbon::now(), false)), max(0, $active->target_seconds - $active->elapsed_seconds));
                if ($active->elapsed_seconds + $seconds >= $active->target_seconds) {
                    self::closeSegment($active, (int) $seconds);
                    self::finish($active, $s);
                    $active = DB::table('focus_sessions')->where('active_owner', $owner)->first();
                }
            }
            if ($action === 'sync') {
                return $active;
            }
            if ($action === 'start') {
                abort_if($active, 422, 'Finish or stop the current timer first.');
                $task = DB::table('focus_tasks')->where('owner', $owner)->where('id', $input['task_id'] ?? 0)->whereNull('completed_at')->first();
                abort_unless($task, 422, 'Select an unfinished task.');

                return self::newSession($owner, $task->id, $task->title, 'focus', $input['mode'] ?? 'countdown', $s['focus_minutes'] * 60, true);
            }
            if (! $active) {
                return null;
            }
            // A stale browser action cannot stop a newer timer.
            abort_unless((int) ($input['session_id'] ?? 0) === (int) $active->id, 409, 'Timer changed on another page. Reload its current state.');
            if ($action === 'resume') {
                abort_unless(in_array($active->status, ['paused', 'ready']), 422);
                DB::table('focus_sessions')->where('id', $active->id)->update(['status' => 'running', 'segment_started_at' => now()->toIso8601String(), 'updated_at' => now()->toIso8601String()]);
            } elseif (in_array($action, ['pause', 'stop', 'reset'])) {
                if ($active->status === 'running') {
                    $seconds = (int) min(max(0, Carbon::parse($active->segment_started_at)->diffInSeconds(Carbon::now(), false)), max(0, $active->target_seconds - $active->elapsed_seconds));
                    self::closeSegment($active, $seconds);
                }
                if ($action === 'pause') {
                    DB::table('focus_sessions')->where('id', $active->id)->update(['status' => 'paused', 'segment_started_at' => null]);
                } else {
                    DB::table('focus_sessions')->where('id', $active->id)->update(['status' => $action === 'reset' ? 'cancelled' : 'finished', 'active_owner' => null, 'ended_at' => now()->toIso8601String(), 'segment_started_at' => null]);
                    if ($action === 'reset') {
                        return self::newSession($owner, $active->task_id, $active->task_title, $active->phase, $active->mode, $active->target_seconds, false);
                    }

                    self::socialChanged($owner);

                    return null;
                }
            }

            return DB::table('focus_sessions')->where('active_owner', $owner)->first();
        });
    }

    private static function closeSegment(object $a, int $seconds): void
    {
        $segments = json_decode($a->segments, true);
        if ($seconds > 0) {
            $segments[] = ['start' => Carbon::parse($a->segment_started_at)->toIso8601String(), 'seconds' => $seconds];
        }
        $a->elapsed_seconds += $seconds;
        $a->segments = json_encode($segments);
        DB::table('focus_sessions')->where('id', $a->id)->update(['elapsed_seconds' => $a->elapsed_seconds, 'segments' => $a->segments, 'segment_started_at' => null, 'updated_at' => now()->toIso8601String()]);
    }

    private static function finish(object $a, array $s): void
    {
        DB::table('focus_sessions')->where('id', $a->id)->update(['status' => 'finished', 'active_owner' => null, 'ended_at' => now()->toIso8601String(), 'segment_started_at' => null, 'updated_at' => now()->toIso8601String()]);
        self::notify($a->owner, $a->phase === 'focus' ? 'Focus session completed. Time for a break.' : 'Break completed. Ready to focus?', $a->task_id);
        if ($a->phase === 'focus') {
            self::reward($a->owner, 'session:'.$a->id, 'Completed focus session', max(1, (int) floor($a->elapsed_seconds / 60)));
            self::socialChanged($a->owner);
        }
        $task = $a->task_id ? DB::table('focus_tasks')->where('id', $a->task_id)->whereNull('completed_at')->first() : null;
        if (! $task) {
            return;
        }
        if ($a->phase === 'focus' && ! $s['disable_breaks']) {
            $count = DB::table('focus_sessions')->where('owner', $a->owner)->where('phase', 'focus')->where('status', 'finished')->where('manual', false)->whereColumn('elapsed_seconds', '>=', 'target_seconds')->count();
            $phase = $count % $s['long_after'] === 0 ? 'long' : 'short';
            self::newSession($a->owner, $task->id, $task->title, $phase, 'countdown', $s[$phase.'_break'] * 60, $s['auto_break']);
        } elseif ($s['auto_focus']) {
            self::newSession($a->owner, $task->id, $task->title, 'focus', 'countdown', $s['focus_minutes'] * 60, true);
        }
    }

    private static function newSession(string $owner, ?int $task, ?string $title, string $phase, string $mode, int $duration, bool $run): object
    {
        $id = DB::table('focus_sessions')->insertGetId(['owner' => $owner, 'active_owner' => $owner, 'task_id' => $task, 'task_title' => $title, 'phase' => $phase, 'mode' => $mode, 'status' => $run ? 'running' : 'ready', 'target_seconds' => $mode === 'stopwatch' ? 86400 : $duration, 'elapsed_seconds' => 0, 'segment_started_at' => $run ? now()->toIso8601String() : null, 'started_at' => now()->toIso8601String(), 'segments' => '[]', 'created_at' => now()->toIso8601String(), 'updated_at' => now()->toIso8601String()]);

        return DB::table('focus_sessions')->where('id', $id)->first();
    }

    public static function report(string $owner, array $s, string $from, string $to): array
    {
        $zone = $s['timezone'];
        $daily = [];
        $projects = [];
        $hours = array_fill(0, 24, 0);
        $total = 0;
        $sessions = DB::table('focus_sessions')->where('owner', $owner)->where('phase', 'focus')->where('status', '!=', 'cancelled')->where('started_at', '<=', Carbon::parse($to, $zone)->endOfDay()->utc()->toIso8601String())->get();
        $tasks = DB::table('focus_tasks')->where('owner', $owner)->get()->keyBy('id');
        foreach ($sessions as $session) {
            $segments = json_decode($session->segments, true);
            if ($session->status === 'running' && $session->segment_started_at) {
                $segments[] = ['start' => $session->segment_started_at, 'seconds' => (int) min(max(0, Carbon::parse($session->segment_started_at)->diffInSeconds(Carbon::now(), false)), max(0, $session->target_seconds - $session->elapsed_seconds))];
            }
            foreach ($segments as $seg) {
                $cursor = Carbon::parse($seg['start'])->setTimezone($zone);
                $left = (int) $seg['seconds'];
                while ($left > 0) {
                    $take = min($left, max(1, (int) $cursor->diffInSeconds($cursor->startOfHour()->addHour())));
                    $date = $cursor->toDateString();
                    if ($date >= $from && $date <= $to) {
                        $daily[$date] = ($daily[$date] ?? 0) + $take;
                        $hours[$cursor->hour] += $take;
                        $total += $take;
                        $pid = $tasks->get($session->task_id)?->project_id ?? 0;
                        $projects[$pid] = ($projects[$pid] ?? 0) + $take;
                    }
                    $cursor = $cursor->addSeconds($take);
                    $left -= $take;
                }
            }
        }
        ksort($daily);
        $completed = DB::table('focus_tasks')->where('owner', $owner)->whereBetween('completed_at', [Carbon::parse($from, $zone)->startOfDay()->utc()->toIso8601String(), Carbon::parse($to, $zone)->endOfDay()->utc()->toIso8601String()])->count();

        return ['seconds' => $total, 'daily' => $daily, 'hours' => $hours, 'projects' => $projects, 'completed' => $completed, 'goal_seconds' => $s['daily_goal'] * 60];
    }
}
