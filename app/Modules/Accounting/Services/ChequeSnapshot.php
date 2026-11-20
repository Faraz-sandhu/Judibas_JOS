<?php

namespace App\Modules\Accounting\Services;

use Illuminate\Support\Facades\DB;

class ChequeSnapshot
{
    public static function at(int $company, string $to): array
    {
        $cheques = DB::table('accounting_cheques')->where('company_id', $company)->where('recorded_date', '<=', $to)->get();
        $events = DB::table('accounting_cheque_events')->whereIn('cheque_id', $cheques->pluck('id'))->where('event_date', '<=', $to)->orderBy('event_date')->orderBy('id')->get()->groupBy('cheque_id');
        $result = [];
        foreach ($cheques as $c) {
            $state = 'pending';
            $date = $c->recorded_date;
            foreach ($events->get($c->id, collect()) as $event) {
                $next = match ($event->action) {
                    'registered' => 'pending','deposit' => 'deposited','payment_posted' => 'cleared','bounce' => 'bounced','cancel','payment_reversed' => 'cancelled','clear' => $state === 'cleared' ? 'cleared' : 'clearing',default => $state
                };
                if ($next !== $state) {
                    $state = $next;
                    $date = $event->event_date;
                }
            }$result[$c->id] = ['status' => $state, 'date' => $date, 'amount_minor' => $c->amount_minor, 'direction' => $c->direction];
        }

        return $result;
    }
}
