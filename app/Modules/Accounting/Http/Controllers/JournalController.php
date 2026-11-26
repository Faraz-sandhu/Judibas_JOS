<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Modules\Accounting\Services\{AccountingAccess as Access, Ledger, Permissions, ApprovalFlow, VoucherTypes};
use App\Modules\Accounting\Services\Currency;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class JournalController
{
    public function parties(Request $r)
    {
        $company=(int)$r->validate(['company_id'=>'required|integer'])['company_id'];
        Access::company($r,$company,'journals.view');
        return ['data'=>DB::table('accounting_parties')->where('company_id',$company)->where('active',true)->orderBy('name')->get(['id','name','kind','account_id'])];
    }

    public function index(Request $r)
    {
        $v = $r->validate(['company_id' => 'required|integer', 'id' => 'nullable|integer', 'page' => 'nullable|integer|min:1', 'status' => 'nullable|in:draft,posted,reversed', 'search' => 'nullable|string|max:150', 'vouchers'=>'sometimes|boolean', 'kind' => 'nullable|in:cash_receipt,cash_payment,bank_receipt,bank_payment,expense,income,contra']);
        $company = (int) $v['company_id'];
        Access::company($r, $company);
        $kind = ! empty($v['id']) ? DB::table('accounting_journals')->where('company_id', $company)->where('id', $v['id'])->value('kind') : ($v['kind'] ?? 'general');
        if(!$r->boolean('vouchers')||!empty($v['id']))Permissions::require($r, Permissions::journalGroup($kind ?? 'general').'.view');
        if (! empty($v['id'])) {
            \App\Modules\Accounting\Services\ImprestFunds::authorizeJournal($r,$company,(int)$v['id']);
            return ['journal' => Ledger::document($company, $v['id'])];
        }
        $q = DB::table('accounting_journals')->where('company_id', $company)->when($v['kind'] ?? null, fn ($q, $kind) => $q->where('kind', $kind), fn ($q) => $q->whereNotIn('kind', array_keys(VoucherTypes::TYPES)))->when($v['status'] ?? null, fn ($q, $s) => $q->where('status', $s))->when($v['search'] ?? null, fn ($q, $s) => $q->where(fn ($q) => $q->where('number', 'ilike', '%'.$s.'%')->orWhere('reference', 'ilike', '%'.$s.'%')->orWhere('remarks', 'ilike', '%'.$s.'%')->orWhere('voucher_person', 'ilike', '%'.$s.'%')));
        if($r->boolean('vouchers')){
            $allowed=array_values(array_filter(array_keys(VoucherTypes::TYPES),fn($k)=>Permissions::can($r,Permissions::journalGroup($k).'.view')));abort_unless($allowed,403);
            $q=DB::table('accounting_journals')->where('company_id',$company)->whereIn('kind',$allowed)->when($v['kind']??null,fn($q,$k)=>$q->where('kind',$k))->when($v['status']??null,fn($q,$s)=>$q->where('status',$s))->when($v['search']??null,fn($q,$s)=>$q->where(fn($q)=>$q->where('number','ilike','%'.$s.'%')->orWhere('reference','ilike','%'.$s.'%')->orWhere('voucher_person','ilike','%'.$s.'%')));
        }
        if (\App\Modules\Accounting\Services\ImprestFunds::restricted($r)) $q->whereIn('id',DB::table('accounting_imprest_movements as m')->join('accounting_imprest_funds as f','f.id','=','m.fund_id')->where('f.company_id',$company)->where('f.user_id',$r->user()->id)->select('m.journal_id'));
        $rows = $q->orderByDesc('posting_date')->orderByDesc('id')->paginate(15);
        $rows->setCollection($rows->getCollection()->map(fn ($j) => Ledger::document($company, $j->id)));

        return $rows;
    }

    public function save(Request $r)
    {
        $v = $r->validate(['company_id' => 'required|integer', 'id' => 'nullable|integer', 'version' => [Rule::requiredIf(fn () => $r->filled('id')), 'integer', 'min:1'], 'request_key' => 'required|uuid', 'posting_date' => 'required|date_format:Y-m-d', 'currency'=>'nullable|string|size:3','exchange_rate'=>'nullable','kind' => 'required|in:general,opening,cash_receipt,cash_payment,bank_receipt,bank_payment,expense,income,contra', 'voucher_person' => 'nullable|string|max:150', 'reference' => 'nullable|string|max:150', 'remarks' => 'nullable|string|max:2000', 'lines' => 'required|array|min:2|max:100', 'lines.*.account_id' => 'required|integer', 'lines.*.party_id'=>'nullable|integer', 'lines.*.debit' => 'required', 'lines.*.credit' => 'required', 'lines.*.description' => 'nullable|string|max:250']);
        $company = (int) $v['company_id'];
        Access::company($r, $company, Permissions::journalGroup($v['kind']).($r->filled('id') ? '.update' : '.create'));
        $id = DB::transaction(function () use ($r, $v, $company) {
            Ledger::lock($company);
            $book = Ledger::book($company);
            $futureVoucher=$v['posting_date']>now()->toDateString();
            abort_if($futureVoucher&&!\App\Services\PlatformAccess::super($r),403,'Only Super Admin can create or change a post-dated voucher.');
            Ledger::checkDate($book, $v['posting_date'], $futureVoucher);
            $q = DB::table('accounting_journals')->where('company_id', $company);
            $old = ! empty($v['id']) ? (clone $q)->where('id', $v['id'])->first() : null;
            abort_if(! empty($v['id']) && ! $old, 404);
            abort_if($old&&$old->posting_date>now()->toDateString()&&!\App\Services\PlatformAccess::super($r),403,'Only Super Admin can change a post-dated voucher, including its date.');
            abort_if($old && \App\Modules\Accounting\Services\ImprestFunds::movement($old->id),422,'Imprest vouchers cannot be edited here. Cancel the non-posted voucher in Imprest / Petty Cash and create a replacement.');
            abort_if(DB::table('accounting_imprest_funds')->whereIn('account_id',array_column($v['lines'],'account_id'))->exists(),422,'Officer fund accounts can only be used through Imprest / Petty Cash.');
            if ($old) ApprovalFlow::ensureEditable($company, 'journal', $old->id);
            foreach($v['lines'] as &$inputLine){
                $account=DB::table('accounting_accounts')->where('company_id',$company)->where('id',$inputLine['account_id'])->first();
                if($account&&in_array($account->account_type,['Receivable','Payable'],true)){
                    $party=\App\Modules\Accounting\Services\PartyAccounts::forLedger($company,$account);
                    abort_if(!empty($inputLine['party_id'])&&(int)$inputLine['party_id']!==(int)$party->id,422,'This ledger account belongs to a different customer or supplier.');
                    $inputLine['party_id']=$party->id;
                }
            }
            unset($inputLine);
            $fx=Currency::context($company,$v);$foreign=$fx['currency']!==$book->currency;
            $lines = $foreign?Currency::journalLines($company,$v['lines'],$fx):Ledger::lines($company,$v['lines']);
            if(!$foreign)foreach($lines as $line){$account=DB::table('accounting_accounts')->find($line['account_id']);Currency::account($account,$book->currency,$company);}
            \App\Modules\Accounting\Services\ExpenseVoucherHours::enforce($r, $book, $v['kind'], $lines);
            Ledger::validateCashVoucher($company, $v['kind'], $lines, $v['voucher_person'] ?? null);
            abort_if($v['kind'] === 'opening' && $v['posting_date'] !== $book->start_date, 422, 'Opening entries must use the books start date.');
            if ($v['kind'] === 'opening') {
                abort_if(DB::table('accounting_accounts')->whereIn('id', array_column($lines, 'account_id'))->whereIn('root_type', ['Income', 'Expense'])->exists(), 422, 'Opening balances belong to balance sheet accounts only.');
            }
            if ($old) {
                abort_unless($old->kind === $v['kind'], 422, 'The document type cannot change.');
                abort_unless($old->status === 'draft' && ! $old->reversal_of, 409, 'Posted entries cannot be edited. Reverse them instead.');
                abort_unless($old->version === (int) $v['version'], 409, 'This journal has changed. Close and reopen it before saving.');
                abort_unless($old->request_key === $v['request_key'], 422, 'The journal request key cannot change.');
            } elseif ($existing = (clone $q)->where('request_key', $v['request_key'])->first()) {
                $previous = Ledger::document($company, $existing->id);
                $same = $existing->posting_date === $v['posting_date'] && $existing->kind === $v['kind'] && ($existing->voucher_person ?? '') === trim($v['voucher_person'] ?? '') && $existing->remarks === trim($v['remarks'] ?? '') && ($existing->reference ?? '') === ($v['reference'] ?? '');
                $actual = collect($previous['lines'])->map(fn ($l) => collect($l)->only(array_merge(['company_id','account_id','position','debit_minor','credit_minor','description'],$foreign?['currency','exchange_rate','foreign_debit_minor','foreign_credit_minor']:[],['party_id']))->all())->all();
                abort_unless($same && ($existing->currency??$book->currency)===$fx['currency'] && (float)$existing->exchange_rate===(float)$fx['exchange_rate'] && $actual == $lines, 409, 'This request key was already used for a different journal.');

                return $existing->id;
            }
            Currency::checkOutflows($company,$lines,$v['posting_date']);
            Ledger::requireCashFunds($company, $v['kind'], $v['posting_date'], $lines);
            $values = $fx+['prepared_by' => $r->user()?->id, 'voucher_person' => trim($v['voucher_person'] ?? ''), 'posting_date' => $v['posting_date'], 'kind' => $v['kind'], 'reference' => $v['reference'] ?? null, 'remarks' => trim($v['remarks'] ?? ''), 'version' => $old ? $old->version + 1 : 1, 'updated_at' => now()];
            if ($old) {
                $id = $old->id;
                (clone $q)->where('id', $id)->update($values);
                DB::table('accounting_journal_lines')->where('journal_id', $id)->delete();
            } else {
                $id = DB::table('accounting_journals')->insertGetId($values + ['company_id' => $company, 'request_key' => $v['request_key'], 'number' => \App\Modules\Accounting\Services\VoucherNumber::next($company, VoucherTypes::TYPES[$v['kind']][0]??'JV'), 'created_by' => $r->user()?->id, 'created_at' => now()]);
            }
            DB::table('accounting_journal_lines')->insert(array_map(fn ($l) => $l + ['journal_id' => $id], $lines));
            Access::audit($r, $company, $old ? 'Journal draft updated' : 'Journal draft created', 'Journal #'.$id);

            return $id;
        });

        return ['message' => 'Journal saved as draft. It has no ledger effect until posted.', 'journal' => Ledger::document($company, $id)];
    }

    public function post(Request $r, int $id)
    {
        abort_unless(\App\Services\PlatformAccess::super($r),403,'Only Super Admin can post non-posted vouchers.');
        $v = $r->validate(['company_id' => 'required|integer', 'version' => 'required|integer|min:1']);
        $company = (int) $v['company_id'];
        Access::company($r, $company);
        DB::transaction(function () use ($r, $id, $company, $v) {
            Ledger::lock($company);
            $j = DB::table('accounting_journals')->where('company_id', $company)->where('id', $id)->first();
            abort_unless($j, 404);
            Permissions::require($r, Permissions::journalGroup($j->kind).'.post');
            if ($j->status === 'posted') {
                return;
            }
            abort_unless($j->version === (int) $v['version'], 409, 'This journal changed. Reopen it before posting.');
            ApprovalFlow::requirePosting($r, $company, 'journal', $id, $j->version);
            Ledger::requireManual($company, $id);
            Ledger::post($r, $company, $j);
        });

        return ['message' => 'Journal posted to the ledger.', 'journal' => Ledger::document($company, $id)];
    }

    public function printJournal(Request $r, int $id)
    {
        $v = $r->validate(['company_id' => 'required|integer', 'version' => 'nullable|integer|min:1', 'auto_print' => 'nullable|boolean']);
        $company = (int) $v['company_id'];
        $outer = DB::transactionLevel() === 0;
        return DB::transaction(function () use ($r, $id, $v, $company, $outer) {
            if ($outer && DB::getDriverName() === 'pgsql') DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            Access::company($r, $company);
            \App\Modules\Accounting\Services\ImprestFunds::authorizeJournal($r,$company,$id);
            $journal = Ledger::document($company, $id);
            Permissions::require($r, Permissions::journalGroup($journal['kind']).'.print');
            abort_if(isset($v['version']) && (int) $v['version'] !== (int) $journal['version'], 409, 'This journal has changed. Reopen it before printing.');
            Access::audit($r, $company, 'Journal print opened', $journal['number']);
            return response()->view('accounting.journal-print', ['journal' => $journal,
                'company' => DB::table('communication_companies')->find($company), 'book' => Ledger::book($company),
                'autoPrint' => $r->boolean('auto_print')])->header('Cache-Control', 'private, no-store');
        });
    }

    public function printVoucher(Request $r, int $id)
    {
        $company = (int) $r->validate(['company_id' => 'required|integer'])['company_id'];
        Access::company($r, $company);
        \App\Modules\Accounting\Services\ImprestFunds::authorizeJournal($r,$company,$id);
        $journal = Ledger::document($company, $id);
        abort_unless(isset(VoucherTypes::TYPES[$journal['kind']]), 404);
        Permissions::require($r, Permissions::journalGroup($journal['kind']).'.print');
        Access::audit($r, $company, 'Cash voucher printed', $journal['number']);
        return response()->view('accounting.cash-voucher', ['journal' => $journal, 'company' => DB::table('communication_companies')->find($company), 'book' => Ledger::book($company)])->header('Cache-Control', 'private, no-store');
    }

    public function reverse(Request $r, int $id)
    {
        $v = $r->validate(['company_id' => 'required|integer', 'version' => 'required|integer|min:1', 'posting_date' => 'required|date_format:Y-m-d', 'reason' => 'required|string|min:5|max:2000']);
        $company = (int) $v['company_id'];
        Access::company($r, $company);
        $kind = DB::table('accounting_journals')->where('company_id', $company)->where('id', $id)->value('kind');
        Permissions::require($r, Permissions::journalGroup($kind ?? 'general').'.reverse');
        $reversal = DB::transaction(function () use ($r, $id, $company, $v) {
            Ledger::lock($company);
            Ledger::requireManual($company, $id);
            return Ledger::reverse($r, $company, $id, $v);
        });

        return ['message' => 'Reversal posted. Original ledger entries were preserved.', 'journal' => Ledger::document($company, $reversal)];
    }
}
