<?php

namespace App\Modules\Accounting\Services;

use Illuminate\Support\Facades\DB;

class ChequeSnapshot
{
    public static function at(int $company, string $to): array
    {
        $cheques = DB::table('accounting_cheques')->where('company_id', $company)->whereNotIn('id',AdminRecordHistory::removed('cheque',$company,$to))->get();
        $changes=DB::table('accounting_admin_record_history')->where('company_id',$company)->where('record_type','cheque')->where('created_at','>',$to.' 23:59:59')->orderByDesc('id')->get()->groupBy('record_id');
        $events = DB::table('accounting_cheque_events')->whereIn('cheque_id', $cheques->pluck('id'))->where('event_date', '<=', $to)->orderBy('event_date')->orderBy('id')->get()->groupBy('cheque_id');
        $result = [];
        foreach ($cheques as $c) {
            foreach($changes->get($c->id,collect()) as $change){$previous=json_decode($change->snapshot,true);foreach(['amount_minor','direction','recorded_date','cheque_number','drawee_bank','cheque_date','invoice_id','party_id','bank_account_id','notes'] as $key)if(isset($previous[$key]))$c->$key=$previous[$key];}
            if($c->recorded_date>$to)continue;
            $state = 'pending';
            $date = $c->recorded_date;
            foreach ($events->get($c->id, collect()) as $event) {
                $next = match ($event->action) {
                    'registered','admin_edited' => 'pending','deposit' => 'deposited','payment_posted' => 'cleared','bounce' => 'bounced','cancel','payment_reversed' => 'cancelled','clear' => $state === 'cleared' ? 'cleared' : 'clearing',default => $state
                };
                if ($next !== $state) {
                    $state = $next;
                    $date = $event->event_date;
                }
            }$result[$c->id] = ['status' => $state, 'date' => $date, 'amount_minor' => $c->amount_minor, 'direction' => $c->direction,'record'=>array_intersect_key((array)$c,array_flip(['cheque_number','drawee_bank','cheque_date','invoice_id','party_id','bank_account_id','notes']))];
        }

        return $result;
    }
}
