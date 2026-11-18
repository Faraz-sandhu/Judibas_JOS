<?php

namespace App\Modules\Focus\Http\Controllers;

use App\Modules\Focus\Services\FocusService as F;
use App\Services\PlatformAccess;
use Carbon\CarbonImmutable as Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class FocusController
{
    public function data(Request $r): array
    {
        $o = F::owner($r);
        $s = F::settings($o);
        $timer = F::timer($o, 'sync');
        F::reminders();
        $today = now($s['timezone'])->toDateString();
        F::reward($o, 'login:'.$today, 'Daily visit', 30);
        $previous = Carbon::parse($today)->subDay()->toDateString();
        if (DB::table('focus_rewards')->where('source', $o.':login:'.$previous)->exists()) {
            F::reward($o, 'streak:'.$today, 'Consecutive-day visit', 6);
        }
        $tasks = DB::table('focus_tasks')->where('owner', $o)->orderBy('id', $s['add_top'] ? 'desc' : 'asc')->get()->map(function ($t) {
            $t->subtasks = json_decode($t->subtasks, true);
            $t->tags = json_decode($t->tags, true);

            return $t;
        });

        return ['settings' => $s, 'tasks' => $tasks, 'projects' => DB::table('focus_projects')->where('owner', $o)->orderBy('name')->get(), 'timer' => $timer, 'server_now' => now()->toIso8601String(), 'today' => $today,
            'summary' => F::report($o, $s, $today, $today),
            'week_summary' => F::report($o, $s, Carbon::parse($today)->startOfWeek()->toDateString(), $today),
            'lifetime_seconds' => (int) DB::table('focus_sessions')->where('owner', $o)->where('phase', 'focus')->where('status', 'finished')->sum('elapsed_seconds'),
            'lifetime_completed' => DB::table('focus_tasks')->where('owner', $o)->whereNotNull('completed_at')->count(),
            'pending_sunlight' => (int) DB::table('focus_rewards')->where('owner', $o)->whereNull('collected_at')->sum('grams'),
            'notifications' => DB::table('focus_notifications')->where('owner', $o)->orderByDesc('id')->limit(50)->get(),
            'sessions' => DB::table('focus_sessions')->where('owner', $o)->orderByDesc('id')->limit(100)->get(), 'companies' => F::companies($r),
            'rewards' => DB::table('focus_rewards')->where('owner', $o)->orderByDesc('id')->limit(100)->get(),
            'sunlight' => (int) DB::table('focus_rewards')->where('owner', $o)->whereNotNull('collected_at')->sum('grams')];
    }

    public function task(Request $r, ?int $id = null): array
    {
        $o = F::owner($r);
        $v = $r->validate(['title' => 'required|string|max:250', 'notes' => 'nullable|string|max:15000', 'project_id' => 'nullable|integer', 'priority' => 'required|in:none,low,medium,high', 'estimate' => 'required|integer|min:0|max:999', 'due_date' => 'nullable|date_format:Y-m-d', 'remind_at' => 'nullable|date', 'repeat' => 'required|in:none,daily,weekly,monthly,yearly', 'tags' => 'present|array|max:20', 'tags.*' => 'string|max:40', 'subtasks' => 'present|array|max:100', 'subtasks.*.title' => 'required|string|max:250', 'subtasks.*.done' => 'required|boolean']);
        if (! empty($v['project_id'])) {
            abort_unless(DB::table('focus_projects')->where('owner', $o)->where('id', $v['project_id'])->where('is_folder', false)->exists(), 422, 'Choose one of your projects.');
        }
        $v['tags'] = json_encode(array_values(array_unique($v['tags'])));
        $v['subtasks'] = json_encode($v['subtasks']);
        $v['updated_at'] = now()->toIso8601String();
        $v['remind_at'] = empty($v['remind_at']) ? null : Carbon::parse($v['remind_at'])->utc()->toIso8601String();
        $v['reminded_at'] = null;
        if ($id) {
            $q = DB::table('focus_tasks')->where('owner', $o)->where('id', $id);
            abort_unless($q->exists(), 404);
            $q->update($v);
        } else {
            $id = DB::table('focus_tasks')->insertGetId($v + ['owner' => $o, 'created_at' => now()->toIso8601String()]);
        }
        F::changed($o);

        return ['id' => $id];
    }

    public function complete(Request $r, int $id): array
    {
        $o = F::owner($r);
        $v = $r->validate(['completed' => 'required|boolean']);
        DB::transaction(function () use ($o, $id, $v) {
            $task = DB::table('focus_tasks')->where('owner', $o)->where('id', $id)->lockForUpdate()->first();
            abort_unless($task, 404);
            if ((bool) $task->completed_at === $v['completed']) {
                return;
            }
            DB::table('focus_tasks')->where('id', $id)->update(['completed_at' => $v['completed'] ? now()->toIso8601String() : null, 'updated_at' => now()->toIso8601String()]);
            if ($v['completed'] && $task->repeat !== 'none') {
                $base = Carbon::parse($task->due_date ?? now(F::settings($o)['timezone'])->toDateString());
                $date = match ($task->repeat) {
                    'daily' => $base->addDay(),'weekly' => $base->addWeek(),'monthly' => $base->addMonthNoOverflow(),default => $base->addYearNoOverflow()
                };
                // Each occurrence is spawned once; reopening never clones it again.
                $marker = 'repeat:'.$id;
                if (! DB::table('focus_rewards')->where('source', $o.':'.$marker)->exists()) {
                    F::reward($o, $marker, 'Recurring task created', 0);
                    $copy = (array) $task;
                    unset($copy['id']);
                    $copy['due_date'] = $date->toDateString();
                    $copy['completed_at'] = null;
                    $copy['remind_at'] = null;
                    $copy['reminded_at'] = null;
                    $copy['created_at'] = now()->toIso8601String();
                    $copy['updated_at'] = now()->toIso8601String();
                    $copy['subtasks'] = json_encode(array_map(fn ($a) => ['title' => $a['title'], 'done' => false], json_decode($task->subtasks, true)));
                    DB::table('focus_tasks')->insert($copy);
                }
            }
        });
        F::changed($o);

        return ['ok' => true];
    }

    public function deleteTask(Request $r, int $id): array
    {
        $o = F::owner($r);
        abort_if(DB::table('focus_sessions')->where('active_owner', $o)->where('task_id', $id)->exists(), 422, 'Stop this task’s timer before deleting it.');
        abort_unless(DB::table('focus_tasks')->where('owner', $o)->where('id', $id)->delete(), 404);
        F::changed($o);

        return ['ok' => true];
    }

    public function project(Request $r, ?int $id = null): array
    {
        $o = F::owner($r);
        $v = $r->validate(['name' => 'required|string|max:120', 'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'], 'is_folder' => 'required|boolean', 'folder_id' => 'nullable|integer']);
        if ($v['is_folder']) {
            $v['folder_id'] = null;
        }
        if (! empty($v['folder_id'])) {
            abort_unless(DB::table('focus_projects')->where('owner', $o)->where('id', $v['folder_id'])->where('is_folder', true)->exists(), 422, 'Choose your own folder.');
        }
        if ($id) {
            $q = DB::table('focus_projects')->where('owner', $o)->where('id', $id);
            $old = $q->first();
            abort_unless($old, 404);
            abort_unless((bool) $old->is_folder === $v['is_folder'], 422, 'Account folders and projects cannot change type.');
            $q->update($v + ['updated_at' => now()->toIso8601String()]);
        } else {
            $id = DB::table('focus_projects')->insertGetId($v + ['owner' => $o, 'created_at' => now()->toIso8601String(), 'updated_at' => now()->toIso8601String()]);
        }
        F::changed($o);

        return ['id' => $id];
    }

    public function deleteProject(Request $r, int $id): array
    {
        $o = F::owner($r);
        abort_unless(DB::table('focus_projects')->where('owner', $o)->where('id', $id)->delete(), 404);
        F::changed($o);

        return ['ok' => true];
    }

    public function timer(Request $r): array
    {
        $o = F::owner($r);
        $v = $r->validate(['action' => 'required|in:start,pause,resume,stop,reset,sync', 'task_id' => 'nullable|integer', 'session_id' => 'nullable|integer', 'mode' => 'nullable|in:countdown,stopwatch']);
        $timer = F::timer($o, $v['action'], $v);
        F::changed($o);

        return ['timer' => $timer, 'server_now' => now()->toIso8601String()];
    }

    public function settings(Request $r): array
    {
        $o = F::owner($r);
        $v = $r->validate(['focus_minutes' => 'required|integer|min:1|max:180', 'short_break' => 'required|integer|min:1|max:60', 'long_break' => 'required|integer|min:1|max:120', 'long_after' => 'required|integer|min:1|max:12', 'daily_goal' => 'required|integer|min:1|max:1440', 'timezone' => 'required|timezone', 'auto_focus' => 'required|boolean', 'auto_break' => 'required|boolean', 'disable_breaks' => 'required|boolean', 'add_top' => 'required|boolean', 'completion_sound' => 'required|boolean', 'groups' => 'required|boolean', 'forest' => 'required|boolean', 'ranking' => 'required|boolean', 'noise' => 'required|in:none,tick,countdown,wind,classroom', 'noise_volume' => 'required|integer|min:0|max:100', 'alarm' => 'required|in:bell,chime,digital,none', 'break_alarm' => 'sometimes|in:bell,chime,digital,none', 'alarm_volume' => 'required|integer|min:0|max:100', 'background' => 'required|in:plain,lavender,forest', 'lists' => 'present|array', 'lists.*' => Rule::in(['Tomorrow', 'This week', 'Planned', 'Events', 'Completed', 'Overdue', 'Next 7 days', 'High priority', 'Medium priority', 'Low priority', 'All', 'Someday'])]);
        F::settings($o);
        DB::table('focus_preferences')->where('owner', $o)->update(['settings' => json_encode($v), 'updated_at' => now()->toIso8601String()]);
        F::changed($o);

        return ['ok' => true];
    }

    public function report(Request $r): array
    {
        $o = F::owner($r);
        $v = $r->validate(['from' => 'required|date_format:Y-m-d', 'to' => 'required|date_format:Y-m-d|after_or_equal:from']);
        abort_if(Carbon::parse($v['from'])->diffInDays(Carbon::parse($v['to'])) > 3660, 422, 'Select a range of up to ten years.');

        return F::report($o, F::settings($o), $v['from'], $v['to']);
    }

    public function manual(Request $r, ?int $id = null): array
    {
        $o = F::owner($r);
        $v = $r->validate(['task_id' => 'required|integer', 'started_at' => 'required|date|before_or_equal:now', 'minutes' => 'required|integer|min:1|max:1440']);
        $task = DB::table('focus_tasks')->where('owner', $o)->where('id', $v['task_id'])->first();
        abort_unless($task, 404);
        $start = Carbon::parse($v['started_at'])->utc();
        abort_if($start->addMinutes($v['minutes'])->isFuture(), 422, 'Focus records cannot extend into the future.');
        $values = ['task_id' => $task->id, 'task_title' => $task->title, 'started_at' => $start->toIso8601String(), 'ended_at' => $start->addMinutes($v['minutes'])->toIso8601String(), 'elapsed_seconds' => $v['minutes'] * 60, 'target_seconds' => $v['minutes'] * 60, 'segments' => json_encode([['start' => $start->toIso8601String(), 'seconds' => $v['minutes'] * 60]]), 'updated_at' => now()->toIso8601String()];
        if ($id) {
            $q = DB::table('focus_sessions')->where('id', $id)->where('owner', $o)->where('manual', true);
            abort_unless($q->exists(), 404);
            $q->update($values);
        } else {
            DB::table('focus_sessions')->insert($values + ['owner' => $o, 'phase' => 'focus', 'mode' => 'manual', 'manual' => true, 'status' => 'finished', 'created_at' => now()->toIso8601String()]);
        }
        F::changed($o);

        return ['ok' => true];
    }

    public function records(Request $r): array
    {
        $o = F::owner($r);
        $v = $r->validate(['from' => 'required|date_format:Y-m-d', 'to' => 'required|date_format:Y-m-d|after_or_equal:from', 'page' => 'nullable|integer|min:1']);
        $zone = F::settings($o)['timezone'];
        $records = DB::table('focus_sessions')->where('owner', $o)->where('phase', 'focus')->where('status', 'finished')
            ->whereBetween('started_at', [Carbon::parse($v['from'], $zone)->startOfDay()->utc()->toIso8601String(), Carbon::parse($v['to'], $zone)->endOfDay()->utc()->toIso8601String()])
            ->orderByDesc('started_at')->paginate(15);

        return ['records' => $records->items(), 'page' => $records->currentPage(), 'last_page' => $records->lastPage(), 'total' => $records->total()];
    }

    public function notifications(Request $r): array
    {
        $o = F::owner($r);
        DB::table('focus_notifications')->where('owner', $o)->when($r->integer('id'), fn ($q) => $q->where('id', $r->integer('id')))->whereNull('read_at')->update(['read_at' => now()->toIso8601String()]);
        F::changed($o);

        return ['ok' => true];
    }

    public function collect(Request $r): array
    {
        $o = F::owner($r);
        DB::table('focus_rewards')->where('owner', $o)->whereNull('collected_at')->update(['collected_at' => now()->toIso8601String(), 'updated_at' => now()->toIso8601String()]);
        F::changed($o);

        return ['ok' => true];
    }

    private function eligible(Request $r, int $company): array
    {
        abort_unless(collect(F::companies($r))->contains('id', $company), 403);

        return DB::table('users')->where(function ($q) use ($company) {
            \App\Services\ProductCompanyAccess::users($q,'focus-to-do',[$company]);
        })->where('users.is_active', true)
            ->whereExists(fn ($q) => $q->selectRaw('1')->from('user_product_access')->whereColumn('user_product_access.user_id', 'users.id')->where('product_slug', 'focus-to-do'))->select('users.id', 'users.name')->distinct()->get()->all();
    }

    public function social(Request $r): array
    {
        $o = F::owner($r);
        $v = $r->validate(['company_id' => 'required|integer', 'period' => 'nullable|in:overall,month']);
        $people = $this->eligible($r, $v['company_id']);
        $owners = array_map(fn ($p) => 'user:'.$p->id, $people);
        if (PlatformAccess::super($r)) {
            $owners[] = 'super';
        }
        $totals = DB::table('focus_sessions')->whereIn('owner', $owners)->where('phase', 'focus')->where('manual', false)->where('status', 'finished')->when(($v['period'] ?? 'month') === 'month', fn ($q) => $q->where('started_at', '>=', now()->subDays(30)->toIso8601String()))->groupBy('owner')->selectRaw('owner, SUM(elapsed_seconds) as seconds')->pluck('seconds', 'owner');
        $names = collect($people)->mapWithKeys(fn ($p) => ['user:'.$p->id => $p->name])->all();
        $names['super'] = 'Super Admin';
        $rank = collect($owners)->map(fn ($key) => ['owner' => $key, 'name' => $names[$key] ?? 'Member', 'seconds' => (int) ($totals[$key] ?? 0), 'you' => $key === $o])->sortByDesc('seconds')->values();
        $groups = DB::table('focus_groups')->where('company_id', $v['company_id'])->orderBy('name')->get()->map(function ($g) use ($owners, $o, $totals, $names) {
            $members = DB::table('focus_group_members')->where('group_id', $g->id)->whereIn('owner', $owners)->pluck('owner');

            return ['id' => $g->id, 'name' => $g->name, 'description' => $g->description, 'mine' => $members->contains($o), 'owned' => $g->owner === $o, 'members' => $members->map(fn ($m) => ['name' => $names[$m] ?? 'Member', 'seconds' => (int) ($totals[$m] ?? 0)])->values(), 'seconds' => $members->sum(fn ($m) => (int) ($totals[$m] ?? 0))];
        });

        return ['groups' => $groups, 'rankings' => $rank];
    }

    public function group(Request $r): array
    {
        $o = F::owner($r);
        $v = $r->validate(['action' => 'required|in:create,join,leave,delete', 'company_id' => 'required|integer', 'id' => 'nullable|integer', 'name' => 'required_if:action,create|string|max:120', 'description' => 'nullable|string|max:1000']);
        $this->eligible($r, $v['company_id']);
        if ($v['action'] === 'create') {
            $id = DB::table('focus_groups')->insertGetId(['owner' => $o, 'company_id' => $v['company_id'], 'name' => $v['name'], 'description' => $v['description'] ?? '', 'created_at' => now()->toIso8601String(), 'updated_at' => now()->toIso8601String()]);
            DB::table('focus_group_members')->insert(['group_id' => $id, 'owner' => $o]);
        } else {
            $g = DB::table('focus_groups')->where('id', $v['id'] ?? 0)->where('company_id', $v['company_id'])->first();
            abort_unless($g, 404);
            if ($v['action'] === 'delete') {
                abort_unless($g->owner === $o, 403);
                DB::table('focus_groups')->where('id', $g->id)->delete();
            } elseif ($v['action'] === 'join') {
                DB::table('focus_group_members')->insertOrIgnore(['group_id' => $g->id, 'owner' => $o]);
            } else {
                DB::table('focus_group_members')->where('group_id', $g->id)->where('owner', $o)->delete();
            }
        }
        F::companyChanged((int) $v['company_id']);

        return ['ok' => true];
    }

    public function clear(Request $r): array
    {
        $o = F::owner($r);
        $r->validate(['confirmation' => 'required|in:DELETE MY FOCUS DATA']);
        DB::transaction(function () use ($o) {
            DB::table('focus_groups')->where('owner', $o)->delete();
            DB::table('focus_group_members')->where('owner', $o)->delete();
            foreach (['focus_notifications', 'focus_sessions', 'focus_rewards', 'focus_tasks', 'focus_projects', 'focus_preferences'] as $table) {
                DB::table($table)->where('owner', $o)->delete();
            }
        });
        F::changed($o);

        return ['ok' => true];
    }
}
