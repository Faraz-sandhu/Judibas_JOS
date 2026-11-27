<?php

namespace Tests\Feature;

use Database\Seeders\AccountingRealisticDemoSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AccountingRealisticDemoTest extends TestCase
{
    use DatabaseTransactions;
    use \Tests\Concerns\UploadsAccountingProofFixtures;

    public function test_realistic_demo_is_balanced_and_safe_to_rerun(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $seeder = new class extends AccountingRealisticDemoSeeder {
            protected string $companyName = 'Realistic COA automated test';
        };
        $seeder->run();
        $company = DB::table('communication_companies')->where('name', 'Realistic COA automated test')->value('id');
        $accounts = DB::table('accounting_accounts')->where('company_id', $company);
        $this->assertSame(51, (clone $accounts)->count());
        $this->assertSame('01-02-02', (clone $accounts)->where('name', 'Computers and Laptops')->value('code'));
        $journals = DB::table('accounting_journals')->where('company_id', $company)->count();
        $this->assertGreaterThan(12, $journals);
        $seeder->run();
        $this->assertSame($journals, DB::table('accounting_journals')->where('company_id', $company)->count());
        $this->withSession(['judibas_admin' => true])->getJson('/accounting/api/data?company_id='.$company)
            ->assertOk()->assertJsonFragment(['name' => 'Owner Capital', 'balance' => '550000.00', 'balance_side' => 'Cr']);
    }
}
