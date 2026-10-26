<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Modules\Accounting\Services\{AccountingAccess as Access, Ledger, TradeDocuments as Trade};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TradeController
{
    public function parties(Request $r)
    {
        $v = $r->validate(['company_id' => 'required|integer', 'kind' => 'nullable|in:customer,supplier', 'search' => 'nullable|string|max:150', 'page' => 'nullable|integer|min:1']);
        Access::company($r, (int) $v['company_id']);

        return DB::table('accounting_parties')->where('company_id', $v['company_id'])->when($v['kind'] ?? null, fn ($q, $s) => $q->where('kind', $s))->when($v['search'] ?? null, fn ($q, $s) => $q->where('name', 'ilike', '%'.$s.'%'))->orderBy('name')->paginate(20);
    }

    public function saveParty(Request $r)
    {
        $v = $r->validate(['company_id' => 'required|integer', 'id' => 'nullable|integer', 'version' => [Rule::requiredIf(fn () => $r->filled('id')), 'integer', 'min:1'], 'kind' => 'required|in:customer,supplier', 'name' => 'required|string|max:150', 'email' => 'nullable|email|max:255', 'phone' => 'nullable|string|max:50', 'tax_number' => 'nullable|string|max:100', 'address' => 'nullable|string|max:2000', 'active' => 'required|boolean']);
        $company = (int) $v['company_id'];
        Access::company($r, $company, 'manage');
        DB::transaction(function () use ($r, $v, $company) {
            Ledger::lock($company);
            $old = isset($v['id']) ? DB::table('accounting_parties')->where('company_id', $company)->where('id', $v['id'])->first() : null;
            abort_if(isset($v['id']) && ! $old, 404);
            abort_if($old && $old->version !== (int) $v['version'], 409, 'This contact changed. Reopen it before saving.');
            abort_if($old && $old->kind !== $v['kind'], 422, 'Customer or supplier type cannot change.');
            $values = collect($v)->except(['id', 'version'])->all();
            $values['name'] = trim($values['name']);
            abort_if($values['name'] === '', 422, 'Enter a contact name.');
            $values += ['version' => ($old->version ?? 0) + 1, 'updated_at' => now()];
            $old ? DB::table('accounting_parties')->where('id', $old->id)->update($values) : DB::table('accounting_parties')->insert($values + ['created_at' => now()]);
            Access::audit($r, $company, $old ? 'Contact updated' : 'Contact created', $values['name']);
        });

        return ['message' => 'Contact saved.'];
    }

    public function invoices(Request $r)
    {
        $v = $r->validate(['company_id' => 'required|integer', 'id' => 'nullable|integer', 'kind' => 'nullable|in:sale,purchase', 'status' => 'nullable|in:draft,posted,reversed', 'page' => 'nullable|integer|min:1', 'party_id' => 'nullable|integer']);
        $company = (int) $v['company_id'];
        Access::company($r, $company);
        if (! empty($v['id'])) {
            return ['invoice' => Trade::invoice($company, $v['id'])];
        }
        $rows = DB::table('accounting_invoices')->where('company_id', $company)->when($v['kind'] ?? null, fn ($q, $s) => $q->where('kind', $s))->when($v['status'] ?? null, fn ($q, $s) => $q->where('status', $s))->when($v['party_id'] ?? null, fn ($q, $s) => $q->where('party_id', $s))->orderByDesc('posting_date')->orderByDesc('id')->paginate(15);
        $rows->setCollection($rows->getCollection()->map(fn ($i) => Trade::invoice($company, $i->id)));

        return $rows;
    }

    public function saveInvoice(Request $r)
    {
        $v = $r->validate(['company_id' => 'required|integer', 'id' => 'nullable|integer', 'version' => [Rule::requiredIf(fn () => $r->filled('id')), 'integer', 'min:1'], 'kind' => 'required|in:sale,purchase', 'request_key' => 'required|uuid', 'party_id' => 'required|integer', 'posting_date' => 'required|date_format:Y-m-d', 'due_date' => 'required|date_format:Y-m-d|after_or_equal:posting_date', 'control_account_id' => 'required|integer', 'tax_percent' => 'required', 'tax_label' => 'nullable|string|max:150', 'tax_account_id' => 'nullable|integer', 'reference' => 'nullable|string|max:150', 'notes' => 'nullable|string|max:2000', 'lines' => 'required|array|min:1|max:100', 'lines.*.description' => 'required|string|max:250', 'lines.*.quantity' => 'required', 'lines.*.rate' => 'required', 'lines.*.account_id' => 'required|integer']);
        $company = (int) $v['company_id'];
        Access::company($r, $company, 'manage');
        $id = DB::transaction(function () use ($r, $v, $company) {
            Ledger::lock($company);
            Ledger::checkDate(Ledger::book($company), $v['posting_date']);
            $sale = $v['kind'] === 'sale';
            $party = DB::table('accounting_parties')->where('company_id', $company)->where('id', $v['party_id'])->where('kind', $sale ? 'customer' : 'supplier')->where('active', true)->first();
            abort_unless($party, 422, 'Select an active customer or supplier in this company.');
            Trade::account($company, $v['control_account_id'], [$sale ? 'Receivable' : 'Payable'], [$sale ? 'Asset' : 'Liability']);
            $net = 0;$lines = [];
            foreach ($v['lines'] as $line) {
                $account = Trade::account($company, $line['account_id'], [], $sale ? ['Income'] : ['Expense', 'Asset']);
                abort_if(in_array($account->account_type, ['Receivable', 'Payable', 'Cash', 'Bank']), 422, 'Invoice lines need income, expense or non-cash asset accounts.');
                $qty = Ledger::minor($line['quantity']);$rate = Ledger::minor($line['rate']);
                abort_unless($qty > 0 && $qty <= 10000000 && $rate > 0 && $rate <= intdiv(PHP_INT_MAX - 50, $qty), 422, 'Enter a valid positive quantity and rate.');
                $total = intdiv($qty * $rate + 50, 100);
                abort_unless($total > 0 && $total <= Ledger::MAX_MINOR, 422, 'Invoice line amount is outside the supported range.');
                $net += $total;
                $lines[] = ['description' => trim($line['description']), 'quantity' => Ledger::money($qty), 'rate' => Ledger::money($rate), 'account_id' => $account->id, 'amount_minor' => $total, 'amount' => Ledger::money($total)];
            }
            $taxRate = Ledger::minor($v['tax_percent']);
            abort_if($taxRate > 10000 || $net > Ledger::MAX_MINOR, 422, 'The invoice total or tax percentage exceeds the supported limit.');
            $tax = intdiv($net * $taxRate + 5000, 10000);
            abort_if($net + $tax > Ledger::MAX_MINOR, 422, 'The invoice total exceeds the supported limit.');
            if ($taxRate > 0) {
                abort_unless(! empty($v['tax_account_id']) && trim($v['tax_label'] ?? '') !== '', 422, 'Specify the tax name and tax account.');
                $taxAccount = Trade::account($company, $v['tax_account_id'], [], [$sale ? 'Liability' : 'Asset']);
                abort_if(in_array($taxAccount->account_type, ['Receivable', 'Payable', 'Cash', 'Bank']), 422, 'Select a dedicated tax liability or input tax asset account.');
            }
            $q = DB::table('accounting_invoices')->where('company_id', $company);
            $old = isset($v['id']) ? (clone $q)->where('id', $v['id'])->first() : null;
            abort_if(isset($v['id']) && ! $old, 404);
            $values = ['party_details' => json_encode(collect((array) $party)->only(['email', 'phone', 'address', 'tax_number'])->all()), 'company_name' => DB::table('communication_companies')->where('id', $company)->value('name'), 'party_id' => $party->id, 'party_name' => $party->name, 'kind' => $v['kind'], 'posting_date' => $v['posting_date'], 'due_date' => $v['due_date'], 'control_account_id' => $v['control_account_id'], 'tax_account_id' => $taxRate ? $v['tax_account_id'] : null, 'tax_label' => $taxRate ? $v['tax_label'] : null, 'tax_basis_points' => $taxRate, 'net_minor' => $net, 'tax_minor' => $tax, 'total_minor' => $net + $tax, 'reference' => $v['reference'] ?? null, 'notes' => $v['notes'] ?? null, 'lines' => json_encode($lines)];
            if ($old) {
                abort_unless($old->status === 'draft' && $old->version === (int) $v['version'], 409, 'Only the latest draft can be changed. Reopen it.');
                abort_unless($old->request_key === $v['request_key'] && $old->kind === $v['kind'], 422, 'Invoice identity cannot change.');
            } elseif ($existing = (clone $q)->where('request_key', $v['request_key'])->first()) {
                foreach ($values as $key => $value) {
                    $same = in_array($key, ['lines', 'party_details']) ? json_decode($existing->{$key}, true) === json_decode($value, true) : ($existing->{$key} == $value);
                    abort_unless($same, 409, 'This request key belongs to a different invoice.');
                }

                return $existing->id;
            }
            $values += ['version' => ($old->version ?? 0) + 1, 'updated_at' => now()];
            if ($old) {
                $id = $old->id;(clone $q)->where('id', $id)->update($values);
            } else {
                $number = ($sale ? 'SI-' : 'PI-').str_pad((string) ((clone $q)->count() + 1), 7, '0', STR_PAD_LEFT);
                $id = DB::table('accounting_invoices')->insertGetId($values + ['company_id' => $company, 'request_key' => $v['request_key'], 'number' => $number, 'created_at' => now()]);
            }
            Access::audit($r, $company, 'Invoice draft saved', 'Invoice #'.$id);

            return $id;
        });

        return ['message' => 'Invoice saved as draft.', 'invoice' => Trade::invoice($company, $id)];
    }

    public function postInvoice(Request $r, int $id)
    {
        $v = $r->validate(['company_id' => 'required|integer', 'version' => 'required|integer|min:1']);
        $company = (int) $v['company_id'];
        Access::company($r, $company, 'manage');
        DB::transaction(function () use ($r, $company, $id, $v) {
            Ledger::lock($company);
            $i = DB::table('accounting_invoices')->where('company_id', $company)->where('id', $id)->first();
            abort_unless($i, 404);
            if ($i->status === 'posted') return;
            abort_unless($i->status === 'draft' && $i->version === (int) $v['version'], 409, 'Reopen the latest draft before posting.');
            $sale = $i->kind === 'sale';
            abort_unless(DB::table('accounting_parties')->where('id', $i->party_id)->where('active', true)->exists(), 422, 'The customer or supplier is inactive.');
            Trade::account($company, $i->control_account_id, [$sale ? 'Receivable' : 'Payable']);
            $lines = [['account_id' => $i->control_account_id, 'party_id' => $i->party_id, 'debit_minor' => $sale ? $i->total_minor : 0, 'credit_minor' => $sale ? 0 : $i->total_minor]];
            foreach (json_decode($i->lines, true) as $line) {
                Trade::account($company, $line['account_id'], [], $sale ? ['Income'] : ['Asset', 'Expense']);
                $lines[] = ['account_id' => $line['account_id'], 'description' => $line['description'], 'debit_minor' => $sale ? 0 : $line['amount_minor'], 'credit_minor' => $sale ? $line['amount_minor'] : 0];
            }
            if ($i->tax_minor > 0) {
                Trade::account($company, $i->tax_account_id, [], [$sale ? 'Liability' : 'Asset']);
                $lines[] = ['account_id' => $i->tax_account_id, 'debit_minor' => $sale ? 0 : $i->tax_minor, 'credit_minor' => $sale ? $i->tax_minor : 0];
            }
            $jid = Trade::journal($r, $company, $i->posting_date, $i->number.' - '.$i->party_name, $lines);
            DB::table('accounting_invoices')->where('id', $id)->update(['status' => 'posted', 'journal_id' => $jid, 'version' => $i->version + 1, 'updated_at' => now()]);
            Access::audit($r, $company, 'Invoice posted', $i->number);
        });

        return ['message' => 'Invoice posted.', 'invoice' => Trade::invoice($company, $id)];
    }

    public function payment(Request $r, int $id)
    {
        $v = $r->validate(['company_id' => 'required|integer', 'request_key' => 'required|uuid', 'posting_date' => 'required|date_format:Y-m-d', 'amount' => 'required', 'bank_account_id' => 'required|integer', 'reference' => 'required|string|max:150']);
        $company = (int) $v['company_id'];
        Access::company($r, $company, 'manage');
        DB::transaction(function () use ($r, $company, $id, $v) {
            Ledger::lock($company);
            $i = DB::table('accounting_invoices')->where('company_id', $company)->where('id', $id)->first();
            abort_unless($i, 404);
            $amount = Ledger::minor($v['amount']);
            if ($existing = DB::table('accounting_payments')->where('company_id', $company)->where('request_key', $v['request_key'])->first()) {
                abort_unless($existing->invoice_id === $id && $existing->amount_minor === $amount && $existing->bank_account_id === (int) $v['bank_account_id'] && $existing->posting_date === $v['posting_date'] && $existing->reference === $v['reference'], 409, 'This request key belongs to another payment.');

                return;
            }
            abort_unless($i->status === 'posted', 422, 'Payments require a posted invoice.');
            abort_if($v['posting_date'] < $i->posting_date, 422, 'Payment cannot be before the invoice date.');
            $paid = (int) DB::table('accounting_payments')->where('invoice_id', $id)->where('status', 'posted')->sum('amount_minor');
            abort_unless($amount > 0 && $amount <= $i->total_minor - $paid, 422, 'Payment must be positive and cannot exceed the outstanding balance.');
            Trade::account($company, $v['bank_account_id'], ['Cash', 'Bank'], ['Asset']);
            Trade::account($company, $i->control_account_id, [$i->kind === 'sale' ? 'Receivable' : 'Payable']);
            $sale = $i->kind === 'sale';
            $number = 'PAY-'.str_pad((string) (DB::table('accounting_payments')->where('company_id', $company)->count() + 1), 7, '0', STR_PAD_LEFT);
            $jid = Trade::journal($r, $company, $v['posting_date'], $number.' / '.$i->number.' - '.$v['reference'], [['account_id' => $v['bank_account_id'], 'debit_minor' => $sale ? $amount : 0, 'credit_minor' => $sale ? 0 : $amount], ['account_id' => $i->control_account_id, 'party_id' => $i->party_id, 'debit_minor' => $sale ? 0 : $amount, 'credit_minor' => $sale ? $amount : 0]]);
            DB::table('accounting_payments')->insert(['company_id' => $company, 'invoice_id' => $id, 'request_key' => $v['request_key'], 'number' => $number, 'posting_date' => $v['posting_date'], 'amount_minor' => $amount, 'bank_account_id' => $v['bank_account_id'], 'reference' => $v['reference'], 'journal_id' => $jid, 'created_at' => now(), 'updated_at' => now()]);
            Access::audit($r, $company, $sale ? 'Customer payment received' : 'Supplier payment made', $number, ['invoice' => $i->number, 'amount' => Ledger::money($amount)]);
        });

        return ['message' => 'Payment posted and allocated to the invoice.', 'invoice' => Trade::invoice($company, $id)];
    }

    public function reverse(Request $r, int $id)
    {
        $v = $r->validate(['company_id' => 'required|integer', 'version' => 'required|integer|min:1', 'posting_date' => 'required|date_format:Y-m-d', 'reason' => 'required|string|min:5|max:2000', 'payment_id' => 'nullable|integer']);
        $company = (int) $v['company_id'];
        Access::company($r, $company, 'delete');
        DB::transaction(function () use ($r, $v, $company, $id) {
            Ledger::lock($company);
            $i = DB::table('accounting_invoices')->where('company_id', $company)->where('id', $id)->first();
            abort_unless($i, 404);
            $payment = ! empty($v['payment_id']) ? DB::table('accounting_payments')->where('invoice_id', $id)->where('id', $v['payment_id'])->first() : null;
            abort_if(! empty($v['payment_id']) && ! $payment, 404);
            $row = $payment ?? $i;
            abort_unless($row->status === 'posted' && $row->version === (int) $v['version'], 409, 'Reopen the latest posted record before reversing it.');
            if (! $payment) abort_if(DB::table('accounting_payments')->where('invoice_id', $id)->where('status', 'posted')->exists(), 422, 'Reverse all active invoice payments before reversing this invoice.');
            $journal = DB::table('accounting_journals')->where('id', $row->journal_id)->first();
            Ledger::reverse($r, $company, $row->journal_id, array_replace($v, ['version' => $journal->version]));
            DB::table($payment ? 'accounting_payments' : 'accounting_invoices')->where('id', $row->id)->update(['status' => 'reversed', 'version' => $row->version + 1, 'updated_at' => now()]);
            Access::audit($r, $company, $payment ? 'Payment reversed' : 'Invoice reversed', $row->number, ['reason' => $v['reason']]);
        });

        return ['message' => 'Reversal recorded.', 'invoice' => Trade::invoice($company, $id)];
    }
    public function printInvoice(Request $r, int $id)
    {
        Access::role($r);
        $invoice = DB::table('accounting_invoices')->where('id', $id)->first();
        abort_unless($invoice, 404);
        Access::company($r, $invoice->company_id);
        Access::audit($r, $invoice->company_id, 'Invoice print opened', $invoice->number);

        return response()->view('accounting.invoice', ['invoice' => Trade::invoice($invoice->company_id, $id), 'book' => Ledger::book($invoice->company_id), 'party' => json_decode($invoice->party_details ?? '{}', true)])->header('Cache-Control', 'private, no-store');
    }
}
