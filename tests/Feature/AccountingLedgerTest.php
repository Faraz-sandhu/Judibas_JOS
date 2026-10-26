<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Accounting\Services\Ledger;
use App\Services\UserProductAccess;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AccountingLedgerTest extends TestCase
{
    use DatabaseTransactions;

    private int $company;
    private array $accounts;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = DB::table('communication_companies')->insertGetId(['name' => 'Ledger test company', 'created_at' => now(), 'updated_at' => now()]);
        $this->withSession(['judibas_admin' => true]);
        $this->postJson('/accounting/api/initialize', ['company_id' => $this->company])->assertOk();
        $this->accounts = DB::table('accounting_accounts')->where('company_id', $this->company)->pluck('id', 'code')->all();
        $this->postJson('/accounting/api/books', ['company_id' => $this->company, 'version' => 0, 'currency' => 'PKR', 'start_date' => now()->subYear()->toDateString()])->assertOk();
    }

    private function body(string $amount = '100.00', array $replace = []): array
    {
        return array_replace(['company_id' => $this->company, 'request_key' => (string) Str::uuid(), 'posting_date' => now()->toDateString(), 'kind' => 'general', 'remarks' => 'Cash revenue', 'reference' => 'Test reference', 'lines' => [['account_id' => $this->accounts['1110'], 'debit' => $amount, 'credit' => '0.00'], ['account_id' => $this->accounts['4100'], 'debit' => '0.00', 'credit' => $amount]]], $replace);
    }

    private function create(array $body, bool $post = true): array
    {
        $j = $this->postJson('/accounting/api/journals', $body)->assertOk()->json('journal');
        if ($post) {
            $j = $this->postJson('/accounting/api/journals/'.$j['id'].'/post', ['company_id' => $this->company, 'version' => $j['version']])->assertOk()->json('journal');
        }

        return $j;
    }

    private function report(string $type, array $replace = [])
    {
        return $this->getJson('/accounting/api/reports?'.http_build_query(array_replace(['company_id' => $this->company, 'report' => $type, 'from' => now()->subMonth()->toDateString(), 'to' => now()->toDateString()], $replace)));
    }

    public function test_draft_has_no_ledger_effect_and_posting_is_balanced_and_idempotent(): void
    {
        $body = $this->body('100.29');
        $j = $this->create($body, false);
        $this->report('profit')->assertOk()->assertJsonPath('summary.profit', '0.00');
        $this->postJson('/accounting/api/journals', $body)->assertOk()->assertJsonPath('journal.id', $j['id']);
        $this->postJson('/accounting/api/journals', array_replace($body, ['remarks' => 'Different transaction']))->assertConflict();
        foreach ([1, 2] as $n) {
            $this->postJson('/accounting/api/journals/'.$j['id'].'/post', ['company_id' => $this->company, 'version' => 1])->assertOk();
        }
        $this->assertSame(2, DB::table('accounting_journal_lines')->where('journal_id', $j['id'])->count());
        $this->report('trial')->assertOk()->assertJsonPath('summary.debit', '100.29')->assertJsonPath('summary.credit', '100.29')->assertJsonPath('summary.closing_difference', '0.00');
        $this->report('profit')->assertOk()->assertJsonPath('summary.profit', '100.29');
        $this->report('balance')->assertOk()->assertJsonPath('summary.assets', '100.29')->assertJsonPath('summary.accumulated_earnings', '100.29')->assertJsonPath('summary.balance_difference', '0.00');
    }

    public function test_rejects_invalid_precision_unbalanced_negative_both_sides_group_and_control_accounts(): void
    {
        foreach (['-1', '1.001', '1e3', '10000000000.00'] as $amount) {
            $this->postJson('/accounting/api/journals', $this->body($amount))->assertUnprocessable();
        }
        $b = $this->body();$b['lines'][1]['credit'] = '99.99';
        $this->postJson('/accounting/api/journals', $b)->assertUnprocessable();
        $b = $this->body();$b['lines'][0]['credit'] = '1.00';
        $this->postJson('/accounting/api/journals', $b)->assertUnprocessable();
        foreach (['1000', '1130', '2110'] as $code) {
            $b = $this->body();$b['lines'][0]['account_id'] = $this->accounts[$code];
            $this->postJson('/accounting/api/journals', $b)->assertUnprocessable();
        }
        $this->assertSame(0, DB::table('accounting_journals')->where('company_id', $this->company)->count());
        $b = $this->body();$b['lines'][0]['debit'] = ['invalid' => 100];
        $this->postJson('/accounting/api/journals', $b)->assertUnprocessable();
    }

    public function test_draft_versions_prevent_stale_updates_and_posting(): void
    {
        $j = $this->create($this->body(), false);
        $body = array_replace($j, ['remarks' => 'Updated draft']);
        $this->postJson('/accounting/api/journals', $body)->assertOk()->assertJsonPath('journal.version', 2);
        $this->postJson('/accounting/api/journals', $body)->assertConflict();
        $this->postJson('/accounting/api/journals/'.$j['id'].'/post', ['company_id' => $this->company, 'version' => 1])->assertConflict();
        $this->postJson('/accounting/api/journals/'.$j['id'].'/post', ['company_id' => $this->company, 'version' => 2])->assertOk();
        $this->postJson('/accounting/api/journals', array_replace($body, ['version' => 3]))->assertConflict();
    }

    public function test_reversal_keeps_original_entries_and_correctly_offsets_reports(): void
    {
        $j = $this->create($this->body('250.75'));
        $lineIds = DB::table('accounting_journal_lines')->where('journal_id', $j['id'])->pluck('id')->all();
        $body = ['company_id' => $this->company, 'version' => $j['version'], 'posting_date' => now()->toDateString(), 'reason' => 'Incorrect revenue entry'];
        $r = $this->postJson('/accounting/api/journals/'.$j['id'].'/reverse', $body)->assertOk()->json('journal');
        $this->assertSame($j['id'], $r['reversal_of']);
        $this->assertSame($lineIds, DB::table('accounting_journal_lines')->where('journal_id', $j['id'])->pluck('id')->all());
        $this->assertDatabaseHas('accounting_journals', ['id' => $j['id'], 'status' => 'reversed']);
        $this->postJson('/accounting/api/journals/'.$j['id'].'/reverse', $body)->assertConflict();
        $this->report('profit')->assertOk()->assertJsonPath('summary.profit', '0.00');
        $this->report('trial')->assertOk()->assertJsonPath('summary.debit', '501.50')->assertJsonPath('summary.credit', '501.50');
        $this->report('ledger')->assertOk()->assertJsonPath('entries.total', 4)->assertJsonPath('closing', '0.00');
    }

    public function test_company_currency_and_closed_period_are_protected(): void
    {
        $j = $this->create($this->body(), false);
        $settings = ['company_id' => $this->company, 'version' => 1, 'currency' => 'PKR', 'start_date' => now()->subYear()->toDateString(), 'locked_through' => now()->toDateString()];
        $this->postJson('/accounting/api/books', array_replace($settings, ['currency' => 'USD']))->assertUnprocessable();
        $this->postJson('/accounting/api/books', $settings)->assertOk();
        $this->postJson('/accounting/api/journals/'.$j['id'].'/post', ['company_id' => $this->company, 'version' => 1])->assertUnprocessable();
        $this->postJson('/accounting/api/journals', $this->body())->assertUnprocessable();
        $this->postJson('/accounting/api/books', array_replace($settings, ['version' => 2, 'locked_through' => null]))->assertUnprocessable();
        $this->postJson('/accounting/api/books', $settings)->assertConflict();
    }

    public function test_opening_entries_and_report_opening_balances_are_correct(): void
    {
        $body = $this->body('1000.00', ['kind' => 'opening', 'posting_date' => now()->subYear()->toDateString()]);
        $this->postJson('/accounting/api/journals', $body)->assertUnprocessable();
        $body['lines'][1]['account_id'] = $this->accounts['3100'];
        $this->create($body);
        $this->create($this->body('100.01'));
        $expense = $this->body('20.05');$expense['lines'][0]['account_id'] = $this->accounts['5110'];$expense['lines'][1]['account_id'] = $this->accounts['1110'];
        $this->create($expense);
        $this->report('ledger', ['account_id' => $this->accounts['1110']])->assertOk()->assertJsonPath('opening', '1000.00')->assertJsonPath('debit', '100.01')->assertJsonPath('credit', '20.05')->assertJsonPath('closing', '1079.96')->assertJsonPath('entries.data.1.balance', '1079.96');
        $this->report('profit')->assertOk()->assertJsonPath('summary.profit', '79.96');
        $this->report('balance')->assertOk()->assertJsonPath('summary.assets', '1079.96')->assertJsonPath('summary.equity', '1000.00')->assertJsonPath('summary.balance_difference', '0.00');
    }

    public function test_viewer_and_other_company_cannot_write_or_see_private_entries(): void
    {
        $j = $this->create($this->body());
        $other = DB::table('communication_companies')->insertGetId(['name' => 'Outside company']);
        $u = User::factory()->create(['is_active' => true]);
        UserProductAccess::sync($u, ['product_slugs' => ['accounts'], 'company_ids' => [$other], 'accounting_role' => 'accountant']);
        $this->withSession(['judibas_admin' => false])->actingAs($u);
        $this->getJson('/accounting/api/journals?company_id='.$this->company)->assertForbidden();
        $this->postJson('/accounting/api/journals/'.$j['id'].'/post', ['company_id' => $this->company, 'version' => 2])->assertForbidden();
        $this->report('trial')->assertForbidden();
        UserProductAccess::sync($u, ['product_slugs' => ['accounts'], 'company_ids' => [$this->company], 'accounting_role' => 'viewer']);
        $this->report('trial')->assertOk();
        $this->postJson('/accounting/api/journals', $this->body())->assertForbidden();
        $this->getJson('/accounting/api/audit?company_id='.$this->company)->assertForbidden();
        $this->postJson('/accounting/api/books', ['company_id' => $this->company, 'version' => 1, 'currency' => 'PKR', 'start_date' => now()->subYear()->toDateString()])->assertForbidden();
    }

    public function test_reports_export_all_rows_and_ledger_paginates(): void
    {
        for ($n = 0; $n < 16; $n++) {
            $this->create($this->body('0.10'));
        }
        $this->report('ledger')->assertOk()->assertJsonPath('entries.total', 32)->assertJsonCount(30, 'entries.data');
        $this->report('ledger', ['page' => 2])->assertOk()->assertJsonCount(2, 'entries.data');
        $csv = $this->report('ledger', ['page' => 2, 'export' => 'csv'])->assertOk()->streamedContent();
        $this->assertSame(33, count(array_filter(explode("\n", trim($csv)))));
        $this->report('profit')->assertOk()->assertJsonPath('summary.profit', '1.60');
    }

    public function test_database_prevents_mutating_posted_ledger_lines(): void
    {
        $j = $this->create($this->body());
        $this->expectException(QueryException::class);
        DB::transaction(fn () => DB::table('accounting_journal_lines')->where('journal_id', $j['id'])->update(['debit_minor' => 999]));
    }

    public function test_database_prevents_deleting_posted_document(): void
    {
        $j = $this->create($this->body());
        $this->expectException(QueryException::class);
        DB::transaction(fn () => DB::table('accounting_journals')->where('id', $j['id'])->delete());
    }

    public function test_referenced_accounts_cannot_be_deleted_or_retyped(): void
    {
        $this->create($this->body(), false);
        $a = DB::table('accounting_accounts')->find($this->accounts['1110']);
        $this->deleteJson('/accounting/api/accounts/'.$a->id, ['company_id' => $this->company, 'version' => $a->version])->assertUnprocessable();
        $this->postJson('/accounting/api/accounts', array_replace((array) $a, ['account_type' => 'Bank']))->assertUnprocessable();
    }

    public function test_minor_unit_calculations_are_exact(): void
    {
        $this->assertSame(10, Ledger::minor('0.1'));
        $this->assertSame('0.30', Ledger::money(Ledger::minor('0.10') + Ledger::minor('0.20')));
        $this->assertSame('-0.01', Ledger::money(-1));
    }
}
