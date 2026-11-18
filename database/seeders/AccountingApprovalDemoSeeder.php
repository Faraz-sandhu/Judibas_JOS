<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Accounting\Http\Controllers\{BooksController, ChartOfAccountsController, JournalController};
use App\Modules\Accounting\Services\ApprovalFlow;
use App\Services\UserProductAccess;
use Illuminate\Database\Seeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AccountingApprovalDemoSeeder extends Seeder
{
    public const COMPANY = 'Atlas Accounts (TEST ONLY)';
    public const USERS = [
        'finance@accounts.com' => ['Atlas Finance Preparer', 'finance_officer'],
        'finance.review@accounts.com' => ['Atlas Finance Reviewer', 'finance_officer'],
        'ceo@atlasaccounts.com' => ['Atlas Company CEO', 'ceo'],
        'manager@accounts.com' => ['Atlas Finance Manager', 'senior_finance_manager'],
    ];

    private function request(array $body, ?User $user = null): Request
    {
        $request = Request::create('/accounting-demo', 'POST', $body);
        $session = app('session')->driver();
        $session->put('judibas_admin', $user === null);
        $request->setLaravelSession($session);
        $request->setUserResolver(fn () => $user);
        return $request;
    }

    public function run(): void
    {
        abort_unless(app()->environment(['local', 'testing']), 403, 'Sample accounts are restricted to local/testing.');
        if (DB::table('communication_companies')->where('name', self::COMPANY)->exists()) {
            $this->command?->info('Approval demo already exists; no accounts, passwords or entries changed.');
            return;
        }
        DB::transaction(function () {
            abort_if(User::whereIn('email', array_keys(self::USERS))->exists(), 409, 'A requested demo email already exists. Existing accounts were not changed.');
            $company = DB::table('communication_companies')->insertGetId(['name'=>self::COMPANY, 'created_at'=>now(), 'updated_at'=>now()]);
            $base = ['company_id'=>$company];
            app(ChartOfAccountsController::class)->initialize($this->request($base));
            app(BooksController::class)->save($this->request($base + ['version'=>0, 'currency'=>'PKR', 'start_date'=>now()->startOfMonth()->toDateString(), 'approval_required'=>true,'approval_types'=>array_keys(\App\Modules\Accounting\Services\ApprovalPolicy::TYPES)]));
            $users = [];
            foreach (self::USERS as $email => [$name, $role]) {
                $user = new User();
                $user->name=$name; $user->email=$email; $user->password='password';
                $user->is_active=true; $user->status=1; $user->judibas_administrator=false; $user->email_verified_at=now();
                $user->save();
                UserProductAccess::sync($user, ['product_slugs'=>['accounts'], 'company_ids'=>[$company], 'accounting_role'=>$role]);
                $users[$email]=$user;
            }
            $ids=DB::table('accounting_accounts')->where('company_id',$company)->pluck('id','code');
            foreach ([['general','2500.00','DEMO-START','Draft service income: submit this to Finance.',false], ['cash_receipt','7500.00','DEMO-REVIEW','Sample cash receipt: awaiting CEO-signed paper copy.',true]] as [$kind,$amount,$reference,$remarks,$submit]) {
                $request=$this->request($base+['request_key'=>(string)Str::uuid(),'posting_date'=>now()->toDateString(),'kind'=>$kind,'voucher_person'=>'Fictional demo customer','reference'=>$reference,'remarks'=>$remarks,'lines'=>[['account_id'=>$ids['01-01-01'],'debit'=>$amount,'credit'=>'0.00'],['account_id'=>$ids['04-01'],'debit'=>'0.00','credit'=>$amount]]],$users['finance@accounts.com']);
                $journal=app(JournalController::class)->save($request)['journal'];
                if ($submit) DB::transaction(fn () => ApprovalFlow::submit($request,$company,'journal',$journal['id'],$journal['version'],'Sample receipt prepared for CEO paper signature.'));
                $this->command?->info($journal['number'].' | '.$reference.' | '.($submit?'Awaiting CEO-signed copy':'Non-posted draft').' | PKR '.$amount);
            }
            $this->command?->info('Created '.self::COMPANY.' with four company-scoped Accounting test accounts.');
        });
    }
}
