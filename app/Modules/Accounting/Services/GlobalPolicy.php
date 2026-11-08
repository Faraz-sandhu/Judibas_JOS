<?php
namespace App\Modules\Accounting\Services;
use Illuminate\Support\Facades\DB;
class GlobalPolicy {
 public static function get(bool $lock=false): object {return DB::table('accounting_global_policy')->where('id',1)->when($lock,fn($q)=>$q->lockForUpdate())->first();}
 public static function apply(object $book):object {$policy=self::get();foreach(['approval_required','approval_types','expense_open_time','expense_close_time'] as $key)$book->$key=$policy->$key;return $book;}
}
