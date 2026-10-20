<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Daymark\Models\Report;
use App\Services\UserProductAccess;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class DaymarkProductTest extends TestCase
{
    use DatabaseTransactions;

    private int $company;

    private int $other;

    private int $department;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Event::fake();
        Mail::fake();
        $this->company = DB::table('communication_companies')->insertGetId(['name' => 'Daymark test company', 'created_at' => now(), 'updated_at' => now()]);
        $this->other = DB::table('communication_companies')->insertGetId(['name' => 'Daymark other company', 'created_at' => now(), 'updated_at' => now()]);
        $this->department = DB::table('daymark_departments')->insertGetId(['company_id' => $this->company, 'name' => 'Engineering', 'created_at' => now(), 'updated_at' => now()]);
    }

    private function member(string $role = 'employee', ?int $company = null): User
    {
        $u = User::factory()->create(['is_active' => true]);
        UserProductAccess::sync($u, ['product_slugs' => ['daymark'], 'company_ids' => [$company ?? $this->company], 'daymark_role' => $role, 'daymark_company_id' => $company ?? $this->company, 'daymark_department_id' => $company ? null : $this->department, 'daymark_designation' => 'Engineer']);

        return $u;
    }

    private function payload(array $changes = []): array
    {
        return array_replace(['date' => today()->toDateString(), 'status' => 'submitted', 'hours' => 7.5, 'completed' => 'Built feature', 'progress' => 'Tests', 'plan' => 'Release', 'blockers' => 'None'], $changes);
    }

    private function save(User $u, array $changes = []): Report
    {
        $this->actingAs($u)->postJson('/daymark/api/report', $this->payload($changes))->assertOk();

        return Report::where('user_id', $u->id)->where('date', $changes['date'] ?? today()->toDateString())->firstOrFail();
    }

    private function dataset(): TestResponse
    {
        return $this->getJson('/daymark/api/data?month='.today()->format('Y-m'));
    }

    public function test_shared_product_gate_and_central_provisioning(): void
    {
        $outsider = User::factory()->create(['is_active' => true]);
        $this->actingAs($outsider)->getJson('/daymark/api/data?month='.today()->format('Y-m'))->assertForbidden();
        $this->withSession(['judibas_admin' => true])->postJson('/admin/api/users', ['name' => 'Report user', 'email' => 'daymark-provision@example.test', 'password' => 'TestPassword123!', 'is_active' => true, 'product_slugs' => ['daymark'], 'company_ids' => [$this->company], 'daymark_company_id' => $this->company, 'daymark_role' => 'pm', 'daymark_department_id' => $this->department, 'daymark_designation' => 'Lead'])->assertOk();
        $id = User::where('email', 'daymark-provision@example.test')->value('id');
        $this->assertDatabaseHas('daymark_members', ['id' => $id, 'role' => 'pm', 'department' => 'Engineering']);
        $this->assertDatabaseHas('user_product_access', ['user_id' => $id, 'product_slug' => 'daymark']);
        $this->get('/daymark')->assertOk();
        $this->getJson('/admin/api')->assertOk()->assertJsonStructure(['daymark_departments', 'daymark_roles', 'daymark_managers']);
    }

    public function test_employee_own_reports_and_draft_privacy_even_for_super_admin(): void
    {
        $a = $this->member();
        $b = $this->member();
        $own = $this->save($a);
        $draft = $this->save($b, ['status' => 'draft']);
        $this->actingAs($a);
        $r = $this->dataset()->assertOk();
        $this->assertSame([$own->id], $r->json('reports.*.id'));
        $this->assertSame([$a->id], $r->json('users.*.id'));
        $this->postJson('/daymark/api/review', ['id' => $own->id, 'status' => 'reviewed'])->assertForbidden();
        $this->withSession(['judibas_admin' => true]);
        $r = $this->dataset()->assertOk();
        $this->assertContains($own->id, $r->json('reports.*.id'));
        $this->assertNotContains($draft->id, $r->json('reports.*.id'));
        $this->postJson('/daymark/api/report', $this->payload())->assertForbidden();
    }

    public function test_manager_assignments_and_company_leadership_scope(): void
    {
        $manager = $this->member('pm');
        $a = $this->member();
        $b = $this->member();
        $foreign = $this->member('employee', $this->other);
        $ra = $this->save($a);
        $rb = $this->save($b);
        $rf = $this->save($foreign);
        DB::table('daymark_assignments')->insert(['manager_id' => $manager->id, 'employee_id' => $a->id]);
        $this->actingAs($manager);
        $this->assertSame([$ra->id], $this->dataset()->assertOk()->json('reports.*.id'));
        $this->postJson('/daymark/api/review', ['id' => $rb->id, 'status' => 'reviewed'])->assertNotFound();
        $this->postJson('/daymark/api/review', ['id' => $rf->id, 'status' => 'reviewed'])->assertNotFound();
        foreach (['company_admin', 'ceo', 'md', 'hr'] as $role) {
            $this->actingAs($this->member($role));
            $ids = $this->dataset()->assertOk()->json('reports.*.id');
            $this->assertContains($ra->id, $ids);
            $this->assertContains($rb->id, $ids);
            $this->assertNotContains($rf->id, $ids);
        }
    }

    public function test_report_review_lifecycle_and_validation(): void
    {
        $u = $this->member();
        $manager = $this->member('om');
        DB::table('daymark_assignments')->insert(['manager_id' => $manager->id, 'employee_id' => $u->id]);
        $report = $this->save($u);
        $this->postJson('/daymark/api/report', $this->payload(['status' => 'draft']))->assertUnprocessable();
        $this->postJson('/daymark/api/report', $this->payload(['date' => today()->addDay()->toDateString()]))->assertUnprocessable();
        $this->postJson('/daymark/api/report', $this->payload(['hours' => 25]))->assertUnprocessable();
        $this->postJson('/daymark/api/report', $this->payload(['completed' => '']))->assertStatus(422);
        $this->actingAs($manager)->postJson('/daymark/api/review', ['id' => $report->id, 'status' => 'reviewed', 'feedback' => 'Good'])->assertOk();
        $this->actingAs($u)->postJson('/daymark/api/report', $this->payload())->assertStatus(422);
        $this->actingAs($manager)->postJson('/daymark/api/review', ['id' => $report->id, 'status' => 'changes_requested'])->assertStatus(422);
        $this->postJson('/daymark/api/review', ['id' => $report->id, 'status' => 'changes_requested', 'feedback' => 'Add detail'])->assertOk();
        $this->actingAs($u)->postJson('/daymark/api/report', $this->payload(['completed' => 'Updated details']))->assertOk();
        $this->assertSame(1, Report::where('user_id', $u->id)->count());
        $this->assertNotEmpty($this->dataset()->json('reports.0.sections_html.completed'));
    }

    public function test_sanitized_rich_text_and_private_own_date_lookup(): void
    {
        $u = $this->member();
        $r = $this->save($u, ['content_format' => 'html', 'completed' => '<p onclick="alert(1)"><strong>Safe</strong><script>alert(1)</script><a href="javascript:alert(1)">Link</a></p>']);
        $this->assertStringContainsString('<strong>Safe</strong>', $r->completed);
        $this->assertStringNotContainsString('script', $r->completed);
        $this->assertStringNotContainsString('onclick', $r->completed);
        $this->getJson('/daymark/api/own-report?date='.today()->toDateString())->assertOk()->assertJsonPath('report.id', $r->id);
        $this->actingAs($this->member())->getJson('/daymark/api/own-report?date='.today()->toDateString())->assertOk()->assertJsonPath('report', null);
    }

    public function test_custom_permissions_cannot_exceed_role_scope_and_take_effect(): void
    {
        $admin = $this->member('company_admin');
        $u = $this->member();
        $this->actingAs($admin)->withSession(['judibas_admin'=>true])->postJson('/daymark/api/roles', ['company_id' => $this->company, 'name' => 'Restricted employee', 'base_role' => 'employee', 'permissions' => ['view_reports', 'review_reports']])->assertStatus(422);
        $role = $this->postJson('/daymark/api/roles', ['company_id' => $this->company, 'name' => 'Read only', 'base_role' => 'employee', 'permissions' => ['view_reports']])->assertOk()->json('role.id');
        $this->postJson('/daymark/api/user', ['id' => $u->id, 'role' => 'employee', 'custom_role_id' => $role, 'department' => 'Engineering', 'department_id' => $this->department, 'designation' => 'Engineer', 'active' => true, 'manager_ids' => []])->assertOk();
        $this->actingAs($u)->withSession(['judibas_admin'=>false])->postJson('/daymark/api/report', $this->payload())->assertForbidden();
        $this->dataset()->assertOk();
        $this->getJson('/daymark/api/export?month='.today()->format('Y-m'))->assertForbidden();
    }

    public function test_shared_invitation_acceptance_and_department_replacement(): void
    {
        $this->withSession(['judibas_admin' => true]);
        $result = $this->postJson('/daymark/api/invite', ['company_id' => $this->company, 'name' => 'Daymark invite', 'email' => 'dm-invite@example.test', 'role' => 'employee', 'department_id' => $this->department, 'designation' => 'Engineer', 'manager_ids' => [], 'delivery_method' => 'manual'])->assertOk();
        $token = basename($result->json('invitation_url'));
        $this->withSession(['judibas_admin' => false])->postJson('/invitations/'.$token, ['name' => 'Daymark invite', 'password' => 'DaymarkPassword123!', 'password_confirmation' => 'DaymarkPassword123!'])->assertOk();
        $u = User::where('email', 'dm-invite@example.test')->firstOrFail();
        $this->assertDatabaseHas('daymark_members', ['id' => $u->id, 'company_id' => $this->company, 'department' => 'Engineering']);
        $this->postJson('/invitations/'.$token, [])->assertStatus(410);
        $this->withSession(['judibas_admin' => true])->postJson('/daymark/api/departments/delete', ['id' => $this->department])->assertStatus(422);
        $replacement = DB::table('daymark_departments')->insertGetId(['company_id' => $this->company, 'name' => 'Delivery', 'created_at' => now(), 'updated_at' => now()]);
        $this->postJson('/daymark/api/departments/delete', ['id' => $this->department, 'replacement_id' => $replacement])->assertOk();
        $this->assertDatabaseHas('daymark_members', ['id' => $u->id, 'department' => 'Delivery']);
    }

    public function test_exports_follow_scope_and_support_csv_excel_daily_pdf(): void
    {
        $a = $this->member();
        $b = $this->member('employee', $this->other);
        $this->save($a, ['completed' => '=FORMULA()']);
        $this->save($b, ['completed' => 'PRIVATE OTHER COMPANY']);
        $this->actingAs($a);
        $csv = $this->get('/daymark/api/export?month='.today()->format('Y-m').'&format=csv')->assertOk()->streamedContent();
        $this->assertStringContainsString("'=FORMULA()", $csv);
        $this->assertStringNotContainsString('PRIVATE OTHER COMPANY', $csv);
        $this->get('/daymark/api/export?month='.today()->format('Y-m').'&format=xlsx')->assertOk()->assertDownload();
        $this->get('/daymark/api/daily-export?date='.today()->toDateString().'&format=xlsx')->assertOk()->assertDownload();
        $this->get('/daymark/api/daily-export?date='.today()->toDateString().'&format=pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get('/daymark/api/daily-export?date='.today()->toDateString().'&format=html')->assertOk()->assertDontSee('PRIVATE OTHER COMPANY');
    }

    public function test_removed_product_inactive_account_and_company_membership_deny_access(): void
    {
        $u = $this->member();
        $this->save($u);
        DB::table('user_product_access')->where('user_id', $u->id)->delete();
        $this->dataset()->assertForbidden();
        DB::table('user_product_access')->insert(['user_id' => $u->id, 'product_slug' => 'daymark']);
        DB::table('communication_company_memberships')->where('user_id', $u->id)->delete();
        DB::table('users')->where('id', $u->id)->update(['company_id' => null]);
        $this->dataset()->assertForbidden();
    }

    public function test_access_updates_reject_foreign_managers_and_keep_historical_company(): void
    {
        $u = $this->member();
        $foreign = $this->member('pm', $this->other);
        $this->save($u);
        $this->withSession(['judibas_admin' => true]);
        $payload = ['name' => $u->name, 'email' => $u->email, 'password' => '', 'is_active' => true, 'product_slugs' => ['daymark'], 'company_ids' => [$this->company, $this->other], 'daymark_company_id' => $this->company, 'daymark_role' => 'employee', 'daymark_manager_ids' => [$foreign->id]];
        $this->postJson('/admin/api/users/'.$u->id, $payload)->assertStatus(422);
        $payload['daymark_manager_ids'] = [];
        $payload['daymark_company_id'] = $this->other;
        $this->postJson('/admin/api/users/'.$u->id, $payload)->assertStatus(422);
        $this->assertDatabaseHas('daymark_members', ['id' => $u->id, 'company_id' => $this->company]);
        $this->deleteJson('/admin/api/users/'.$u->id)->assertStatus(422);
        $this->assertDatabaseHas('users', ['id' => $u->id]);
    }

    public function test_company_admin_cannot_manage_other_companies_or_grant_central_access(): void
    {
        $admin = $this->member('company_admin');
        $foreign = $this->member('employee', $this->other);
        $this->actingAs($admin)->postJson('/daymark/api/user', ['id' => $foreign->id, 'role' => 'company_admin', 'active' => true, 'department' => 'Development', 'designation' => 'Lead', 'manager_ids' => []])->assertForbidden();
        $this->postJson('/daymark/api/departments', ['company_id' => $this->other, 'name' => 'Forbidden'])->assertForbidden();
        $this->postJson('/daymark/api/roles', ['company_id' => $this->other, 'name' => 'Forbidden', 'base_role' => 'company_admin', 'permissions' => ['view_reports']])->assertForbidden();
        $this->getJson('/admin/api')->assertForbidden();
        $this->postJson('/daymark/api/invite', ['company_id' => $this->other, 'name' => 'Foreign', 'email' => 'foreign-invite@example.test', 'role' => 'employee', 'department_id' => $this->department, 'designation' => 'Engineer', 'delivery_method' => 'manual'])->assertForbidden();
    }

    public function test_shared_existing_invitation_preserves_other_products_and_profile_deactivation_is_local(): void
    {
        $u = $this->member();
        DB::table('user_product_access')->insert(['user_id' => $u->id, 'product_slug' => 'crm']);
        $password = $u->password;
        $admin = $this->member('company_admin');
        $this->actingAs($admin)->withSession(['judibas_admin'=>true])->postJson('/daymark/api/user', ['id' => $u->id, 'role' => 'employee', 'active' => false, 'department' => 'Engineering', 'department_id' => $this->department, 'designation' => 'Engineer', 'manager_ids' => []])->assertOk();
        $this->actingAs($u)->withSession(['judibas_admin'=>false]);
        $this->dataset()->assertForbidden();
        $this->assertTrue($u->fresh()->is_active);
        $this->assertDatabaseHas('user_product_access',['user_id' => $u->id, 'product_slug' => 'crm']);
        $this->assertSame($password,$u->fresh()->password);
    }

    public function test_department_setup_is_central_and_company_admin_cannot_manage_it(): void
    {
        $admin=$this->member('company_admin');
        $this->actingAs($admin)->postJson('/daymark/api/departments',['company_id'=>$this->company,'name'=>'Blocked'])->assertForbidden();
        $this->postJson('/admin/api/daymark/departments',['company_id'=>$this->company,'name'=>'Blocked'])->assertForbidden();
        $this->withSession(['judibas_admin'=>true]);
        $first=$this->postJson('/admin/api/daymark/departments',['company_id'=>$this->company,'name'=>' Quality   Assurance '])->assertOk()->assertJsonPath('department.name','Quality Assurance')->json('department.id');
        $this->postJson('/admin/api/daymark/departments',['company_id'=>$this->company,'name'=>'Quality Assurance'])->assertOk()->assertJsonPath('department.id',$first);
        $this->assertSame(1,DB::table('daymark_departments')->where('company_id',$this->company)->where('name','Quality Assurance')->count());
        $this->withSession(['judibas_admin'=>false])->postJson('/daymark/api/invite',[])->assertForbidden();
        $this->postJson('/daymark/api/roles',[])->assertForbidden();
        $this->postJson('/daymark/api/user',[])->assertForbidden();
    }
}
