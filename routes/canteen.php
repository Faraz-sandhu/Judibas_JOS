<?php
use Illuminate\Support\Facades\Route;
use App\Modules\Canteen\Http\Controllers\{CatalogController,OrderController};
Route::get('/canteen',[CatalogController::class,'page']);
Route::get('/canteen/api/bootstrap',[CatalogController::class,'bootstrap']);
Route::get('/canteen/api/catalog',[CatalogController::class,'index']);
Route::post('/canteen/api/catalog/{resource}/{id?}',[CatalogController::class,'save'])->whereNumber('id');
Route::get('/canteen/api/audit',[CatalogController::class,'audit']);
Route::post('/canteen/api/quote',[OrderController::class,'quote']);
Route::get('/canteen/api/orders',[OrderController::class,'index']);
Route::post('/canteen/api/orders',[OrderController::class,'store'])->middleware('throttle:60,1');
Route::patch('/canteen/api/orders/{id}',[OrderController::class,'update'])->whereNumber('id');
Route::get('/canteen/orders/{id}/receipt/{kind}',[OrderController::class,'receipt'])->whereNumber('id');
Route::get('/canteen/api/reports',[OrderController::class,'report']);

Route::post('/canteen/api/setup',[CatalogController::class,'setup']);
Route::get('/canteen/api/reports/export/{format}',[OrderController::class,'export']);

Route::get('/canteen/api/stock',[CatalogController::class,'stock']);
