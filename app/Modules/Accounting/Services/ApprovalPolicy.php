<?php
namespace App\Modules\Accounting\Services;

use Illuminate\Support\Facades\DB;

class ApprovalPolicy
{
    public const TYPES = [
        'contra'=>'CV - Contra vouchers', 'general'=>'Journal entries', 'opening'=>'Opening entries',
        'cash_payment'=>'CPV - Cash payment vouchers', 'cash_receipt'=>'CRV - Cash receipt vouchers',
        'bank_payment'=>'BPV - Bank payment vouchers', 'bank_receipt'=>'BRV - Bank receipt vouchers',
        'expense'=>'EV - Expense vouchers', 'income'=>'IV - Income vouchers',
        'sale'=>'Sales invoices', 'purchase'=>'Purchase invoices',
        'customer_payment'=>'Customer payment requests', 'supplier_payment'=>'Supplier payment requests',
    ];

    public static function selected(?object $book): array
    {
        $types=$book->approval_types??null;
        if(is_string($types))$types=json_decode($types,true);
        return $types===null?array_keys(self::TYPES):array_values(array_intersect(array_keys(self::TYPES),$types));
    }

    public static function required(int $company,string $key): bool
    {
        $book=DB::table('accounting_books')->where('company_id',$company)->first();
        if($book)$book=GlobalPolicy::apply($book);
        return $book&&(bool)$book->approval_required&&in_array($key,self::selected($book),true);
    }

    public static function documentKey(int $company,string $type,int $id): string
    {
        if($type==='journal')return DB::table('accounting_journals')->where('company_id',$company)->where('id',$id)->value('kind')??'';
        if($type==='invoice')return DB::table('accounting_invoices')->where('company_id',$company)->where('id',$id)->value('kind')??'';
        if($type==='payment'){
            $kind=DB::table('accounting_payment_requests as p')->join('accounting_invoices as i','i.id','=','p.invoice_id')->where('p.company_id',$company)->where('p.id',$id)->value('i.kind');
            return $kind==='sale'?'customer_payment':($kind==='purchase'?'supplier_payment':'');
        }
        return '';
    }
}
