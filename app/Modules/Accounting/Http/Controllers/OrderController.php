<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Modules\Accounting\Services\AccountingAccess as Access;
use App\Modules\Accounting\Services\Ledger;
use App\Modules\Accounting\Services\Permissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderController
{
    private function group(string $kind): string
    {
        return $kind === 'sale' ? 'sales_orders' : 'purchase_orders';
    }

    private function both(Request $r, object $order, string $action): void
    {
        Access::company($r, $order->seller_company_id, 'sales_orders.'.$action);
        Access::company($r, $order->buyer_company_id, 'purchase_orders.'.$action);
    }

    private function locks(object $o): void
    {
        $ids = [$o->seller_company_id, $o->buyer_company_id];
        sort($ids);
        foreach ($ids as $id) {
            Ledger::lock($id);
        }
    }

    private function query(int $company, string $kind)
    {
        return DB::table('accounting_intercompany_orders')->where($kind === 'sale' ? 'seller_company_id' : 'buyer_company_id', $company);
    }

    private function data(Request $r, object $o, int $company, string $kind): array
    {
        $other = $kind === 'sale' ? $o->buyer_company_id : $o->seller_company_id;
        $invoiceId = $kind === 'sale' ? $o->sales_invoice_id : $o->purchase_invoice_id;
        $ids = Access::companies($r)->pluck('id')->all();

        return ['id' => $o->id, 'number' => ($kind === 'sale' ? 'SO-' : 'PO-').str_pad((string) $o->id, 7, '0', STR_PAD_LEFT), 'reference' => 'IC-'.str_pad((string) $o->id, 7, '0', STR_PAD_LEFT), 'kind' => $kind, 'company_id' => $company, 'seller_company_id' => $o->seller_company_id, 'buyer_company_id' => $o->buyer_company_id, 'counterparty' => DB::table('communication_companies')->where('id', $other)->value('name'), 'party_id' => $kind === 'sale' ? $o->customer_id : $o->supplier_id, 'order_date' => $o->order_date, 'due_date' => $o->due_date, 'currency' => $o->currency, 'status' => $o->status, 'version' => $o->version, 'lines' => json_decode($o->lines, true), 'quoted' => Ledger::money($o->quoted_minor), 'total' => Ledger::money($o->total_minor), 'discount' => Ledger::money($o->quoted_minor - $o->total_minor), 'terms' => $o->terms, 'invoice_id' => Permissions::can($r, ($kind === 'sale' ? 'sales' : 'purchases').'.view') ? $invoiceId : null, 'invoice_status' => $invoiceId ? DB::table('accounting_invoices')->where('id', $invoiceId)->value('status') : null, 'can_manage_pair' => in_array($o->seller_company_id, $ids) && in_array($o->buyer_company_id, $ids)];
    }

    public function index(Request $r)
    {
        $v = $r->validate(['company_id' => 'required|integer', 'kind' => 'required|in:sale,purchase', 'status' => 'nullable|in:draft,confirmed,cancelled,invoiced', 'id' => 'nullable|integer', 'party_id' => 'nullable|integer', 'search' => 'nullable|string|max:150', 'page' => 'nullable|integer|min:1']);
        $c = (int) $v['company_id'];
        $kind = $v['kind'];
        Access::company($r, $c, $this->group($kind).'.view');
        $q = $this->query($c, $kind);
        if ($r->filled('id')) {
            return ['order' => $this->data($r, $q->where('id', $v['id'])->firstOrFail(), $c, $kind)];
        }
        $q->when($v['status'] ?? null, fn ($q, $s) => $q->where('status', $s))->when($v['party_id'] ?? null, fn ($q, $id) => $q->where($kind === 'sale' ? 'customer_id' : 'supplier_id', $id));
        if ($r->filled('search')) {
            $s = $v['search'];
            $q->where(function ($q) use ($s, $kind) {
                $q->where('terms', 'ilike', '%'.$s.'%')->orWhereIn($kind === 'sale' ? 'buyer_company_id' : 'seller_company_id', DB::table('communication_companies')->where('name', 'ilike', '%'.$s.'%')->select('id'));
                if (preg_match('/^(?:PO-|SO-|IC-)?0*(\d+)$/i', $s, $m)) {
                    $q->orWhere('id', (int) $m[1]);
                }
            });
        }
        $page = $q->orderByDesc('id')->paginate(15);
        $page->setCollection($page->getCollection()->map(fn ($o) => $this->data($r, $o, $c, $kind)));

        return $page;
    }

    public function options(Request $r)
    {
        $v = $r->validate(['company_id' => 'required|integer', 'kind' => 'required|in:sale,purchase']);
        Access::company($r, (int) $v['company_id'], $this->group($v['kind']).'.view');
        $companies = Access::companies($r)->map(function ($c) use ($r) {
            $book = DB::table('accounting_books')->where('company_id', $c->id)->first();

            return ['id' => $c->id, 'name' => $c->name, 'currency' => $book?->currency, 'accounts' => Permissions::can($r, 'sales.create') && Permissions::can($r, 'purchases.create') ? DB::table('accounting_accounts')->where('company_id', $c->id)->where('active', true)->where('is_group', false)->get(['id', 'code', 'name', 'root_type', 'account_type']) : []];
        });

        return ['companies' => $companies];
    }

    private function party(Request $r, int $company, int $other, string $kind): int
    {
        $map = DB::table('accounting_company_parties')->where(['company_id' => $company, 'counterparty_company_id' => $other, 'kind' => $kind])->first();
        if ($map) {
            $party = DB::table('accounting_parties')->where('id', $map->party_id)->where('company_id', $company)->where('kind', $kind)->where('active', true)->first();
            abort_unless($party, 422, 'The linked customer/supplier is inactive or invalid.');

            return $party->id;
        }
        Permissions::require($r, ($kind === 'customer' ? 'customers' : 'suppliers').'.create');
        $name = DB::table('communication_companies')->where('id', $other)->value('name');
        // Never silently attach an existing same-name contact to a legal company.
        $id = DB::table('accounting_parties')->insertGetId(['company_id' => $company, 'kind' => $kind, 'name' => $name, 'active' => true, 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('accounting_company_parties')->insert(['company_id' => $company, 'counterparty_company_id' => $other, 'kind' => $kind, 'party_id' => $id]);
        Access::audit($r, $company, 'Intercompany contact created', $name, ['party_id' => $id, 'counterparty_company_id' => $other, 'kind' => $kind]);

        return $id;
    }

    public function save(Request $r)
    {
        $v = $r->validate(['seller_company_id' => 'required|integer|different:buyer_company_id', 'buyer_company_id' => 'required|integer', 'request_key' => 'required|uuid', 'order_date' => 'required|date_format:Y-m-d', 'due_date' => 'required|date_format:Y-m-d|after_or_equal:order_date', 'terms' => 'nullable|string|max:2000', 'lines' => 'required|array|min:1|max:100', 'lines.*.description' => 'required|string|max:250', 'lines.*.quantity' => 'required', 'lines.*.quoted_rate' => 'required', 'lines.*.rate' => 'required']);
        $o = (object) ['seller_company_id' => (int) $v['seller_company_id'], 'buyer_company_id' => (int) $v['buyer_company_id']];
        $this->both($r, $o, 'create');
        $hash = hash('sha256', json_encode($v));
        $id = DB::transaction(function () use ($r, $v, $o, $hash) {
            $this->locks($o);
            $existing = DB::table('accounting_intercompany_orders')->where('request_key', $v['request_key'])->first();
            if ($existing) {
                abort_unless(hash_equals($existing->payload_hash, $hash), 409, 'This request key was already used for different order details.');

                return $existing->id;
            }
            $seller = Ledger::book($o->seller_company_id);
            $buyer = Ledger::book($o->buyer_company_id);
            abort_unless($seller->currency === $buyer->currency, 422, 'Both companies must use the same base currency for this linked-order flow.');
            $lines = [];
            $net = 0;
            $quote = 0;
            foreach ($v['lines'] as $l) {
                $qty = Ledger::minor($l['quantity']);
                $rate = Ledger::minor($l['rate']);
                $quoted = Ledger::minor($l['quoted_rate']);
                abort_unless(trim($l['description']) !== '' && $qty > 0 && $qty <= 10000000 && $rate > 0 && $quoted >= $rate && $quoted <= intdiv(PHP_INT_MAX - 50, $qty), 422, 'Enter a description, positive quantity/rate, and quoted rate at least equal to the agreed rate.');
                $total = intdiv($qty * $rate + 50, 100);
                $qt = intdiv($qty * $quoted + 50, 100);
                abort_unless($total > 0 && $qt <= Ledger::MAX_MINOR, 422, 'Order amount is outside the supported range.');
                $net += $total;
                $quote += $qt;
                $lines[] = ['description' => trim($l['description']), 'quantity' => Ledger::money($qty), 'rate' => Ledger::money($rate), 'quoted_rate' => Ledger::money($quoted), 'amount' => Ledger::money($total), 'amount_minor' => $total];
            }
            abort_if($quote > Ledger::MAX_MINOR || $net > Ledger::MAX_MINOR, 422, 'Order total exceeds the supported limit.');
            $customer = $this->party($r, $o->seller_company_id, $o->buyer_company_id, 'customer');
            $supplier = $this->party($r, $o->buyer_company_id, $o->seller_company_id, 'supplier');
            $id = DB::table('accounting_intercompany_orders')->insertGetId(['request_key' => $v['request_key'], 'seller_company_id' => $o->seller_company_id, 'buyer_company_id' => $o->buyer_company_id, 'customer_id' => $customer, 'supplier_id' => $supplier, 'order_date' => $v['order_date'], 'due_date' => $v['due_date'], 'currency' => $seller->currency, 'lines' => json_encode($lines), 'quoted_minor' => $quote, 'total_minor' => $net, 'terms' => $v['terms'] ?? null, 'payload_hash' => $hash, 'created_by' => $r->user()?->id, 'created_at' => now(), 'updated_at' => now()]);
            foreach ([$o->seller_company_id, $o->buyer_company_id] as $c) {
                Access::audit($r, $c, 'Linked order created', 'IC-'.$id, ['order_id' => $id]);
            }

return $id;
        });

        return ['message' => 'Linked purchase and sales orders created. No ledger entries were posted.', 'id' => $id];
    }

    public function action(Request $r, int $id)
    {
        $v = $r->validate(['company_id' => 'required|integer', 'kind' => 'required|in:sale,purchase', 'version' => 'required|integer|min:1', 'action' => 'required|in:confirm,cancel']);
        $c = (int) $v['company_id'];
        Access::company($r, $c, $this->group($v['kind']).'.view');
        DB::transaction(function () use ($r, $id, $v, $c) {
            $o = $this->query($c, $v['kind'])->where('id', $id)->firstOrFail();
            $this->locks($o);
            $o = DB::table('accounting_intercompany_orders')->where('id', $id)->lockForUpdate()->first();
            $this->both($r, $o, $v['action']);
            abort_unless($o->version === (int) $v['version'], 409, 'Order changed. Reopen the latest version.');
            abort_if($o->sales_invoice_id || $o->purchase_invoice_id, 422, 'Invoice drafts already exist. Manage their financial lifecycle from Invoices.');
            abort_unless($v['action'] === 'confirm' ? $o->status === 'draft' : in_array($o->status, ['draft', 'confirmed']), 422, 'This order cannot move to the requested status.');
            DB::table('accounting_intercompany_orders')->where('id', $id)->update(['status' => $v['action'] === 'confirm' ? 'confirmed' : 'cancelled', 'version' => $o->version + 1, 'updated_at' => now()]);
            foreach ([$o->seller_company_id, $o->buyer_company_id] as $company) {
                Access::audit($r, $company, 'Linked order '.$v['action'], 'IC-'.$id, ['order_id' => $id]);
            }
        });

        return ['message' => 'Both order sides updated.'];
    }

    public function invoices(Request $r, int $id)
    {
        $v = $r->validate(['company_id' => 'required|integer', 'kind' => 'required|in:sale,purchase', 'version' => 'required|integer|min:1', 'posting_date' => 'required|date_format:Y-m-d', 'due_date' => 'required|date_format:Y-m-d|after_or_equal:posting_date', 'receivable_account_id' => 'required|integer', 'income_account_id' => 'required|integer', 'payable_account_id' => 'required|integer', 'cost_account_id' => 'required|integer']);
        $c = (int) $v['company_id'];
        Access::company($r, $c, $this->group($v['kind']).'.view');
        DB::transaction(function () use ($r, $id, $v, $c) {
            $o = $this->query($c, $v['kind'])->where('id', $id)->firstOrFail();
            $this->locks($o);
            $o = DB::table('accounting_intercompany_orders')->where('id', $id)->lockForUpdate()->first();
            $this->both($r, $o, 'convert');
            Access::company($r, $o->seller_company_id, 'sales.create');
            Access::company($r, $o->buyer_company_id, 'purchases.create');
            if ($o->sales_invoice_id && $o->purchase_invoice_id) {
                return;
            }abort_unless($o->status === 'confirmed' && $o->version === (int) $v['version'], 409, 'Confirm and reopen the latest order before creating invoices.');
            abort_unless(Ledger::book($o->seller_company_id)->currency === $o->currency && Ledger::book($o->buyer_company_id)->currency === $o->currency, 422, 'The company currencies changed.');
            $ids = [];
            foreach (['sale', 'purchase'] as $kind) {
                $sale = $kind === 'sale';
                $payload = ['company_id' => $sale ? $o->seller_company_id : $o->buyer_company_id, 'kind' => $kind, 'request_key' => (string) Str::uuid(), 'party_id' => $sale ? $o->customer_id : $o->supplier_id, 'posting_date' => $v['posting_date'], 'due_date' => $v['due_date'], 'control_account_id' => $sale ? $v['receivable_account_id'] : $v['payable_account_id'], 'tax_percent' => '0', 'reference' => 'IC-'.$o->id, 'notes' => 'Linked intercompany order. Pre-tax draft: review tax treatment before approval/posting.', 'lines' => array_map(fn ($l) => ['description' => $l['description'], 'quantity' => $l['quantity'], 'rate' => $l['rate'], 'account_id' => $sale ? $v['income_account_id'] : $v['cost_account_id']], json_decode($o->lines, true))];
                $child = Request::create('/accounting/api/invoices', 'POST', $payload);
                $child->setUserResolver($r->getUserResolver());
                $child->setLaravelSession($r->session());
                $child->query->replace($r->query->all());
                $result = app(TradeController::class)->saveInvoice($child);
                $ids[$kind] = $result['invoice']['id'];
            }
            DB::table('accounting_intercompany_orders')->where('id',$id)->update(['sales_invoice_id' => $ids['sale'], 'purchase_invoice_id' => $ids['purchase'], 'status' => 'invoiced', 'version' => $o->version + 1, 'updated_at' => now()]);
            foreach ([$o->seller_company_id, $o->buyer_company_id] as $company) {
                Access::audit($r,$company,'Linked invoice drafts created','IC-'.$id,['order_id' => $id]);
            }
        });

        return ['message' => 'Both invoice drafts created. Review taxes and use the existing approval/posting workflow in each company.'];
    }
}
