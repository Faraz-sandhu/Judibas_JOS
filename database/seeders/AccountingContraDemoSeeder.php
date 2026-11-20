<?php
namespace Database\Seeders;

use App\Modules\Accounting\Http\Controllers\JournalController;
use Illuminate\Database\Seeder;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AccountingContraDemoSeeder extends Seeder
{
    public function run(): void
    {
        abort_unless(app()->environment(['local','testing']),403,'Local testing only.');
        $original=app('request');
        try {
            DB::transaction(function () {
                $company=DB::table('communication_companies')->where('name','Suivillion')->value('id');
                abort_unless($company,422,'Suivillion company not found.');
                $reference='TEST-CONTRA-2026-11-19';
                $existing=DB::table('accounting_journals')->where('company_id',$company)->where('reference',$reference)->first();
                if($existing){$this->command?->info('Existing sample: '.$existing->number.' ('.$existing->status.')');return;}
                $accounts=DB::table('accounting_accounts')->where('company_id',$company)->where('active',true)->where('is_group',false)->where('root_type','Asset')->whereNull('currency')->orderBy('id')->get();
                $fundAccounts=DB::table('accounting_imprest_funds')->pluck('account_id')->all();
                $accounts=$accounts->reject(fn($a)=>in_array($a->id,$fundAccounts));
                $cash=$accounts->firstWhere('account_type','Cash');
                $bank=$accounts->firstWhere('account_type','Bank');
                abort_unless($cash&&$bank,422,'A base-currency Cash and Bank account are required.');
                $r=Request::create('/accounting/api/journals','POST',[
                    'company_id'=>$company,'request_key'=>(string)Str::uuid(),'posting_date'=>now()->toDateString(),
                    'kind'=>'contra','voucher_person'=>'TEST ONLY - Return surplus cash to bank',
                    'reference'=>$reference,'remarks'=>'Fictional local test: transfer Rs 20,000 from Cash to Bank. Non-posted until approved and posted. No real payment.',
                    'lines'=>[
                        ['account_id'=>$cash->id,'debit'=>'0.00','credit'=>'20000.00','description'=>'TEST - Source cash'],
                        ['account_id'=>$bank->id,'debit'=>'20000.00','credit'=>'0.00','description'=>'TEST - Destination bank'],
                    ],
                ]);
                $session=new Store('contra-demo',new ArraySessionHandler(120));$session->put('judibas_admin',true);$r->setLaravelSession($session);
                app()->instance('request',$r);$r->setUserResolver(fn()=>null);
                $result=app(JournalController::class)->save($r);
                $this->command?->info('Suivillion: '.$result['journal']['number'].' | Non-posted | Rs 20,000 | '.$cash->name.' -> '.$bank->name);
            });
        } finally { app()->instance('request',$original); }
    }
}

