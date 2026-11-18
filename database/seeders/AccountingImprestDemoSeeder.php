<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Accounting\Http\Controllers\ApprovalController;
use App\Modules\Accounting\Http\Controllers\ImprestController;
use App\Modules\Accounting\Http\Controllers\JournalController;
use App\Modules\Accounting\Http\Controllers\SignedCopyController;
use App\Modules\Accounting\Services\ApprovalFlow;
use App\Modules\Accounting\Services\ImprestFunds;
use App\Modules\Accounting\Services\Ledger;
use App\Services\ProductCompanyAccess;
use App\Services\UserProductAccess;
use Illuminate\Database\Seeder;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AccountingImprestDemoSeeder extends Seeder
{
    public const JUNIOR = 'imprest.junior@example.test';

    public const MANAGER = 'imprest.manager@example.test';

    private function request(array $body, ?User $user = null): Request
    {
        $r = Request::create('/accounting-imprest-demo', 'POST', $body);
        $session = new Store('imprest-demo', new ArraySessionHandler(120));
        $session->put('judibas_admin', $user === null);
        $r->setLaravelSession($session);
        app()->instance('request', $r);
        $r->setUserResolver(fn () => $user);

        return $r;
    }

    private function user(string $email, string $name, string $role, int $company): User
    {
        if ($existing = User::where('email', $email)->first()) {
            abort_unless($existing->name === $name && $existing->is_active && ! $existing->judibas_administrator
                && in_array($company, ProductCompanyAccess::ids($existing->id, 'accounts'), true)
                && DB::table('accounting_members')->where('user_id', $existing->id)->value('role') === $role,
                409, 'A demo email is already used by a different account. No existing user was changed.');

            return $existing; // Never reset an existing password or access scope.
        }
        $user = new User;
        $user->forceFill(['name' => $name, 'email' => $email, 'password' => '11223344', 'is_active' => true, 'judibas_administrator' => false, 'email_verified_at' => now()]);
        $user->save();
        UserProductAccess::sync($user, ['product_slugs' => ['accounts'], 'company_ids' => [$company], 'accounting_role' => $role]);

        return $user;
    }

    private function post(array $journal, int $company, User $manager): void
    {
        if ($journal['status'] === 'posted') {
            return;
        }
        abort_unless($journal['status'] === 'draft', 409, 'An existing sample was reversed; it was not recreated.');
        if (ApprovalFlow::enabled($company, 'journal', $journal['id'])) {
            // Explicitly fictional local fixture, never a claim of a real CEO signature.
            $path = tempnam(storage_path('app'), 'imprest-test-');
            $pdf = new \Dompdf\Dompdf;
            $number = htmlspecialchars($journal['number'], ENT_QUOTES, 'UTF-8');
            $pdf->loadHtml('<h1>TEST ONLY - Simulated approval</h1><p>Voucher: '.$number.'</p><p>This is fictional local testing evidence, not a real CEO signature or payment authorisation.</p>');
            $pdf->render();
            file_put_contents($path, $pdf->output());
            try {
                $request = $this->request(['company_id' => $company, 'version' => $journal['version'], 'document_id' => $journal['id'], 'initial' => true, 'note' => 'TEST ONLY: simulated paper approval for fictional imprest sample; not a real CEO signature.']);
                $request->files->set('signed_copy', new UploadedFile($path, 'TEST-ONLY-SIMULATED-APPROVAL.pdf', 'application/pdf', UPLOAD_ERR_OK, true));
                app(SignedCopyController::class)->store($request, 0);
                $approval = ApprovalFlow::approval($company, 'journal', $journal['id']);
                app(ApprovalController::class)->action($this->request(['company_id' => $company, 'version' => $approval->version, 'action' => 'post', 'verified_signed_copy' => true, 'note' => 'TEST ONLY: finance manager confirms this simulated sample approval; no real payment.'], $manager), $approval->id);
            } finally {
                if (is_file($path)) {
                    unlink($path);
                }
            }
        } elseif (! empty($journal['imprest'])) {
            app(ImprestController::class)->post($this->request(['company_id' => $company, 'version' => $journal['version']], $manager), $journal['id']);
        } else {
            app(JournalController::class)->post($this->request(['company_id' => $company, 'version' => $journal['version']], $manager), $journal['id']);
        }
    }

    public function run(): void
    {
        abort_unless(app()->environment(['local', 'testing']), 403, 'Imprest samples are local/testing only.');
        $original = app('request');
        try {
            DB::transaction(function () {
                $company = (int) DB::table('communication_companies')->whereRaw('lower(name)=?', ['suivillion'])->value('id');
                abort_unless($company, 422, 'Create the existing Suivillion company before adding these samples.');
                Ledger::lock($company);
                $book = Ledger::book($company);
                Ledger::checkDate($book, now()->toDateString());
                abort_unless($book->currency === 'PKR', 422, 'This sample requires PKR company books.');
                $accounts = DB::table('accounting_accounts')->where('company_id', $company)->where('active', true)->get();
                $cash = $accounts->first(fn ($a) => ! $a->is_group && $a->account_type === 'Cash' && ! $a->currency && ! ImprestFunds::protectedAccount($a->id));
                $capital = $accounts->first(fn ($a) => ! $a->is_group && $a->account_type === 'Capital');
                $expense = $accounts->first(fn ($a) => ! $a->is_group && $a->root_type === 'Expense');
                $parent = $accounts->first(fn ($a) => $a->is_group && $a->name === 'Current Assets');
                abort_unless($cash && $capital && $expense && $parent, 422, 'Suivillion needs Cash, Capital, Expense and Current Assets accounts.');
                $junior = $this->user(self::JUNIOR, 'Ali Hassan (Imprest Test)', 'junior_finance_officer', $company);
                $manager = $this->user(self::MANAGER, 'Omar Farooq (Imprest Test)', 'senior_finance_manager', $company);
                $controller = app(ImprestController::class);
                $fund = DB::table('accounting_imprest_funds')->where('company_id', $company)->where('user_id', $junior->id)->first();
                if (! $fund) {
                    $id = $controller->createFund($this->request(['company_id' => $company, 'user_id' => $junior->id, 'parent_id' => $parent->id, 'name' => 'TEST - Suivillion Junior Cash']))['fund_id'];
                    $fund = DB::table('accounting_imprest_funds')->find($id);
                }
                $prefix = 'TEST-IMPREST-V1-'.$company;
                $source = DB::table('accounting_journals')->where('company_id', $company)->where('reference', $prefix.'-CAPITAL')->first();
                $sourceJournal = $source ? Ledger::document($company, $source->id) : app(JournalController::class)->save($this->request([
                    'company_id' => $company, 'request_key' => (string) Str::uuid(), 'posting_date' => now()->toDateString(), 'kind' => 'cash_receipt', 'voucher_person' => 'TEST ONLY - fictional owner capital', 'reference' => $prefix.'-CAPITAL', 'remarks' => 'TEST ONLY - fictional PKR 200,000 cash funding for the imprest demo.',
                    'lines' => [['account_id' => $cash->id, 'debit' => '200000.00', 'credit' => '0.00'], ['account_id' => $capital->id, 'debit' => '0.00', 'credit' => '200000.00']],
                ]))['journal'];
                $this->post($sourceJournal, $company, $manager);
                foreach ([['funding', '50000.00', 'ADVANCE', true], ['spending', '10000.00', 'EXPENSE-POSTED', true], ['spending', '5000.00', 'EXPENSE-DRAFT', false]] as [$type,$amount,$tag,$post]) {
                    $remarks = $prefix.'-'.$tag.' | TEST ONLY - fictional imprest sample.';
                    $existing = DB::table('accounting_journals')->where('company_id', $company)->where('remarks', $remarks)->first();
                    // A rerun preserves all samples as the user left them, including reversals or cancellations.
                    if ($existing) {
                        continue;
                    }
                    $input = ['company_id' => $company, 'fund_id' => $fund->id, 'account_id' => $type === 'funding' ? $cash->id : $expense->id, 'amount' => $amount, 'posting_date' => now()->toDateString(), 'request_key' => (string) Str::uuid(), 'person' => $type === 'funding' ? 'Ali Hassan (Imprest Test)' : 'TEST ONLY - stationery supplier', 'remarks' => $remarks];
                    $journal = $type === 'funding' ? $controller->allocate($this->request($input))['journal'] : $controller->spend($this->request($input, $junior))['journal'];
                    if ($post) {
                        $this->post($journal, $company, $manager);
                    }
                    $this->command?->info($journal['number'].' | '.$amount.' | '.($post ? 'Posted' : 'Non-posted draft'));
                }
                $this->command?->info('Suivillion: officer posted balance '.Ledger::money(ImprestFunds::balance($fund)).'; reserved '.Ledger::money(ImprestFunds::reserved($fund)).'; available '.Ledger::money(ImprestFunds::available($fund, now()->toDateString())).'.');
                $this->command?->info('New local testing logins: '.self::JUNIOR.' and '.self::MANAGER.'; password 11223344. Existing passwords are never reset.');
            });
        } finally {
            app()->instance('request',$original);
        }
    }
}
