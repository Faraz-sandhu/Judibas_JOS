<?php

use App\Http\Controllers\PortalController;
use App\Modules\Accounting\Http\Controllers\ChartOfAccountsController;
use App\Services\PlatformAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/accounting', function (Request $r) {
    if (! PlatformAccess::product($r, 'accounts')) {
        abort_if($r->user(), 403);
        $r->session()->put('url.intended', '/accounting');

        return redirect('/login');
    }

return app(PortalController::class)->index($r, 'accounts');
});
Route::get('/accounting/journals/{id}/print', [\App\Modules\Accounting\Http\Controllers\JournalController::class, 'printJournal'])->whereNumber('id');
Route::get('/accounting/vouchers/{id}/print', [\App\Modules\Accounting\Http\Controllers\JournalController::class, 'printVoucher'])->whereNumber('id');
Route::get('/accounting/invoices/{id}/print', [\App\Modules\Accounting\Http\Controllers\TradeController::class, 'printInvoice'])->whereNumber('id');
Route::prefix('accounting/api')->middleware(\App\Modules\Accounting\Http\Middleware\RestrictImprestOfficer::class)->group(function () {
    Route::get('journals/{id}/payment-proofs',[\App\Modules\Accounting\Http\Controllers\PaymentProofController::class,'index'])->whereNumber('id');
    Route::post('journals/{id}/payment-proofs',[\App\Modules\Accounting\Http\Controllers\PaymentProofController::class,'store'])->whereNumber('id');
    Route::get('payment-proofs/{id}',[\App\Modules\Accounting\Http\Controllers\PaymentProofController::class,'show'])->whereNumber('id');
    Route::post('payment-proofs/{id}/remove',[\App\Modules\Accounting\Http\Controllers\PaymentProofController::class,'remove'])->whereNumber('id');
    Route::post('admin-accounts/{id}',[\App\Modules\Accounting\Http\Controllers\AdminExtendedRecordController::class,'saveAccount'])->whereNumber('id');
    Route::post('admin-cheques/{id}',[\App\Modules\Accounting\Http\Controllers\AdminExtendedRecordController::class,'saveCheque'])->whereNumber('id');
    Route::post('admin-records/{type}/{id}', [\App\Modules\Accounting\Http\Controllers\AdminRecordController::class,'action'])->whereNumber('id');
    Route::get('realtime/config', [\App\Modules\Accounting\Http\Controllers\WorkspaceRealtimeController::class, 'config']);
    Route::post('realtime/auth', [\App\Modules\Accounting\Http\Controllers\WorkspaceRealtimeController::class, 'auth']);
    Route::get('report-parties',[\App\Modules\Accounting\Http\Controllers\FinancialReportsController::class,'parties']);
    Route::get('journal-parties',[\App\Modules\Accounting\Http\Controllers\JournalController::class,'parties']);
    Route::get('items',[\App\Modules\Accounting\Http\Controllers\ItemController::class,'index']);
    Route::post('items',[\App\Modules\Accounting\Http\Controllers\ItemController::class,'save']);
    Route::get('cheques', [\App\Modules\Accounting\Http\Controllers\ChequeController::class,'index']);
    Route::post('cheques', [\App\Modules\Accounting\Http\Controllers\ChequeController::class,'save']);
    Route::post('cheques/{id}/actions', [\App\Modules\Accounting\Http\Controllers\ChequeController::class,'action'])->whereNumber('id');
    Route::get('cash-custody', [\App\Modules\Accounting\Http\Controllers\CashCustodyController::class,'index']);
    Route::post('cash-custody/custodians', [\App\Modules\Accounting\Http\Controllers\CashCustodyController::class,'assign']);
    Route::post('cash-custody', [\App\Modules\Accounting\Http\Controllers\CashCustodyController::class,'save']);
    Route::post('cash-custody/{id}/receive', [\App\Modules\Accounting\Http\Controllers\CashCustodyController::class,'receive'])->whereNumber('id');
    Route::get('imprest', [\App\Modules\Accounting\Http\Controllers\ImprestController::class,'index']);
    Route::get('imprest/{id}', [\App\Modules\Accounting\Http\Controllers\ImprestController::class,'show'])->whereNumber('id');
    Route::post('imprest/funds', [\App\Modules\Accounting\Http\Controllers\ImprestController::class,'createFund']);
    Route::post('imprest/allocate', [\App\Modules\Accounting\Http\Controllers\ImprestController::class,'allocate']);
    Route::post('imprest/spend', [\App\Modules\Accounting\Http\Controllers\ImprestController::class,'spend']);
    Route::post('imprest/{id}/post', [\App\Modules\Accounting\Http\Controllers\ImprestController::class,'post'])->whereNumber('id');
    Route::post('imprest/{id}/cancel', [\App\Modules\Accounting\Http\Controllers\ImprestController::class,'cancel'])->whereNumber('id');
    Route::get('currencies', [\App\Modules\Accounting\Http\Controllers\CurrencyController::class,'index']);
    Route::post('currencies', [\App\Modules\Accounting\Http\Controllers\CurrencyController::class,'save']);
    Route::get('orders', [\App\Modules\Accounting\Http\Controllers\OrderController::class,'index']);
    Route::get('orders/options', [\App\Modules\Accounting\Http\Controllers\OrderController::class,'options']);
    Route::post('orders', [\App\Modules\Accounting\Http\Controllers\OrderController::class,'save']);
    Route::post('orders/{id}/actions', [\App\Modules\Accounting\Http\Controllers\OrderController::class,'action'])->whereNumber('id');
    Route::post('orders/{id}/invoices', [\App\Modules\Accounting\Http\Controllers\OrderController::class,'invoices'])->whereNumber('id');
    Route::get('all-companies', [\App\Modules\Accounting\Http\Controllers\AllCompaniesController::class, 'index']);
    Route::get('notifications', [\App\Modules\Accounting\Http\Controllers\NotificationController::class, 'index']);
    Route::post('notifications/read', [\App\Modules\Accounting\Http\Controllers\NotificationController::class, 'read']);
    Route::get('notifications/realtime/config', [\App\Modules\Accounting\Http\Controllers\NotificationController::class, 'config']);
    Route::post('notifications/realtime/auth', [\App\Modules\Accounting\Http\Controllers\NotificationController::class, 'auth']);
    Route::post('approvals/{id}/signed-copy', [\App\Modules\Accounting\Http\Controllers\SignedCopyController::class, 'store'])->whereNumber('id');
    Route::get('signed-copies/{id}', [\App\Modules\Accounting\Http\Controllers\SignedCopyController::class, 'show'])->whereNumber('id');
    Route::get('roles', [\App\Modules\Accounting\Http\Controllers\RoleController::class, 'index']);
    Route::post('roles', [\App\Modules\Accounting\Http\Controllers\RoleController::class, 'save']);
    Route::get('approvals', [\App\Modules\Accounting\Http\Controllers\ApprovalController::class, 'index']);
    Route::get('approvals/document', [\App\Modules\Accounting\Http\Controllers\ApprovalController::class, 'document']);
    Route::post('approvals/submit', [\App\Modules\Accounting\Http\Controllers\ApprovalController::class, 'submit']);
    Route::post('approvals/{id}/actions', [\App\Modules\Accounting\Http\Controllers\ApprovalController::class, 'action'])->whereNumber('id');
    Route::get('parties', [\App\Modules\Accounting\Http\Controllers\TradeController::class, 'parties']);
    Route::post('parties', [\App\Modules\Accounting\Http\Controllers\TradeController::class, 'saveParty']);
    Route::get('invoices', [\App\Modules\Accounting\Http\Controllers\TradeController::class, 'invoices']);
    Route::post('invoices', [\App\Modules\Accounting\Http\Controllers\TradeController::class, 'saveInvoice']);
    Route::post('invoices/{id}/post', [\App\Modules\Accounting\Http\Controllers\TradeController::class, 'postInvoice'])->whereNumber('id');
    Route::post('invoices/{id}/payments', [\App\Modules\Accounting\Http\Controllers\TradeController::class, 'payment'])->whereNumber('id');
    Route::post('invoices/{id}/reverse', [\App\Modules\Accounting\Http\Controllers\TradeController::class, 'reverse'])->whereNumber('id');
    Route::post('books', [\App\Modules\Accounting\Http\Controllers\BooksController::class, 'save']);
    Route::get('audit', [\App\Modules\Accounting\Http\Controllers\BooksController::class, 'audit']);
    Route::get('journals', [\App\Modules\Accounting\Http\Controllers\JournalController::class, 'index']);
    Route::post('journals', [\App\Modules\Accounting\Http\Controllers\JournalController::class, 'save']);
    Route::post('journals/{id}/post', [\App\Modules\Accounting\Http\Controllers\JournalController::class, 'post'])->whereNumber('id');
    Route::post('journals/{id}/reverse', [\App\Modules\Accounting\Http\Controllers\JournalController::class, 'reverse'])->whereNumber('id');
    Route::get('cash-basis-reviews/{id}', [\App\Modules\Accounting\Http\Controllers\CashBasisReviewController::class, 'show'])->whereNumber('id');
    Route::post('cash-basis-reviews/{id}', [\App\Modules\Accounting\Http\Controllers\CashBasisReviewController::class, 'save'])->whereNumber('id');
    Route::get('reports', [\App\Modules\Accounting\Http\Controllers\FinancialReportsController::class, 'report']);
    Route::get('data', [ChartOfAccountsController::class, 'data']);
    Route::post('initialize', [ChartOfAccountsController::class, 'initialize']);
    Route::post('accounts', [ChartOfAccountsController::class, 'save']);
    Route::delete('accounts/{id}', [ChartOfAccountsController::class, 'delete'])->whereNumber('id');
});
