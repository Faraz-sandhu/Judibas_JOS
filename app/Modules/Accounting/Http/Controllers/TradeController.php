<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Modules\Accounting\Services\{AccountingAccess as Access, Ledger, TradeDocuments as Trade, Permissions, ApprovalFlow};
use App\Modules\Accounting\Services\Currency;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TradeController
{
    public function parties(Request $r)
    {
        $v = $r->validate(['company_id' => 'required|integer', 'id'=>'nullable|integer|min:1', 'kind' => 'nullable|in:customer,supplier', 'search' => 'nullable|string|max:150', 'page' => 'nullable|integer|min:1']);
        Access::company($r, (int) $v['company_id']);
        $allowed = array_values(array_filter(['customer','supplier'], fn($kind)=>Permissions::can($r, ($kind==='customer'?'customers':'suppliers').'.view')));
        if(isset($v['kind']))Permissions::require($r,($v['kind']==='customer'?'customers':'suppliers').'.view');
        abort_unless($allowed,403);

        return DB::table('accounting_parties')->whereIn('kind',$allowed)->where('company_id', $v['company_id'])->whereNotIn('id',\App\Modules\Accounting\Services\AdminRecordHistory::removed('party',(int)$v['company_id']))->when($v['id']??null,fn($q,$id)=>$q->where('id',$id))->when($v['kind'] ?? null, fn ($q, $s) => $q->where('kind', $s))->when($v['search'] ?? null, fn ($q, $s) => $q->where('name', 'ilike', '%'.$s.'%'))->orderBy('name')->paginate(20);
    }

    public function saveParty(Request $r)
    {
        $v = $r->validate(['company_id' => 'required|integer', 'id' => 'nullable|integer', 'version' => [Rule::requiredIf(fn () => $r->filled('id')), 'integer', 'min:1'], 'kind' => 'required|in:customer,supplier', 'name' => 'required|string|max:150', 'email' => 'nullable|email|max:255', 'phone' => 'nullable|string|max:50', 'tax_number' => 'nullable|string|max:100', 'address' => 'nullable|string|max:2000', 'active' => 'required|boolean']);
        $company = (int) $v['company_id'];
        Access::company($r, $company, ($v['kind']==='customer'?'customers':'suppliers').($r->filled('id')?'.update':'.create'));
        DB::transaction(function () use ($r, $v, $company) {
            Ledger::lock($company);
            $old = isset($v['id']) ? DB::table('accounting_parties')->where('company_id', $company)->where('id', $v['id'])->first() : null;
            abort_if(isset($v['id']) && ! $old, 404);
            abort_if($old && $old->version !== (int) $v['version'], 409, 'This contact changed. Reopen it before saving.');
            if($old)\App\Modules\Accounting\Services\AdminRecordHistory::assertLive('party',$company,$old->id);
            abort_if($old && $old->kind !== $v['kind'], 422, 'Customer or supplier type cannot change.');
            $values = collect($v)->except(['id', 'version'])->all();
            $values['name'] = trim($values['name']);
            abort_if($values['name'] === '', 422, 'Enter a contact name.');
            $values += ['version' => ($old->version ?? 0) + 1, 'updated_at' => now()];
            if($old){DB::table('accounting_parties')->where('id',$old->id)->update($values);$partyId=$old->id;}else{$partyId=DB::table('accounting_parties')->insertGetId($values+['created_at'=>now()]);}
            \App\Modules\Accounting\Services\PartyAccounts::sync($company,$partyId);
            Access::audit($r, $company, $old ? 'Contact updated' : 'Contact created', $values['name']);
        });

        return ['message' => 'Contact saved.'];
    }

    public function invoices(Request $r)
    {
        $v = $r->validate(['company_id' => 'required|integer', 'id' => 'nullable|integer', 'kind' => 'nullable|in:sale,purchase', 'status' => 'nullable|in:draft,posted,reversed', 'page' => 'nullable|integer|min:1', 'party_id' => 'nullable|integer']);
        $company = (int) $v['company_id'];
        Access::company($r, $company);
        $allowed = array_values(array_filter(['sale','purchase'],fn($kind)=>Permissions::can($r,($kind==='sale'?'sales':'purchases').'.view')));
        abort_unless($allowed,403);
        if(isset($v['kind']))Permissions::require($r,($v['kind']==='sale'?'sales':'purchases').'.view');
        if (! empty($v['id'])) {
            $kind=DB::table('accounting_invoices')->where('company_id',$company)->where('id',$v['id'])->value('kind');
            Permissions::require($r,($kind==='sale'?'sales':'purchases').'.view');
            return ['invoice' => Trade::invoice($company, $v['id'])];
        }
        $rows = DB::table('accounting_invoices')->whereIn('kind',$allowed)->where('company_id', $company)->whereNotIn('id',\App\Modules\Accounting\Services\AdminRecordHistory::removed('invoice',$company))->when($v['kind'] ?? null, fn ($q, $s) => $q->where('kind', $s))->when($v['status'] ?? null, fn ($q, $s) => $q->where('status', $s))->when($v['party_id'] ?? null, fn ($q, $s) => $q->where('party_id', $s))->orderByDesc('posting_date')->orderByDesc('id')->paginate(15);
        $rows->setCollection($rows->getCollection()->map(fn ($i) => Trade::invoice($company, $i->id)));

        return $rows;
    }

    public function saveInvoice(Request $r)
    {
        $v = $r->validate(['company_id' => 'required|integer', 'id' => 'nullable|integer', 'version' => [Rule::requiredIf(fn () => $r->filled('id')), 'integer', 'min:1'], 'kind' => 'required|in:sale,purchase', 'request_key' => 'required|uuid', 'party_id' => 'required|integer', 'posting_date' => 'required|date_format:Y-m-d', 'due_date' => 'required|date_format:Y-m-d|after_or_equal:posting_date', 'control_account_id' => 'required|integer', 'currency'=>'nullable|string|size:3','exchange_rate'=>'nullable', 'discount_type'=>'sometimes|required|in:fixed,percent','discount_value'=>'nullable','tax_percent' => 'required', 'tax_label' => 'nullable|string|max:150', 'tax_account_id' => 'nullable|integer', 'reference' => 'nullable|string|max:150', 'notes' => 'nullable|string|max:2000', 'lines' => 'required|array|min:1|max:100', 'lines.*.item_id'=>'nullable|integer','lines.*.description' => 'required|string|max:250', 'lines.*.quantity' => 'required', 'lines.*.rate' => 'required', 'lines.*.account_id' => 'required|integer']);
        $company = (int) $v['company_id'];
        Access::company($r, $company, ($v['kind']==='sale'?'sales':'purchases').($r->filled('id')?'.update':'.create'));
        $id = DB::transaction(function () use ($r, $v, $company) {
            Ledger::lock($company);
            Ledger::checkDate(Ledger::book($company), $v['posting_date']);
            $sale = $v['kind'] === 'sale';
            $party = DB::table('accounting_parties')->where('company_id', $company)->where('id', $v['party_id'])->where('kind', $sale ? 'customer' : 'supplier')->where('active', true)->first();
            abort_unless($party, 422, 'Select an active customer or supplier in this company.');
            Trade::account($company,$v['control_account_id'],[$sale?'Receivable':'Payable'],[$sale?'Asset':'Liability']);
            $mapped=\App\Modules\Accounting\Services\PartyAccounts::sync($company,$party->id);
            abort_unless($mapped,422,'Initialize the company chart first.');
            $otherParty=DB::table('accounting_parties')->where('account_id',$v['control_account_id'])->where('id','<>',$party->id)->exists();
            abort_if($otherParty,422,'Select the account belonging to this customer or supplier.');
            $v['control_account_id']=$mapped;
            Trade::account($company, $v['control_account_id'], [$sale ? 'Receivable' : 'Payable'], [$sale ? 'Asset' : 'Liability']);
            $fx=Currency::context($company,$v);
            $net = 0;$lines = [];
            foreach ($v['lines'] as $line) {
                $item=null;if(!empty($line['item_id'])){$item=DB::table('accounting_items')->where('company_id',$company)->where('id',$line['item_id'])->where('active',true)->where($sale?'sellable':'purchasable',true)->first();abort_unless($item,422,'Choose an active item enabled for this invoice in this company.');}

                $account = Trade::account($company, $line['account_id'], [], $sale ? ['Income'] : ['Expense', 'Asset']);
                abort_if(in_array($account->account_type, ['Receivable', 'Payable', 'Cash', 'Bank']), 422, 'Invoice lines need income, expense or non-cash asset accounts.');
                $qty = Ledger::minor($line['quantity']);$rate = Ledger::minor($line['rate']);
                abort_unless($qty > 0 && $qty <= 10000000 && $rate > 0 && $rate <= intdiv(PHP_INT_MAX - 50, $qty), 422, 'Enter a valid positive quantity and rate.');
                $total = intdiv($qty * $rate + 50, 100);
                abort_unless($total > 0 && $total <= Ledger::MAX_MINOR, 422, 'Invoice line amount is outside the supported range.');
                $net += $total;
                $lines[] = ['description' => trim($line['description']), 'quantity' => Ledger::money($qty), 'rate' => Ledger::money($rate), 'account_id' => $account->id, 'amount_minor' => $total, 'amount' => Ledger::money($total)];
                if($item){$index=count($lines)-1;$lines[$index]+=['item_id'=>$item->id,'item_code'=>$item->code,'item_name'=>$item->name,'unit'=>$item->unit];}
            }
            $subtotal=$net;
            $discountType=$v['discount_type']??'fixed';$discountValue=Ledger::money(Ledger::minor($v['discount_value']??'0.00'));
            $discount=\App\Modules\Accounting\Services\InvoiceDiscount::apply($lines,$subtotal,$discountType,$discountValue);$net-=$discount;
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
            $foreignNet=$net;$foreignTax=$tax;$foreignTotal=$net+$tax;
            $baseNet=0;$baseSubtotal=0;
            foreach($lines as &$fxLine){$baseSubtotal+=Currency::convert($fxLine['subtotal_minor']??$fxLine['amount_minor'],$fx['exchange_rate']);$fxLine['foreign_amount_minor']=$fxLine['amount_minor'];$fxLine['amount_minor']=Currency::convert($fxLine['amount_minor'],$fx['exchange_rate']);abort_unless($fxLine['amount_minor']>0||$fxLine['foreign_amount_minor']===0,422,'Each invoice line must convert to at least one base-currency minor unit.');$baseNet+=$fxLine['amount_minor'];}unset($fxLine);
            $net=$baseNet;$tax=Currency::convert($foreignTax,$fx['exchange_rate']);
            abort_if($net+$tax>Ledger::MAX_MINOR,422,'Converted total exceeds the supported limit.');
            abort_if($tax<0,422,'Invoice rounding requires adjusting the lines.');
            $values = ['discount_type'=>$discountType,'discount_value'=>$discountValue,'foreign_discount_minor'=>$discount,'discount_minor'=>$baseSubtotal-$baseNet,'subtotal_minor'=>$baseSubtotal,'foreign_subtotal_minor'=>$subtotal]+$fx+['foreign_net_minor'=>$foreignNet,'foreign_tax_minor'=>$foreignTax,'foreign_total_minor'=>$foreignTotal]+['prepared_by' => $r->user()?->id, 'party_details' => json_encode(collect((array) $party)->only(['email', 'phone', 'address', 'tax_number'])->all()), 'company_name' => DB::table('communication_companies')->where('id', $company)->value('name'), 'party_id' => $party->id, 'party_name' => $party->name, 'kind' => $v['kind'], 'posting_date' => $v['posting_date'], 'due_date' => $v['due_date'], 'control_account_id' => $v['control_account_id'], 'tax_account_id' => $taxRate ? $v['tax_account_id'] : null, 'tax_label' => $taxRate ? $v['tax_label'] : null, 'tax_basis_points' => $taxRate, 'net_minor' => $net, 'tax_minor' => $tax, 'total_minor' => $net + $tax, 'reference' => $v['reference'] ?? null, 'notes' => $v['notes'] ?? null, 'lines' => json_encode($lines)];
            if ($old) {
                $sourceOrder=DB::table('accounting_intercompany_orders')->where($sale?'sales_invoice_id':'purchase_invoice_id',$old->id)->first();
                if($sourceOrder){
                    abort_if($discount>0,422,'Revise the linked order before applying an additional invoice discount.');
                    abort_unless($fx['currency']===$sourceOrder->currency&&$fx['exchange_rate']==='1.000000',422,'Linked company orders currently require their original base currency.');
                    abort_unless((int)$values['party_id']===(int)($sale?$sourceOrder->customer_id:$sourceOrder->supplier_id)&&$net===(int)$sourceOrder->total_minor,422,'The linked order customer/supplier and pre-tax amount cannot change.');
                    $sourceLines=json_decode($sourceOrder->lines,true);
                    $shape=fn($rows)=>array_map(fn($line)=>array_intersect_key($line,array_flip(['description','quantity','rate'])),$rows);
                    abort_unless($shape($lines)===$shape($sourceLines),422,'Linked order lines cannot change; account mappings and invoice tax can be reviewed separately.');
                }
                \App\Modules\Accounting\Services\AdminRecordHistory::assertLive('invoice',$company,$old->id);
                ApprovalFlow::ensureEditable($company,'invoice',$old->id);
                abort_unless($old->status === 'draft' && $old->version === (int) $v['version'], 409, 'Only the latest draft can be changed. Reopen it.');
                abort_unless($old->request_key === $v['request_key'] && $old->kind === $v['kind'], 422, 'Invoice identity cannot change.');
            } elseif ($existing = (clone $q)->where('request_key', $v['request_key'])->first()) {
                foreach (collect($values)->except('prepared_by') as $key => $value) {
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
                $id = DB::table('accounting_invoices')->insertGetId($values + ['company_id' => $company, 'request_key' => $v['request_key'], 'number' => $number, 'created_by' => $r->user()?->id, 'created_at' => now()]);
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
        Access::company($r, $company);
        DB::transaction(function () use ($r, $company, $id, $v) {
            Ledger::lock($company);
            $i = DB::table('accounting_invoices')->where('company_id', $company)->where('id', $id)->first();
            abort_unless($i, 404);
            Permissions::require($r,($i->kind==='sale'?'sales':'purchases').'.post');
            if ($i->status === 'posted') return;
            ApprovalFlow::requirePosting($r,$company,'invoice',$id,$i->version);
            \App\Modules\Accounting\Services\AdminRecordHistory::assertLive('invoice',$company,$i->id);
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
            $fx=['currency'=>$i->currency??Ledger::book($company)->currency,'exchange_rate'=>number_format((float)$i->exchange_rate,6,'.','')];
            foreach($lines as $index=>&$fxLine){
                $foreign=$index===0?($i->foreign_total_minor??$i->total_minor):($index<=count(json_decode($i->lines,true))?(json_decode($i->lines,true)[$index-1]['foreign_amount_minor']??($fxLine['debit_minor']+$fxLine['credit_minor'])):($i->foreign_tax_minor??$i->tax_minor));
                $d=$fxLine['debit_minor']>0;$fxLine+=Currency::tags($fx,$d?$foreign:0,$d?0:$foreign);
                $account=DB::table('accounting_accounts')->find($fxLine['account_id']);if($account->currency)Currency::account($account,$fx['currency'],$company);
            }unset($fxLine);
            $lines=array_values(array_filter($lines,fn($line)=>$line['debit_minor']+$line['credit_minor']>0));
            $jid = Trade::journal($r, $company, $i->posting_date, $i->number.' - '.$i->party_name, $lines);
            DB::table('accounting_invoices')->where('id', $id)->update(['status' => 'posted', 'journal_id' => $jid, 'version' => $i->version + 1, 'updated_at' => now()]);
            Access::audit($r, $company, 'Invoice posted', $i->number);
        });

        return ['message' => 'Invoice posted.', 'invoice' => Trade::invoice($company, $id)];
    }

    public function payment(Request $r, int $id)
    {
        if($r->filled('replacement_payment_id'))return app(AdminExtendedRecordController::class)->replacePayment($r,$id);
        $v = $r->validate(['company_id' => 'required|integer', 'request_key' => 'required|uuid', 'posting_date' => 'required|date_format:Y-m-d', 'amount' => 'required', 'bank_account_id' => 'required|integer', 'reference' => 'required|string|max:150','currency'=>'nullable|string|size:3','exchange_rate'=>'nullable','exchange_account_id'=>'nullable|integer']);
        $company = (int) $v['company_id'];
        Access::company($r, $company, $r->attributes->has('accounting_approval')?'payments.post':'payments.create');
        $queued=false;
        DB::transaction(function () use ($r, $company, $id, $v, &$queued) {
            Ledger::lock($company);
            $i = DB::table('accounting_invoices')->where('company_id', $company)->where('id', $id)->first();
            abort_unless($i, 404);
            $foreignAmount=Ledger::minor($v['amount']);
            $invoiceCurrency=$i->currency??Ledger::book($company)->currency;
            $fx=Currency::context($company,$v+['currency'=>$invoiceCurrency]);
            abort_unless($fx['currency']===$invoiceCurrency,422,'Settle in the invoice currency. Cross-currency settlement requires a separate bank conversion.');
            $foreignTotal=$i->foreign_total_minor??$i->total_minor;
            $foreignPaid=(int)DB::table('accounting_payments')->where('invoice_id',$id)->where('status','posted')->selectRaw('COALESCE(SUM(COALESCE(foreign_amount_minor,amount_minor)),0) as total')->value('total');
            $cheque=DB::table('accounting_cheques')->where('company_id',$company)->where('payment_key',$v['request_key'])->first();
            if($cheque){
                abort_unless($r->attributes->get('pdc_clearance')===$cheque->id||$r->attributes->has('accounting_approval'),403,'Clear this cheque through the Cheque Register.');
                abort_unless($cheque->invoice_id===$id&&$cheque->bank_account_id===(int)$v['bank_account_id']&&$cheque->amount_minor===$foreignAmount&&$v['posting_date']>=$cheque->cheque_date,422,'Payment must match the registered cheque invoice, amount, bank and maturity date.');
            }
            if($cheque)abort_unless(in_array($cheque->status,['pending','deposited','clearing','cleared']),422,'This cheque was cancelled or bounced.');
            $existingForeign=DB::table('accounting_payments')->where('company_id',$company)->where('request_key',$v['request_key'])->first();
            if($existingForeign){
                abort_unless($existingForeign->invoice_id===$id&&($existingForeign->foreign_amount_minor??$existingForeign->amount_minor)===$foreignAmount&&$existingForeign->bank_account_id===(int)$v['bank_account_id']&&$existingForeign->posting_date===$v['posting_date']&&$existingForeign->reference===$v['reference']&&($existingForeign->currency??Ledger::book($company)->currency)===$fx['currency']&&(float)$existingForeign->exchange_rate===(float)$fx['exchange_rate']&&((int)$existingForeign->exchange_difference_minor===0||$existingForeign->exchange_account_id===($v['exchange_account_id']??null)),409,'This request key belongs to another payment.');
                return;
            }
            $outstandingForeign=$foreignTotal-$foreignPaid;
            $chequeReserved=(int)DB::table('accounting_cheques')->where('invoice_id',$id)->whereIn('status',['pending','deposited','clearing'])->where('payment_key','<>',$v['request_key'])->sum('amount_minor');
            abort_if($foreignAmount>$outstandingForeign-$chequeReserved,422,'This invoice balance is reserved by pending cheques. Cancel or settle those cheques first.');
            abort_unless($foreignAmount>0&&$foreignAmount<=$outstandingForeign,422,'Payment exceeds the outstanding amount in the invoice currency.');
            $basePaid=(int)DB::table('accounting_payments')->where('invoice_id',$id)->where('status','posted')->when($existingForeign,fn($q)=>$q->where('id','<>',$existingForeign->id))->sum('amount_minor');
            $remainingBase=$i->total_minor-$basePaid;
            $amount=$foreignAmount===$outstandingForeign?$remainingBase:Currency::convert($foreignAmount,number_format((float)$i->exchange_rate,6,'.',''));
            abort_unless($amount>0&&$amount<=$remainingBase,422,'Payment is too small or rounding exceeds the outstanding balance.');
            $bankAmount=Currency::convert($foreignAmount,$fx['exchange_rate']);
            abort_unless($bankAmount>0,422,'Converted payment must be greater than zero.');
            $bank=Trade::account($company,$v['bank_account_id'],['Cash','Bank'],['Asset']);
            if($bank->currency)Currency::account($bank,$fx['currency'],$company);
            if($i->kind==='purchase'&&$bank->currency&&!$existingForeign)$bankAmount=Currency::bankCost($company,$bank->id,$foreignAmount,$v['posting_date']);
            if($existingForeign)$bankAmount=$existingForeign->bank_amount_minor??$existingForeign->amount_minor;
            $difference=$bankAmount-$amount;
            $fxAccount=null;
            if($difference!==0){$gain=($i->kind==='sale')===($difference>0);$fxAccount=Trade::account($company,(int)($v['exchange_account_id']??0),[],[$gain?'Income':'Expense']);abort_if($fxAccount->currency,422,'Exchange gain/loss account must use the base currency.');}

            abort_unless($i->status === 'posted', 422, 'Payments require a posted invoice.');
            abort_if($v['posting_date'] < $i->posting_date, 422, 'Payment cannot be before the invoice date.');
            $paid = (int) DB::table('accounting_payments')->where('invoice_id', $id)->where('status', 'posted')->sum('amount_minor');
            abort_unless($amount > 0 && $amount <= $i->total_minor - $paid, 422, 'Payment must be positive and cannot exceed the outstanding balance.');
            Trade::account($company, $v['bank_account_id'], ['Cash', 'Bank'], ['Asset']);
            Trade::account($company, $i->control_account_id, [$i->kind === 'sale' ? 'Receivable' : 'Payable']);
            Ledger::checkDate(Ledger::book($company),$v['posting_date']);
            if(\App\Modules\Accounting\Services\ApprovalPolicy::required($company,$i->kind==='sale'?'customer_payment':'supplier_payment')&&!$r->attributes->has('accounting_approval')){ApprovalFlow::queuePayment($r,$company,$id,array_replace($v,$fx,['amount'=>Ledger::money($foreignAmount)]));$queued=true;return;}
            $sale = $i->kind === 'sale';
            $number = 'PAY-'.str_pad((string) (DB::table('accounting_payments')->where('company_id', $company)->count() + 1), 7, '0', STR_PAD_LEFT);
            $bankFx=$bank->currency?array_replace($fx,['exchange_rate'=>$sale?$fx['exchange_rate']:number_format($bankAmount/$foreignAmount,6,'.','')]):['currency'=>Ledger::book($company)->currency,'exchange_rate'=>'1.000000'];
            $bankForeign=$bank->currency?$foreignAmount:$bankAmount;
            $paymentLines=[['account_id'=>$bank->id,'debit_minor'=>$sale?$bankAmount:0,'credit_minor'=>$sale?0:$bankAmount]+Currency::tags($bankFx,$sale?$bankForeign:0,$sale?0:$bankForeign),['account_id'=>$i->control_account_id,'party_id'=>$i->party_id,'debit_minor'=>$sale?0:$amount,'credit_minor'=>$sale?$amount:0]+Currency::tags(['currency'=>$invoiceCurrency,'exchange_rate'=>number_format((float)$i->exchange_rate,6,'.','')],$sale?0:$foreignAmount,$sale?$foreignAmount:0)];
            if($difference!==0){$gain=$sale===($difference>0);$paymentLines[]=['account_id'=>$fxAccount->id,'debit_minor'=>$gain?0:abs($difference),'credit_minor'=>$gain?abs($difference):0,'description'=>'Realised exchange '.($gain?'gain':'loss')];}
            if(!$sale&&$cheque)Ledger::requireCashFunds($company,'bank_payment',$v['posting_date'],$paymentLines);
            $jid=Trade::journal($r,$company,$v['posting_date'],$number.' / '.$i->number.' - '.$v['reference'],$paymentLines);
            DB::table('accounting_payments')->insert($fx+['foreign_amount_minor'=>$foreignAmount,'bank_amount_minor'=>$bankAmount,'exchange_difference_minor'=>$difference,'exchange_account_id'=>$fxAccount?->id]+['company_id' => $company, 'invoice_id' => $id, 'request_key' => $v['request_key'], 'number' => $number, 'posting_date' => $v['posting_date'], 'amount_minor' => $amount, 'bank_account_id' => $v['bank_account_id'], 'reference' => $v['reference'], 'journal_id' => $jid, 'created_at' => now(), 'updated_at' => now()]);
            if($cheque){DB::table('accounting_cheques')->where('id',$cheque->id)->update(['status'=>'cleared','cleared_date'=>$v['posting_date'],'version'=>$cheque->version+1,'updated_at'=>now()]);DB::table('accounting_cheque_events')->insert(['cheque_id'=>$cheque->id,'action'=>'payment_posted','event_date'=>$v['posting_date'],'actor_id'=>$r->user()?->id,'created_at'=>now()]);}
            Access::audit($r, $company, $sale ? 'Customer payment received' : 'Supplier payment made', $number, ['invoice' => $i->number, 'amount' => Ledger::money($amount)]);
        });

        return ['message' => $queued?'Payment submitted for approval. No ledger entry has been posted.':'Payment posted and allocated to the invoice.', 'invoice' => Trade::invoice($company, $id)];
    }

    public function reverse(Request $r, int $id)
    {
        $v = $r->validate(['company_id' => 'required|integer', 'version' => 'required|integer|min:1', 'posting_date' => 'required|date_format:Y-m-d', 'reason' => 'required|string|min:5|max:2000', 'payment_id' => 'nullable|integer']);
        $company = (int) $v['company_id'];
        Access::company($r, $company);
        $kind=DB::table('accounting_invoices')->where('company_id',$company)->where('id',$id)->value('kind');
        Permissions::require($r,!empty($v['payment_id'])?'payments.reverse':($kind==='sale'?'sales':'purchases').'.reverse');
        DB::transaction(function () use ($r, $v, $company, $id) {
            Ledger::lock($company);
            $i = DB::table('accounting_invoices')->where('company_id', $company)->where('id', $id)->first();
            abort_unless($i, 404);
            $payment = ! empty($v['payment_id']) ? DB::table('accounting_payments')->where('invoice_id', $id)->where('id', $v['payment_id'])->first() : null;
            abort_if(! empty($v['payment_id']) && ! $payment, 404);
            $row = $payment ?? $i;
            abort_unless($row->status === 'posted' && $row->version === (int) $v['version'], 409, 'Reopen the latest posted record before reversing it.');
            if(!$payment)abort_if(DB::table('accounting_cheques')->where('invoice_id',$id)->whereIn('status',['pending','deposited','clearing'])->exists(),422,'Cancel or resolve outstanding cheques before reversing this invoice.');
            if (! $payment) abort_if(DB::table('accounting_payments')->where('invoice_id', $id)->where('status', 'posted')->exists(), 422, 'Reverse all active invoice payments before reversing this invoice.');
            $journal = DB::table('accounting_journals')->where('id', $row->journal_id)->first();
            Ledger::reverse($r, $company, $row->journal_id, array_replace($v, ['version' => $journal->version]));
            DB::table($payment ? 'accounting_payments' : 'accounting_invoices')->where('id', $row->id)->update(['status' => 'reversed', 'version' => $row->version + 1, 'updated_at' => now()]);
            if($payment){$cheque=DB::table('accounting_cheques')->where('payment_key',$payment->request_key)->first();if($cheque){DB::table('accounting_cheques')->where('id',$cheque->id)->update(['status'=>'cancelled','version'=>$cheque->version+1,'updated_at'=>now()]);DB::table('accounting_cheque_events')->insert(['cheque_id'=>$cheque->id,'action'=>'payment_reversed','event_date'=>$v['posting_date'],'note'=>$v['reason'],'actor_id'=>$r->user()?->id,'created_at'=>now()]);}}
            Access::audit($r, $company, $payment ? 'Payment reversed' : 'Invoice reversed', $row->number, ['reason' => $v['reason']]);
        });

        return ['message' => 'Reversal recorded.', 'invoice' => Trade::invoice($company, $id)];
    }
    public function printInvoice(Request $r, int $id)
    {
        Access::role($r);
        $invoice = DB::table('accounting_invoices')->where('id', $id)->first();
        abort_unless($invoice, 404);
        Access::company($r, $invoice->company_id, ($invoice->kind==='sale'?'sales':'purchases').'.print');
        Access::audit($r, $invoice->company_id, 'Invoice print opened', $invoice->number);

        return response()->view('accounting.invoice', ['invoice' => Trade::invoice($invoice->company_id, $id), 'book' => Ledger::book($invoice->company_id), 'party' => json_decode($invoice->party_details ?? '{}', true)])->header('Cache-Control', 'private, no-store');
    }
}
