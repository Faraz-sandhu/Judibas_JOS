<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Accounting\Services\Ledger;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LiveTestingSetupTest extends TestCase
{
    use DatabaseTransactions;

    public function test_setup_is_repeat_safe_and_accounts_use_shared_access(): void
    {
        $this->artisan('judibas:prepare-testing', ['--apply' => true])->assertExitCode(0);
        $users = DB::table('users')->where('email', 'like', 'judibas.test.%@example.test')->count();
        $this->assertSame(8, $users);
        $items = DB::table('canteen_items')->where('name', 'like', '% (Test)')->count();
        $this->artisan('judibas:prepare-testing', ['--apply' => true])->assertExitCode(0);
        $this->assertSame($users, DB::table('users')->where('email', 'like', 'judibas.test.%@example.test')->count());
        $this->assertSame($items, DB::table('canteen_items')->where('name', 'like', '% (Test)')->count());
        $user = User::where('email', 'judibas.test.employee@example.test')->firstOrFail();
        $this->assertTrue(Hash::check('11223344', $user->password));
    }

    public function test_company_policy_never_changes_another_company(): void
    {
        $this->withSession(['judibas_admin' => true]);
        $ids = [];
        foreach (['Policy A', 'Policy B'] as $name) {
            $id = DB::table('communication_companies')->insertGetId(['name' => $name]);
            $ids[] = $id;
            $this->postJson('/accounting/api/books', ['company_id' => $id, 'version' => 0, 'currency' => 'PKR', 'start_date' => today()->toDateString()])->assertOk();
        }
        $before = DB::table('accounting_books')->where('company_id', $ids[1])->first();
        $this->postJson('/accounting/api/books', ['company_id' => $ids[0], 'version' => 1, 'currency' => 'PKR', 'start_date' => today()->toDateString(), 'approval_required' => true, 'approval_types' => ['general'], 'expense_open_time' => '10:00', 'expense_close_time' => '18:00'])->assertOk();
        $after = DB::table('accounting_books')->where('company_id', $ids[1])->first();
        $this->assertEquals($before, $after);
        $this->assertSame('10:00', Ledger::book($ids[0])->expense_open_time);
    }
}
