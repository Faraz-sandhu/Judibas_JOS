<?php

namespace App\Modules\Accounting\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class VoucherNumber
{
    /** Use within the caller's transaction, after locking its company row. */
    public static function next(int $company, string $type): string
    {
        $numbers=DB::table('accounting_journals')->where('company_id',$company)->orderBy('id')->pluck('number');
        $code=null;$sequence=0;
        foreach($numbers as $number){
            // Retain the first issued company code even if the company is later renamed.
            if(preg_match('/^[A-Z]+-([A-Z0-9]+)-\d{7,}$/D',$number,$match))$code??=$match[1];
            if(preg_match('/-(\d+)$/D',$number,$match))$sequence=max($sequence,(int)$match[1]);
        }
        if($code===null){
            $name=(string)DB::table('communication_companies')->where('id',$company)->value('name');
            $known=['judibas goc'=>'JGOC','judibas'=>'JUD','skyinfinit'=>'SKY','cruxlo'=>'CRX','wecoma'=>'WEC','suivillion'=>'SUI','corzella'=>'COR','sincwell'=>'SINC','true north'=>'TN','oxyien'=>'OXY','bedizen'=>'BED','judibas store'=>'JSTR','judibas canteen'=>'JCAN','nltpl'=>'NLTPL','china bedding store'=>'CBS','true water'=>'TW','gaming lounge'=>'GL','yubis'=>'YUB','backend - fsd'=>'BFSD','sales office - fsd'=>'SFSD','sales office - mul'=>'SMUL'];
            $words=preg_split('/[^A-Z0-9]+/',strtoupper(Str::ascii(trim($name))),-1,PREG_SPLIT_NO_EMPTY);
            $initials=count($words)>1?implode('',array_map(fn($word)=>$word[0],$words)):substr($words[0]??'COMP',0,4);
            $code=$known[strtolower(trim($name))]??substr($initials,0,8).$company;
        }
        abort_if($sequence>=PHP_INT_MAX,422,'This company has reached the voucher sequence limit.');
        return $type.'-'.$code.'-'.str_pad((string)(max($sequence,$numbers->count())+1),7,'0',STR_PAD_LEFT);
    }
}
