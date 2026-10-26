<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Modules\Accounting\Services\{AccountingAccess as Access, Ledger};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BooksController
{
    public function save(Request $r)
    {
        $v = $r->validate(['company_id' => 'required|integer', 'version' => 'required|integer|min:0', 'currency' => 'required|in:PKR,USD,EUR,GBP,AED,SAR,CAD,AUD', 'start_date' => 'required|date_format:Y-m-d|before_or_equal:today', 'locked_through' => 'nullable|date_format:Y-m-d|after_or_equal:start_date|before_or_equal:today']);
        $company = (int) $v['company_id'];
        Access::company($r, $company, 'delete');
        DB::transaction(function () use ($r, $v, $company) {
            Ledger::lock($company);
            $old = DB::table('accounting_books')->where('company_id', $company)->first();
            abort_unless(($old->version ?? 0) === (int) $v['version'], 409, 'Books settings changed. Refresh before saving.');
            $hasEntries = DB::table('accounting_journals')->where('company_id', $company)->exists();
            abort_if($old && $hasEntries && ($old->currency !== $v['currency'] || $old->start_date !== $v['start_date']), 422, 'Currency and start date cannot change after the first journal is created.');
            abort_if($old?->locked_through && (! $r->filled('locked_through') || $v['locked_through'] < $old->locked_through), 422, 'A closed period cannot be reopened from these settings.');
            $values = ['currency' => $v['currency'], 'start_date' => $v['start_date'], 'locked_through' => $v['locked_through'] ?? null, 'version' => ($old->version ?? 0) + 1, 'updated_at' => now()];
            DB::table('accounting_books')->updateOrInsert(['company_id' => $company], $values + ($old ? [] : ['created_at' => now()]));
            Access::audit($r, $company, 'Books settings saved', 'Company books', ['previous' => $old, 'current' => $values]);
        });

        return ['message' => 'Company books settings saved.'];
    }

    public function audit(Request $r)
    {
        $v = $r->validate(['company_id' => 'required|integer', 'page' => 'nullable|integer|min:1']);
        Access::company($r, (int) $v['company_id'], 'delete');

        return DB::table('accounting_audits')->where('company_id', $v['company_id'])->orderByDesc('id')->select(['id', 'actor_name', 'action', 'subject', 'created_at'])->paginate(20);
    }
}
