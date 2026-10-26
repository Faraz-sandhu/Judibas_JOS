<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Modules\Accounting\Services\{AccountingAccess as Access, Ledger};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class JournalController
{
    public function index(Request $r)
    {
        $v = $r->validate(['company_id' => 'required|integer', 'id' => 'nullable|integer', 'page' => 'nullable|integer|min:1', 'status' => 'nullable|in:draft,posted,reversed', 'search' => 'nullable|string|max:150']);
        $company = (int) $v['company_id'];
        Access::company($r, $company);
        if (! empty($v['id'])) {
            return ['journal' => Ledger::document($company, $v['id'])];
        }
        $q = DB::table('accounting_journals')->where('company_id', $company)->when($v['status'] ?? null, fn ($q, $s) => $q->where('status', $s))->when($v['search'] ?? null, fn ($q, $s) => $q->where(fn ($q) => $q->where('number', 'ilike', '%'.$s.'%')->orWhere('reference', 'ilike', '%'.$s.'%')->orWhere('remarks', 'ilike', '%'.$s.'%')));
        $rows = $q->orderByDesc('posting_date')->orderByDesc('id')->paginate(15);
        $rows->setCollection($rows->getCollection()->map(fn ($j) => Ledger::document($company, $j->id)));

        return $rows;
    }

    public function save(Request $r)
    {
        $v = $r->validate(['company_id' => 'required|integer', 'id' => 'nullable|integer', 'version' => [Rule::requiredIf(fn () => $r->filled('id')), 'integer', 'min:1'], 'request_key' => 'required|uuid', 'posting_date' => 'required|date_format:Y-m-d', 'kind' => 'required|in:general,opening', 'reference' => 'nullable|string|max:150', 'remarks' => 'required|string|max:2000', 'lines' => 'required|array|min:2|max:100', 'lines.*.account_id' => 'required|integer', 'lines.*.debit' => 'required', 'lines.*.credit' => 'required', 'lines.*.description' => 'nullable|string|max:250']);
        $company = (int) $v['company_id'];
        Access::company($r, $company, 'manage');
        $id = DB::transaction(function () use ($r, $v, $company) {
            Ledger::lock($company);
            $book = Ledger::book($company);
            Ledger::checkDate($book, $v['posting_date']);
            $q = DB::table('accounting_journals')->where('company_id', $company);
            $old = ! empty($v['id']) ? (clone $q)->where('id', $v['id'])->first() : null;
            abort_if(! empty($v['id']) && ! $old, 404);
            $lines = Ledger::lines($company, $v['lines']);
            abort_if($v['kind'] === 'opening' && $v['posting_date'] !== $book->start_date, 422, 'Opening entries must use the books start date.');
            if ($v['kind'] === 'opening') {
                abort_if(DB::table('accounting_accounts')->whereIn('id', array_column($lines, 'account_id'))->whereIn('root_type', ['Income', 'Expense'])->exists(), 422, 'Opening balances belong to balance sheet accounts only.');
            }
            if ($old) {
                abort_unless($old->status === 'draft' && ! $old->reversal_of, 409, 'Posted entries cannot be edited. Reverse them instead.');
                abort_unless($old->version === (int) $v['version'], 409, 'This journal has changed. Close and reopen it before saving.');
                abort_unless($old->request_key === $v['request_key'], 422, 'The journal request key cannot change.');
            } elseif ($existing = (clone $q)->where('request_key', $v['request_key'])->first()) {
                $previous = Ledger::document($company, $existing->id);
                $same = $existing->posting_date === $v['posting_date'] && $existing->kind === $v['kind'] && $existing->remarks === trim($v['remarks']) && ($existing->reference ?? '') === ($v['reference'] ?? '');
                $actual = collect($previous['lines'])->map(fn ($l) => collect($l)->only(['company_id', 'account_id', 'position', 'debit_minor', 'credit_minor', 'description'])->all())->all();
                abort_unless($same && $actual === $lines, 409, 'This request key was already used for a different journal.');

                return $existing->id;
            }
            abort_if(trim($v['remarks']) === '', 422, 'Enter a journal description.');
            $values = ['posting_date' => $v['posting_date'], 'kind' => $v['kind'], 'reference' => $v['reference'] ?? null, 'remarks' => trim($v['remarks']), 'version' => $old ? $old->version + 1 : 1, 'updated_at' => now()];
            if ($old) {
                $id = $old->id;
                (clone $q)->where('id', $id)->update($values);
                DB::table('accounting_journal_lines')->where('journal_id', $id)->delete();
            } else {
                $number = (int) (clone $q)->count() + 1;
                $id = DB::table('accounting_journals')->insertGetId($values + ['company_id' => $company, 'request_key' => $v['request_key'], 'number' => 'JV-'.str_pad((string) $number, 7, '0', STR_PAD_LEFT), 'created_by' => $r->user()?->id, 'created_at' => now()]);
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
        Access::company($r, $company, 'manage');
        DB::transaction(function () use ($r, $id, $company, $v) {
            Ledger::lock($company);
            $j = DB::table('accounting_journals')->where('company_id', $company)->where('id', $id)->first();
            abort_unless($j, 404);
            if ($j->status === 'posted') {
                return;
            }
            abort_unless($j->version === (int) $v['version'], 409, 'This journal changed. Reopen it before posting.');
            Ledger::requireManual($company, $id);
            Ledger::post($r, $company, $j);
        });

        return ['message' => 'Journal posted to the ledger.', 'journal' => Ledger::document($company, $id)];
    }

    public function reverse(Request $r, int $id)
    {
        $v = $r->validate(['company_id' => 'required|integer', 'version' => 'required|integer|min:1', 'posting_date' => 'required|date_format:Y-m-d', 'reason' => 'required|string|min:5|max:2000']);
        $company = (int) $v['company_id'];
        Access::company($r, $company, 'delete');
        $reversal = DB::transaction(function () use ($r, $id, $company, $v) {
            Ledger::lock($company);
            Ledger::requireManual($company, $id);
            return Ledger::reverse($r, $company, $id, $v);
        });

        return ['message' => 'Reversal posted. Original ledger entries were preserved.', 'journal' => Ledger::document($company, $reversal)];
    }
}
