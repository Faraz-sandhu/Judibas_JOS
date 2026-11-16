<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Modules\Accounting\Services\{AccountingAccess as Access, Ledger, ApprovalPolicy, ApprovalFlow, Permissions};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BooksController
{
    public function save(Request $r)
    {
        $v = $r->validate(['expense_open_time'=>'sometimes|required|date_format:H:i', 'expense_close_time'=>'sometimes|required|date_format:H:i', 'company_id' => 'required|integer', 'version' => 'required|integer|min:0', 'currency' => 'required|in:PKR,USD,EUR,GBP,AED,SAR,CAD,AUD', 'start_date' => 'required|date_format:Y-m-d|before_or_equal:today', 'approval_required' => 'sometimes|boolean', 'approval_types'=>'sometimes|array|max:12','approval_types.*'=>['required','string','distinct',\Illuminate\Validation\Rule::in(array_keys(ApprovalPolicy::TYPES))], 'locked_through' => 'nullable|date_format:Y-m-d|after_or_equal:start_date|before_or_equal:today']);
        $company = (int) $v['company_id'];
        Access::company($r, $company, 'books.manage');
        DB::transaction(function () use ($r, $v, $company) {
            $defaults=\App\Modules\Accounting\Services\GlobalPolicy::get();
            Ledger::lock($company);
            $old = DB::table('accounting_books')->where('company_id', $company)->first();
            $global=$old??$defaults;
            abort_unless(($old->version ?? 0) === (int) $v['version'], 409, 'Books settings changed. Refresh before saving.');
            $open=$v['expense_open_time']??$global->expense_open_time??'11:00';
            $close=$v['expense_close_time']??$global->expense_close_time??'20:00';
            abort_unless($open < $close,422,'Expense voucher opening time must be before its closing time.');
            if($open!==($global->expense_open_time??'11:00')||$close!==($global->expense_close_time??'20:00'))
                abort_unless(\App\Services\PlatformAccess::super($r),403,'Only Super Admin can change expense voucher hours.');
            $hasEntries = DB::table('accounting_journals')->where('company_id',$company)->exists() || DB::table('accounting_invoices')->where('company_id',$company)->exists() || DB::table('accounting_intercompany_orders')->where(fn($q)=>$q->where('buyer_company_id',$company)->orWhere('seller_company_id',$company))->exists() || DB::table('accounting_exchange_rates')->where('company_id',$company)->exists();
            abort_if($old && $hasEntries && ($old->currency !== $v['currency'] || $old->start_date !== $v['start_date']), 422, 'Currency and start date cannot change after accounting documents or exchange rates have been created.');
            abort_if($old?->locked_through && (! $r->filled('locked_through') || $v['locked_through'] < $old->locked_through), 422, 'A closed period cannot be reopened from these settings.');
            $enabled=(bool)($v['approval_required']??$global->approval_required??true);
            $types=$v['approval_types']??ApprovalPolicy::selected($global);
            $before=ApprovalPolicy::selected($global);$normal=$types;sort($before);sort($normal);
            if((bool)($global->approval_required??true)!==$enabled||$before!==$normal)Permissions::require($r,'workflow.configure');
            $changed=(bool)$global->approval_required!==$enabled||$before!==$normal||$open!==$global->expense_open_time||$close!==$global->expense_close_time;
            if($changed){
                abort_unless(\App\Services\PlatformAccess::super($r),403,'Only Super Admin can change this company’s Accounting approval and voucher hours settings.');
                Access::audit($r,$company,'Company Accounting policy saved','Company books',['previous'=>$old]);
            }
            $values = ['expense_open_time'=>$open,'expense_close_time'=>$close,'approval_types'=>json_encode(array_values($types)), 'approval_required' => $enabled, 'currency' => $v['currency'], 'start_date' => $v['start_date'], 'locked_through' => $v['locked_through'] ?? null, 'version' => ($old->version ?? 0) + 1, 'updated_at' => now()];
            DB::table('accounting_books')->updateOrInsert(['company_id' => $company], $values + ($old ? [] : ['created_at' => now()]));
            Access::audit($r, $company, 'Books settings saved', 'Company books', ['previous' => $old, 'current' => $values]);
        });

        return ['message' => 'Settings saved for this company only.'];
    }

    public function audit(Request $r)
    {
        $v = $r->validate(['company_id' => 'required|integer', 'page' => 'nullable|integer|min:1']);
        Access::company($r, (int) $v['company_id'], 'audit.view');

        return DB::table('accounting_audits')->where('company_id', $v['company_id'])->orderByDesc('id')->select(['id', 'actor_name', 'action', 'subject', 'created_at'])->paginate(20);
    }
}
