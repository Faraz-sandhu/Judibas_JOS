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
Route::get('/accounting/invoices/{id}/print', [\App\Modules\Accounting\Http\Controllers\TradeController::class, 'printInvoice'])->whereNumber('id');
Route::prefix('accounting/api')->group(function () {
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
    Route::get('reports', [\App\Modules\Accounting\Http\Controllers\FinancialReportsController::class, 'report']);
    Route::get('data', [ChartOfAccountsController::class, 'data']);
    Route::post('initialize', [ChartOfAccountsController::class, 'initialize']);
    Route::post('accounts', [ChartOfAccountsController::class, 'save']);
    Route::delete('accounts/{id}', [ChartOfAccountsController::class, 'delete'])->whereNumber('id');
});
