<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Modules\Accounting\Services\AccountingAccess as Access;
use App\Modules\Accounting\Services\ChequeSnapshot;
use App\Modules\Accounting\Services\Ledger;
use App\Modules\Accounting\Services\Permissions;
use App\Modules\Accounting\Services\TradeDocuments;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ChequeController
{
    private function event(Request $r, int $id, string $action, string $date, ?string $note): void
    {
        DB::table('accounting_cheque_events')->insert(['cheque_id' => $id, 'action' => $action, 'event_date' => $date, 'note' => $note, 'actor_id' => $r->user()?->id, 'created_at' => now()]);
    }

    public function index(Request $r)
    {
        $v = $r->validate(['company_id' => 'required|integer', 'direction' => 'nullable|in:received,issued', 'status' => 'nullable|in:pending,deposited,clearing,cleared,bounced,cancelled,outstanding', 'search' => 'nullable|string|max:100', 'page' => 'nullable|integer|min:1', 'from' => 'nullable|required_with:to|date_format:Y-m-d', 'to' => 'nullable|required_with:from|date_format:Y-m-d|after_or_equal:from']);
        $company = (int) $v['company_id'];
        Access::company($r, $company, 'cheques.view');
        $q = DB::table('accounting_cheques as c')->join('accounting_parties as p', 'p.id', '=', 'c.party_id')->join('accounting_invoices as i', 'i.id', '=', 'c.invoice_id')->where('c.company_id', $company)->whereNotIn('c.id',\App\Modules\Accounting\Services\AdminRecordHistory::removed('cheque',$company,$v['to']??null));
        $snapshot = isset($v['to']) ? ChequeSnapshot::at($company, $v['to']) : null;
        $summary = [];
        foreach (['issued', 'received', 'bounced', 'cleared'] as $key) {
            if ($snapshot !== null) {
                $matching = array_filter($snapshot, fn ($c) => in_array($key, ['issued', 'received']) ? ($c['direction'] === $key && in_array($c['status'], ['pending', 'deposited', 'clearing'])) : ($c['status'] === $key && $c['date'] >= $v['from']));
                $summary[$key] = ['count' => count($matching), 'amount' => Ledger::money(array_sum(array_column($matching, 'amount_minor')))];

                continue;
            }
            $query = DB::table('accounting_cheques')->where('company_id', $company);
            if (in_array($key, ['issued', 'received'])) {
                $query->where('direction', $key)->whereIn('status', ['pending', 'deposited', 'clearing']);
            } else {
                $query->where('status', $key);
            }$summary[$key] = ['count' => (clone $query)->count(), 'amount' => Ledger::money((int) $query->sum('amount_minor'))];
        }
        if ($snapshot !== null) {
            $matching = array_filter($snapshot, function ($c) use ($v) {
                if (! empty($v['direction']) && $c['direction'] !== $v['direction']) {
                    return false;
                }
                $status = $v['status'] ?? '';
                if ($status === 'outstanding') {
                    return in_array($c['status'], ['pending', 'deposited', 'clearing']);
                }
                if ($status && $c['status'] !== $status) {
                    return false;
                }

                return ! in_array($status, ['cleared', 'bounced']) || $c['date'] >= $v['from'];
            });
            $q->whereIn('c.id', array_keys($matching));
        } else {
            $q->when($v['direction'] ?? null, fn ($q, $x) => $q->where('c.direction', $x))->when($v['status'] ?? null, fn ($q, $x) => $x === 'outstanding' ? $q->whereIn('c.status', ['pending', 'deposited', 'clearing']) : $q->where('c.status', $x))->when($v['search'] ?? null, fn ($q, $x) => $q->where(fn ($q) => $q->where('c.cheque_number', 'ilike', '%'.$x.'%')->orWhere('p.name', 'ilike', '%'.$x.'%')));
        }
        if ($snapshot !== null && ! empty($v['search'])) {
            $q->where(fn ($q) => $q->where('c.cheque_number', 'ilike', '%'.$v['search'].'%')->orWhere('p.name', 'ilike', '%'.$v['search'].'%'));
        }
        $rows = $q->select('c.*', 'p.name as party_name', 'i.number as invoice_number')->orderByDesc('c.id')->paginate(15);
        $rows->getCollection()->transform(function ($c) use ($snapshot) {
            if ($snapshot !== null) {
                $c->status = $snapshot[$c->id]['status'];$c->amount_minor=$snapshot[$c->id]['amount_minor'];$c->direction=$snapshot[$c->id]['direction'];foreach($snapshot[$c->id]['record']??[] as $key=>$value)$c->$key=$value;$historicalInvoice=DB::table('accounting_invoices')->where('id',$c->invoice_id)->where('company_id',$c->company_id)->first(['number','party_name']);if($historicalInvoice){$c->invoice_number=$historicalInvoice->number;$c->party_name=$historicalInvoice->party_name;}
            }
            $c->amount = Ledger::money($c->amount_minor);
            $c->events = DB::table('accounting_cheque_events')->where('cheque_id', $c->id)->when($v['to'] ?? null, fn ($q, $to) => $q->where('event_date', '<=', $to))->orderBy('id')->get();

            return $c;
        });
        $invoices = DB::table('accounting_invoices')->where('company_id', $company)->where('status', 'posted')->orderByDesc('id')->get(['id', 'number', 'party_id', 'party_name', 'kind', 'currency']);

        return ['rows' => $rows, 'summary' => $summary, 'invoices' => $invoices, 'from' => $v['from'] ?? null, 'to' => $v['to'] ?? null, 'historical' => isset($v['to']) && $v['to'] < now()->toDateString()];
    }

    public function save(Request $r)
    {
        abort_unless(\App\Services\PlatformAccess::super($r),403,'Only Super Admin can add post-dated cheques.');
        $v = $r->validate(['company_id' => 'required|integer', 'request_key' => 'required|uuid', 'invoice_id' => 'required|integer', 'bank_account_id' => 'required|integer', 'cheque_number' => 'required|string|max:100', 'drawee_bank' => 'required|string|max:150', 'cheque_date' => 'required|date_format:Y-m-d', 'recorded_date' => 'required|date_format:Y-m-d|before_or_equal:today', 'amount' => 'required', 'notes' => 'nullable|string|max:2000']);
        $company = (int) $v['company_id'];
        Access::company($r, $company, 'cheques.create');
        DB::transaction(function () use ($r, $v, $company) {
            Ledger::lock($company);
            $old = DB::table('accounting_cheques')->where('request_key', $v['request_key'])->first();
            $amount = Ledger::minor($v['amount']);
            if ($old) {
                abort_unless($old->company_id === $company && $old->invoice_id === (int) $v['invoice_id'] && $old->bank_account_id === (int) $v['bank_account_id'] && $old->amount_minor === $amount && $old->cheque_number === trim($v['cheque_number']) && $old->cheque_date === $v['cheque_date'] && $old->drawee_bank === trim($v['drawee_bank']), 409, 'Request key already used for another cheque.');

                return;
            }
            $i = DB::table('accounting_invoices')->where('company_id', $company)->where('id', $v['invoice_id'])->where('status', 'posted')->first();
            abort_unless($i, 422, 'Select a posted invoice in this company.');
            $book = Ledger::book($company);
            abort_unless(($i->currency ?? $book->currency) === $book->currency, 422, 'PDC currently supports company base currency invoices only.');
            abort_if($v['cheque_date'] < $v['recorded_date'] || $v['recorded_date'] < $i->posting_date, 422, 'Cheque date cannot precede record date or invoice.');
            $bank = TradeDocuments::account($company, $v['bank_account_id'], ['Bank'], ['Asset']);
            abort_if($bank->currency && $bank->currency !== $book->currency, 422, 'Select a base-currency bank account.');
            $paid = (int) DB::table('accounting_payments')->where('invoice_id', $i->id)->where('status', 'posted')->sum('amount_minor');
            $reserved = (int) DB::table('accounting_cheques')->where('invoice_id', $i->id)->whereIn('status', ['pending', 'deposited', 'clearing'])->sum('amount_minor');
            abort_unless($amount > 0 && $amount <= $i->total_minor - $paid - $reserved, 422, 'Cheque exceeds unreserved invoice outstanding.');
            $direction = $i->kind === 'sale' ? 'received' : 'issued';
            abort_if(DB::table('accounting_cheques')->where(['company_id' => $company, 'direction' => $direction, 'drawee_bank' => trim($v['drawee_bank']), 'cheque_number' => trim($v['cheque_number'])])->exists(), 422, 'This cheque number is already registered for that bank.');
            $id = DB::table('accounting_cheques')->insertGetId(['company_id' => $company, 'request_key' => $v['request_key'], 'payment_key' => (string) Str::uuid(), 'invoice_id' => $i->id, 'party_id' => $i->party_id, 'bank_account_id' => $bank->id, 'direction' => $direction, 'cheque_number' => trim($v['cheque_number']), 'drawee_bank' => trim($v['drawee_bank']), 'cheque_date' => $v['cheque_date'], 'recorded_date' => $v['recorded_date'], 'amount_minor' => $amount, 'currency' => $book->currency, 'notes' => $v['notes'] ?? null, 'created_by' => $r->user()?->id, 'created_at' => now(), 'updated_at' => now()]);
            $this->event($r, $id, 'registered', $v['recorded_date'], null);
            Access::audit($r, $company, 'PDC registered', $v['cheque_number']);
        });

        return ['message' => 'Cheque registered. No bank or ledger posting yet.'];
    }

    public function action(Request $r, int $id)
    {
        $v = $r->validate(['company_id' => 'required|integer', 'version' => 'required|integer|min:1', 'action' => 'required|in:deposit,clear,bounce,cancel', 'date' => 'required|date_format:Y-m-d|before_or_equal:today', 'note' => 'required|string|min:3|max:2000']);
        $company = (int) $v['company_id'];
        Access::company($r, $company, $v['action'] === 'clear' ? 'cheques.clear' : ($v['action'] === 'bounce' ? 'cheques.bounce' : 'cheques.manage'));
        DB::transaction(function () use ($r, $v, $company, $id) {
            Ledger::lock($company);
            $c = DB::table('accounting_cheques')->where('company_id', $company)->where('id', $id)->lockForUpdate()->first();
            abort_unless($c, 404);
            abort_unless($c->version === (int) $v['version'], 409, 'Cheque changed. Refresh before continuing.');
            abort_if($v['date'] < $c->recorded_date, 422, 'Action date cannot precede registration.');
            $status = $c->status;
            if ($v['action'] === 'clear') {
                abort_unless(in_array($status, ['pending', 'deposited']), 409, 'Only an outstanding cheque can be cleared.');
                abort_if($v['date'] < $c->cheque_date, 422, 'Cheque cannot clear before its cheque date.');
                Permissions::require($r, 'payments.create');
                $child = Request::create('/accounting/api/invoices/'.$c->invoice_id.'/payments', 'POST', ['company_id' => $company, 'request_key' => $c->payment_key, 'posting_date' => $v['date'], 'amount' => Ledger::money($c->amount_minor), 'bank_account_id' => $c->bank_account_id, 'reference' => 'PDC-'.$id.' / '.$c->cheque_number, 'currency' => $c->currency, 'exchange_rate' => '1.000000']);
                if ($r->hasSession()) {
                    $child->setLaravelSession($r->session());
                }$child->setUserResolver(fn () => $r->user());
                $child->attributes->set('pdc_clearance', $c->id);
                app(TradeController::class)->payment($child, $c->invoice_id);
                $paid = DB::table('accounting_payments')->where('company_id', $company)->where('request_key', $c->payment_key)->exists();
                $status = $paid ? 'cleared' : 'clearing';
            } elseif ($v['action'] === 'deposit') {
                abort_unless($status === 'pending' && $c->direction === 'received', 409, 'Only a pending received cheque can be deposited.');
                abort_if($v['date'] < $c->cheque_date, 422, 'Deposit cannot precede cheque date.');
                $status = 'deposited';
            } elseif ($v['action'] === 'bounce' && $status === 'cleared') {
                Permissions::require($r, 'payments.reverse');
                $p = DB::table('accounting_payments')->where('company_id', $company)->where('request_key', $c->payment_key)->where('status', 'posted')->first();
                abort_unless($p, 409, 'Posted cheque payment not found.');
                $j = DB::table('accounting_journals')->find($p->journal_id);
                Ledger::reverse($r, $company, $j->id, ['version' => $j->version, 'posting_date' => $v['date'], 'reason' => 'PDC bounced: '.$v['note']]);
                DB::table('accounting_payments')->where('id', $p->id)->update(['status' => 'reversed', 'version' => $p->version + 1, 'updated_at' => now()]);
                $status = 'bounced';
            } elseif ($status === 'clearing' && $v['action'] === 'cancel') {
                $paymentRequest = DB::table('accounting_payment_requests')->where('request_key', $c->payment_key)->first();
                $approval = $paymentRequest ? DB::table('accounting_approvals')->where('document_type', 'payment')->where('document_id', $paymentRequest->id)->first() : null;
                abort_unless($paymentRequest && $approval && in_array($approval->stage, ['returned', 'withdrawn']), 409, 'Return or withdraw the payment approval before cancelling this cheque.');
                DB::table('accounting_payment_requests')->where('id', $paymentRequest->id)->update(['status' => 'cancelled', 'updated_at' => now()]);
                $status = 'cancelled';
            } else {
                abort_unless(in_array($status, ['pending', 'deposited']), 409, 'Only pending or deposited cheques can be bounced/cancelled. Resolve pending payment approval first.');
                $status = $v['action'] === 'bounce' ? 'bounced' : 'cancelled';
            }
            DB::table('accounting_cheques')->where('id', $id)->update(['status' => $status, 'version' => $c->version + 1, 'cleared_date' => $status === 'cleared' ? $v['date'] : $c->cleared_date, 'updated_at' => now()]);
            $this->event($r, $id, $v['action'], $v['date'], $v['note']);
            Access::audit($r, $company, 'PDC '.$v['action'], $c->cheque_number, ['status' => $status]);
        });

        return ['message' => 'Cheque action saved. Clearance follows the invoice payment approval policy.'];
    }
}
