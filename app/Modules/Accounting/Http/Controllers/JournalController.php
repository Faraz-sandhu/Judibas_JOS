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
    public function index(Request $r)
    {
        $v = $r->validate(['company_id' => 'required|integer', 'id' => 'nullable|integer', 'page' => 'nullable|integer|min:1', 'status' => 'nullable|in:draft,posted,reversed', 'search' => 'nullable|string|max:150', 'vouchers'=>'sometimes|boolean', 'kind' => 'nullable|in:cash_receipt,cash_payment,bank_receipt,bank_payment,expense,income']);
        $company = (int) $v['company_id'];
        Access::company($r, $company);
        $kind = ! empty($v['id']) ? DB::table('accounting_journals')->where('company_id', $company)->where('id', $v['id'])->value('kind') : ($v['kind'] ?? 'general');
        if(!$r->boolean('vouchers')||!empty($v['id']))Permissions::require($r, Permissions::journalGroup($kind ?? 'general').'.view');
        if (! empty($v['id'])) {
            return ['journal' => Ledger::document($company, $v['id'])];
        }
        $q = DB::table('accounting_journals')->where('company_id', $company)->when($v['kind'] ?? null, fn ($q, $kind) => $q->where('kind', $kind), fn ($q) => $q->whereNotIn('kind', array_keys(VoucherTypes::TYPES)))->when($v['status'] ?? null, fn ($q, $s) => $q->where('status', $s))->when($v['search'] ?? null, fn ($q, $s) => $q->where(fn ($q) => $q->where('number', 'ilike', '%'.$s.'%')->orWhere('reference', 'ilike', '%'.$s.'%')->orWhere('remarks', 'ilike', '%'.$s.'%')->orWhere('voucher_person', 'ilike', '%'.$s.'%')));
        if($r->boolean('vouchers')){
            $allowed=array_values(array_filter(array_keys(VoucherTypes::TYPES),fn($k)=>Permissions::can($r,Permissions::journalGroup($k).'.view')));abort_unless($allowed,403);
            $q=DB::table('accounting_journals')->where('company_id',$company)->whereIn('kind',$allowed)->when($v['kind']??null,fn($q,$k)=>$q->where('kind',$k))->when($v['status']??null,fn($q,$s)=>$q->where('status',$s))->when($v['search']??null,fn($q,$s)=>$q->where(fn($q)=>$q->where('number','ilike','%'.$s.'%')->orWhere('reference','ilike','%'.$s.'%')->orWhere('voucher_person','ilike','%'.$s.'%')));
        }
        $rows = $q->orderByDesc('posting_date')->orderByDesc('id')->paginate(15);
        $rows->setCollection($rows->getCollection()->map(fn ($j) => Ledger::document($company, $j->id)));

        return $rows;
    }

    public function save(Request $r)
    {
        $v = $r->validate(['company_id' => 'required|integer', 'id' => 'nullable|integer', 'version' => [Rule::requiredIf(fn () => $r->filled('id')), 'integer', 'min:1'], 'request_key' => 'required|uuid', 'posting_date' => 'required|date_format:Y-m-d', 'currency'=>'nullable|string|size:3','exchange_rate'=>'nullable','kind' => 'required|in:general,opening,cash_receipt,cash_payment,bank_receipt,bank_payment,expense,income', 'voucher_person' => 'nullable|string|max:150', 'reference' => 'nullable|string|max:150', 'remarks' => 'nullable|string|max:2000', 'lines' => 'required|array|min:2|max:100', 'lines.*.account_id' => 'required|integer', 'lines.*.debit' => 'required', 'lines.*.credit' => 'required', 'lines.*.description' => 'nullable|string|max:250']);
        $company = (int) $v['company_id'];
        Access::company($r, $company, Permissions::journalGroup($v['kind']).($r->filled('id') ? '.update' : '.create'));
        $id = DB::transaction(function () use ($r, $v, $company) {
            Ledger::lock($company);
            $book = Ledger::book($company);
            Ledger::checkDate($book, $v['posting_date']);
            $q = DB::table('accounting_journals')->where('company_id', $company);
            $old = ! empty($v['id']) ? (clone $q)->where('id', $v['id'])->first() : null;
            abort_if(! empty($v['id']) && ! $old, 404);
            if ($old) ApprovalFlow::ensureEditable($company, 'journal', $old->id);
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
                $actual = collect($previous['lines'])->map(fn ($l) => collect($l)->only(array_merge(['company_id','account_id','position','debit_minor','credit_minor','description'],$foreign?['currency','exchange_rate','foreign_debit_minor','foreign_credit_minor']:[]))->all())->all();
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
                $number = (int) (clone $q)->count() + 1;
                $id = DB::table('accounting_journals')->insertGetId($values + ['company_id' => $company, 'request_key' => $v['request_key'], 'number' => (isset(VoucherTypes::TYPES[$v['kind']])?VoucherTypes::TYPES[$v['kind']][0].'-':'JV-').str_pad((string) $number, 7, '0', STR_PAD_LEFT), 'created_by' => $r->user()?->id, 'created_at' => now()]);
            }
            DB::table('accounting_journal_lines')->insert(array_map(fn ($l) => $l + ['journal_id' => $id], $lines));
            Access::audit($r, $company, $old ? 'Journal draft updated' : 'Journal draft created', 'Journal #'.$id);

            return $id;
        });

        return ['message' => 'Journal saved as draft. It has no ledger effect until posted.', 'journal' => Ledger::document($company, $id)];
    }

    public function post(Request $r, int $id)
    {
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
