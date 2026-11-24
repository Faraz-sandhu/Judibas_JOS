<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Focus\Events\FocusChanged;
use App\Modules\Focus\Services\FocusService as F;
use App\Services\UserProductAccess;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class FocusProductTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['broadcasting.default' => 'log']);
    }

    private function person(): User
    {
        $u = User::factory()->create(['is_active' => true]);
        DB::table('user_product_access')->insert(['user_id' => $u->id, 'product_slug' => 'focus-to-do']);

        return $u;
    }

    private function task(User $u, array $extra = []): int
    {
        return $this->actingAs($u)->postJson('/focus-to-do/api/tasks', array_replace(['title' => 'Prepare proposal', 'project_id' => null, 'notes' => 'Private note', 'priority' => 'high', 'estimate' => 2, 'due_date' => '2026-11-08', 'repeat' => 'none', 'tags' => ['work'], 'subtasks' => [['title' => 'Check totals', 'done' => false]]], $extra))->assertOk()->json('id');
    }

    public function test_access_and_personal_ownership_are_enforced(): void
    {
        $superTasks=DB::table('focus_tasks')->where('owner','super')->count();
        $this->get('/focus-to-do')->assertRedirect('/login');
        $u = User::factory()->create(['is_active' => true]);
        $this->actingAs($u)->getJson('/focus-to-do/api/data')->assertForbidden();
        $a = $this->person();
        $b = $this->person();
        $id = $this->task($a);
        $this->actingAs($b)->getJson('/focus-to-do/api/data')->assertOk()->assertJsonCount(0, 'tasks');
        $this->deleteJson('/focus-to-do/api/tasks/'.$id)->assertNotFound();
        $this->postJson('/focus-to-do/api/tasks/'.$id.'/complete', ['completed' => true])->assertNotFound();
        $this->withSession(['judibas_admin' => true])->getJson('/focus-to-do/api/data')->assertOk()->assertJsonCount($superTasks, 'tasks');
    }

    public function test_non_recurring_completion_is_explicit_idempotent_and_survives_reload(): void
    {
        $user=$this->person();$id=$this->task($user,['due_date'=>now()->toDateString(),'repeat'=>'none']);
        foreach([true,true,false,false,true] as $completed){
            $this->postJson("/focus-to-do/api/tasks/$id/complete",['completed'=>$completed])->assertOk();
            $data=$this->getJson('/focus-to-do/api/data')->assertOk()->json();
            $task=collect($data['tasks'])->firstWhere('id',$id);
            $this->assertSame($completed,(bool)$task['completed_at']);
            $this->assertCount(1,$data['tasks']);
        }
    }

    public function test_repeat_completion_is_idempotent_and_reopening_does_not_duplicate(): void
    {
        $u = $this->person();
        $id = $this->task($u, ['repeat' => 'monthly', 'due_date' => '2026-01-31']);
        foreach ([true, true, false, true] as $value) {
            $this->postJson("/focus-to-do/api/tasks/$id/complete", ['completed' => $value])->assertOk();
        }
        $this->assertSame(2, DB::table('focus_tasks')->where('owner', 'user:'.$u->id)->count());
        $this->assertDatabaseHas('focus_tasks', ['owner' => 'user:'.$u->id, 'due_date' => '2026-02-28']);
    }

    public function test_timer_pause_refresh_finish_and_break_have_exactly_once_credit(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 8)->setTime(10, 0));
        $u = $this->person();
        $id = $this->task($u);
        $o = 'user:'.$u->id;
        $s = F::settings($o);
        $s['focus_minutes'] = 1;
        DB::table('focus_preferences')->where('owner', $o)->update(['settings' => json_encode($s)]);
        $session = $this->postJson('/focus-to-do/api/timer', ['action' => 'start', 'task_id' => $id, 'mode' => 'countdown'])->assertOk()->json('timer.id');
        $this->postJson('/focus-to-do/api/timer', ['action' => 'start', 'task_id' => $id, 'mode' => 'countdown'])->assertUnprocessable();
        $this->travel(20)->seconds();
        $this->postJson('/focus-to-do/api/timer', ['action' => 'pause', 'session_id' => $session])->assertOk()->assertJsonPath('timer.elapsed_seconds', 20);
        $this->travel(100)->seconds();
        $this->getJson('/focus-to-do/api/data')->assertOk()->assertJsonPath('timer.elapsed_seconds', 20);
        $this->postJson('/focus-to-do/api/timer', ['action' => 'resume', 'session_id' => $session])->assertOk();
        $this->travel(50)->seconds();
        $this->getJson('/focus-to-do/api/data')->assertOk()->assertJsonPath('timer.phase', 'short')->assertJsonPath('timer.status', 'ready')->assertJsonPath('summary.seconds', 60);
        $this->getJson('/focus-to-do/api/data')->assertOk()->assertJsonPath('summary.seconds', 60);
        $this->assertSame(1, DB::table('focus_rewards')->where('source', $o.':session:'.$session)->count());
        $this->postJson('/focus-to-do/api/timer', ['action' => 'stop', 'session_id' => $session])->assertStatus(409);
        $this->travelBack();
    }

    public function test_manual_focus_report_and_rewards_are_separate(): void
    {
        $u = $this->person();
        $id = $this->task($u);
        $start = now()->subHours(2);
        $this->postJson('/focus-to-do/api/manual', ['task_id' => $id, 'started_at' => $start->toIso8601String(), 'minutes' => 30])->assertOk();
        $report = F::report('user:'.$u->id, F::settings('user:'.$u->id), $start->toDateString(), now()->addDay()->toDateString());
        $this->assertSame(1800, $report['seconds']);
        $this->assertSame(0, DB::table('focus_rewards')->where('owner', 'user:'.$u->id)->count());
        $this->postJson('/focus-to-do/api/manual', ['task_id' => $id, 'started_at' => now()->toIso8601String(), 'minutes' => 30])->assertUnprocessable();
    }

    public function test_foreign_project_injection_and_reminders_are_blocked_or_idempotent(): void
    {
        $a = $this->person();
        $b = $this->person();
        $this->actingAs($a);
        $p = $this->postJson('/focus-to-do/api/projects', ['name' => 'Private', 'color' => '#7762ee', 'is_folder' => false, 'folder_id' => null])->assertOk()->json('id');
        $this->actingAs($b)->postJson('/focus-to-do/api/tasks', ['title' => 'Bad', 'project_id' => $p, 'priority' => 'none', 'estimate' => 1, 'repeat' => 'none', 'tags' => [], 'subtasks' => []])->assertUnprocessable();
        $id = $this->task($a, ['remind_at' => now()->subMinute()->toIso8601String()]);
        F::reminders();
        F::reminders();
        $this->assertSame(1, DB::table('focus_notifications')->where('task_id', $id)->count());
        $this->actingAs($b)->postJson('/focus-to-do/api/realtime/auth', ['socket_id' => '123.456', 'channel_name' => 'private-focus.user.'.$a->id])->assertForbidden();
    }

    public function test_company_scoped_social_and_focus_only_deletion(): void
    {
        $u = $this->person();
        $id = $this->task($u);
        $this->getJson('/focus-to-do/api/social?company_id=999999')->assertForbidden();
        $this->postJson('/focus-to-do/api/clear', ['confirmation' => 'yes'])->assertUnprocessable();
        $this->postJson('/focus-to-do/api/clear', ['confirmation' => 'DELETE MY FOCUS DATA'])->assertOk();
        $this->assertDatabaseMissing('focus_tasks', ['id' => $id]);
        $this->assertDatabaseHas('users', ['id' => $u->id]);
        $this->assertDatabaseHas('user_product_access', ['user_id' => $u->id, 'product_slug' => 'focus-to-do']);
    }

    public function test_groups_and_rankings_never_cross_company_or_reveal_tasks(): void
    {
        $a = $this->person();
        $b = $this->person();
        $outsider = $this->person();
        $company = DB::table('communication_companies')->insertGetId(['name' => 'Focus isolated test company', 'created_at' => now(), 'updated_at' => now()]);
        foreach ([$a, $b] as $user) {
            DB::table('users')->where('id', $user->id)->update(['company_id' => $company]);
            $user->refresh();
        }
        $this->actingAs($a)->postJson('/focus-to-do/api/groups', ['company_id' => $company, 'action' => 'create', 'name' => 'Morning focus'])->assertOk();
        $id = DB::table('focus_groups')->where('company_id', $company)->value('id');
        $this->actingAs($b)->postJson('/focus-to-do/api/groups', ['company_id' => $company, 'action' => 'join', 'id' => $id])->assertOk();
        $this->postJson('/focus-to-do/api/groups', ['company_id' => $company, 'action' => 'join', 'id' => $id])->assertOk();
        $this->assertSame(2, DB::table('focus_group_members')->where('group_id', $id)->count());
        $this->getJson('/focus-to-do/api/social?company_id='.$company)->assertOk()->assertJsonCount(2, 'rankings')->assertJsonCount(2, 'groups.0.members')->assertJsonMissingPath('tasks');
        $this->postJson('/focus-to-do/api/groups', ['company_id' => $company, 'action' => 'delete', 'id' => $id])->assertForbidden();
        $this->actingAs($outsider)->getJson('/focus-to-do/api/social?company_id='.$company)->assertForbidden();
        DB::table('users')->where('id', $a->id)->update(['is_active' => false]);
        $this->actingAs($b)->getJson('/focus-to-do/api/social?company_id='.$company)->assertOk()->assertJsonCount(1, 'rankings');
    }

    public function test_focus_reports_split_midnight_in_the_users_timezone(): void
    {
        $u = $this->person();
        $id = $this->task($u);
        $this->travelTo(Carbon::parse('2026-11-09T02:00:00+05:00'));
        $this->postJson('/focus-to-do/api/manual', ['task_id' => $id, 'started_at' => '2026-11-08T23:50:00+05:00', 'minutes' => 30])->assertOk();
        $this->getJson('/focus-to-do/api/report?from=2026-11-08&to=2026-11-09')->assertOk()->assertJsonPath('seconds', 1800)->assertJsonPath('daily.2026-11-08', 600)->assertJsonPath('daily.2026-11-09', 1200);
        $this->getJson('/focus-to-do/api/records?from=2026-11-08&to=2026-11-09')->assertOk()->assertJsonPath('total', 1);
        $this->travelBack();
    }

    public function test_stopwatch_and_reset_do_not_duplicate_focus_credit(): void
    {
        $this->travelTo(now()->setTime(10, 0));
        $u = $this->person();
        $id = $this->task($u);
        $session = $this->postJson('/focus-to-do/api/timer', ['action' => 'start', 'task_id' => $id, 'mode' => 'stopwatch'])->assertOk()->json('timer.id');
        $this->travel(40)->seconds();
        $this->postJson('/focus-to-do/api/timer', ['action' => 'reset', 'session_id' => $session])->assertOk()->assertJsonPath('timer.status', 'ready');
        $session = DB::table('focus_sessions')->where('active_owner', 'user:'.$u->id)->value('id');
        $this->postJson('/focus-to-do/api/timer', ['action' => 'resume', 'session_id' => $session])->assertOk();
        $this->travel(60)->seconds();
        $this->postJson('/focus-to-do/api/timer', ['action' => 'stop', 'session_id' => $session])->assertOk();
        $this->getJson('/focus-to-do/api/data')->assertOk()->assertJsonPath('summary.seconds', 60);
        $this->postJson('/focus-to-do/api/timer', ['action' => 'stop', 'session_id' => $session])->assertOk();
        $this->getJson('/focus-to-do/api/data')->assertOk()->assertJsonPath('summary.seconds', 60);
        $this->travelBack();
    }

    public function test_project_folders_and_deletion_preserve_task_history(): void
    {
        $u = $this->person();
        $this->actingAs($u);
        $folder = $this->postJson('/focus-to-do/api/projects', ['name' => 'Work', 'color' => '#7762ee', 'is_folder' => true])->assertOk()->json('id');
        $project = $this->postJson('/focus-to-do/api/projects', ['name' => 'Launch', 'color' => '#7762ee', 'is_folder' => false, 'folder_id' => $folder])->assertOk()->json('id');
        $id = $this->task($u, ['project_id' => $project]);
        $this->deleteJson('/focus-to-do/api/projects/'.$folder)->assertOk();
        $this->assertDatabaseHas('focus_projects', ['id' => $project, 'folder_id' => null]);
        $this->deleteJson('/focus-to-do/api/projects/'.$project)->assertOk();
        $this->assertDatabaseHas('focus_tasks', ['id' => $id, 'project_id' => null]);
        $u->forceFill(['is_active' => false])->save();
        $this->getJson('/focus-to-do/api/data')->assertForbidden();
    }

    public function test_realtime_auth_uses_product_access_and_own_channels_for_both_providers(): void
    {
        $u = $this->person();
        $this->actingAs($u);
        $body = ['socket_id' => '123.456', 'channel_name' => 'private-focus.user.'.$u->id];
        foreach (['reverb', 'pusher'] as $provider) {
            config(['broadcasting.default' => $provider, 'broadcasting.connections.'.$provider => ['driver' => $provider, 'key' => 'test-key', 'secret' => 'test-secret', 'app_id' => 'test-app', 'options' => ['cluster' => 'mt1', 'host' => '127.0.0.1', 'port' => 8080, 'scheme' => 'http', 'useTLS' => false]]]);
            Broadcast::forgetDrivers();
            $this->postJson('/focus-to-do/api/realtime/auth', $body)->assertOk()->assertJsonStructure(['auth']);
        }
        $this->postJson('/focus-to-do/api/realtime/auth', ['socket_id' => '123.456', 'channel_name' => 'private-focus.super'])->assertForbidden();
        $config = $this->getJson('/focus-to-do/api/realtime/config')->assertOk()->json();
        $this->assertSame('focus.user.'.$u->id, $config['channel']);
        $this->assertArrayNotHasKey('secret', $config);
        $this->withSession(['judibas_admin' => true])->postJson('/focus-to-do/api/realtime/auth', ['socket_id' => '123.456', 'channel_name' => 'private-focus.super'])->assertOk();
        $this->postJson('/focus-to-do/api/realtime/auth', $body)->assertForbidden();
        $this->withSession(['judibas_admin' => false]);
        DB::table('user_product_access')->where('user_id', $u->id)->where('product_slug', 'focus-to-do')->delete();
        $this->postJson('/focus-to-do/api/realtime/auth', $body)->assertForbidden();
    }

    public function test_central_access_assignment_and_social_events_preserve_private_content(): void
    {
        Event::fake([FocusChanged::class]);
        $u = User::factory()->create(['is_active' => true]);
        UserProductAccess::sync($u, ['product_slugs' => ['focus-to-do'], 'company_ids' => []]);
        $this->actingAs($u)->get('/focus-to-do')->assertOk();
        $this->task($u);
        Event::assertDispatched(FocusChanged::class, function ($e) use ($u) {
            $this->assertSame(['changed' => true], $e->broadcastWith());

            return $e->broadcastOn()[0]->name === 'private-focus.user.'.$u->id;
        });
        $this->assertDatabaseMissing('user_product_access',['user_id' => $u->id, 'product_slug' => 'communication']);
    }
}
