<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\CommunicationActionsController;
use App\Http\Controllers\CommunicationController;
use App\Http\Controllers\CommunicationManagementController;
use App\Http\Controllers\CommunicationStoryController;
use App\Http\Controllers\CommunicationUsersController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\PortalController;
use App\Services\CommunicationAccess;
use Illuminate\Auth\GenericUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

Route::get('/', [PortalController::class, 'index']);
Route::get('/products', [PortalController::class, 'index']);
Route::get('/products/{slug}', [PortalController::class, 'index']);
Route::get('/branding/{file}', [PortalController::class, 'logo']);
Route::get('/blog/{article}', [PortalController::class, 'article']);
Route::get('/login', [PortalController::class, 'index']);
Route::post('/login', [EmployeeController::class, 'login'])->middleware('throttle:portal-login');
Route::post('/logout', [EmployeeController::class, 'logout']);
Route::get('/workspace/{slug}', [EmployeeController::class, 'workspace']);
Route::get('/admin', [PortalController::class, 'index']);
Route::post('/admin/login', [PortalController::class, 'login'])->middleware('throttle:portal-login');
Route::post('/admin/settings', [PortalController::class, 'save']);
Route::post('/admin/logout', [PortalController::class, 'logout']);
Route::get('/admin/api', [AdminController::class, 'data']);
Route::post('/admin/api/{resource}/{id?}', [AdminController::class, 'save'])->whereNumber('id');
Route::delete('/admin/api/{resource}/{id}', [AdminController::class, 'delete'])->whereNumber('id');
foreach (array_keys(config('pages')) as $page) {
    Route::get('/'.$page, [PortalController::class, 'index']);
}

Route::get('/communication', [CommunicationController::class, 'page']);
Route::get('/communication/api', [CommunicationController::class, 'data']);
Route::post('/communication/api/review', [CommunicationController::class, 'review']);
Route::get('/communication/api/audits', [CommunicationController::class, 'audits']);
Route::get('/communication/api/conversations/{id}/shared', [CommunicationController::class, 'shared'])->whereNumber('id');
Route::post('/communication/api/conversations', [CommunicationController::class, 'createConversation']);
Route::get('/communication/api/conversations/{id}/messages', [CommunicationController::class, 'messages'])->whereNumber('id');
Route::post('/communication/api/conversations/{id}/messages', [CommunicationController::class, 'send'])->whereNumber('id')->middleware('throttle:communication-message');
Route::patch('/communication/api/messages/{id}', [CommunicationController::class, 'updateMessage'])->whereNumber('id');
Route::delete('/communication/api/messages/{id}', [CommunicationController::class, 'deleteMessage'])->whereNumber('id');
Route::get('/communication/api/attachments/{id}', [CommunicationController::class, 'attachment'])->whereNumber('id');
Route::get('/communication/api/stories', [CommunicationStoryController::class, 'index']);
Route::post('/communication/api/stories', [CommunicationStoryController::class, 'store']);
Route::delete('/communication/api/stories/{id}', [CommunicationStoryController::class, 'destroy'])->whereNumber('id');

Route::get('/communication/api/management', [CommunicationManagementController::class, 'data']);
Route::post('/communication/api/companies/{id?}', [CommunicationManagementController::class, 'company'])->whereNumber('id');
Route::post('/communication/api/groups/{id}', [CommunicationManagementController::class, 'group'])->whereNumber('id');
Route::delete('/communication/api/groups/{id}', [CommunicationManagementController::class, 'deleteGroup'])->whereNumber('id');
Route::post('/communication/api/announcements', [CommunicationManagementController::class, 'announce']);
Route::post('/communication/api/invitations', [CommunicationManagementController::class, 'invite'])->middleware('throttle:communication-invite');
Route::post('/communication/api/invitations/{id}/revoke', [CommunicationManagementController::class, 'revoke'])->whereNumber('id');
Route::get('/communication/invitations/{token}', [CommunicationManagementController::class, 'invitationPage']);
Route::post('/communication/invitations/{token}', [CommunicationManagementController::class, 'accept'])->middleware('throttle:invitation-accept');
Route::post('/communication/api/messages/{id}/reactions', [CommunicationController::class, 'react'])->whereNumber('id');
Route::post('/communication/api/profile', [CommunicationActionsController::class, 'profile']);
Route::get('/communication/api/avatars/{id}', [CommunicationActionsController::class, 'avatar'])->whereNumber('id');
Route::post('/communication/api/messages/{id}/pin', [CommunicationActionsController::class, 'pin'])->whereNumber('id');
Route::post('/communication/api/messages/{id}/forward', [CommunicationActionsController::class, 'forward'])->whereNumber('id')->middleware('throttle:communication-message');

Route::get('/communication/api/stories/{id}/attachment', [CommunicationStoryController::class, 'attachment'])->whereNumber('id');

Route::post('/communication/api/stories/{id}/view', [CommunicationStoryController::class, 'viewed'])->whereNumber('id');
Route::get('/communication/api/stories/{id}/viewers', [CommunicationStoryController::class, 'viewers'])->whereNumber('id');

Route::get('/communication/api/users', [CommunicationUsersController::class, 'data']);
Route::post('/communication/api/users/{id?}', [CommunicationUsersController::class, 'save'])->whereNumber('id');
Route::delete('/communication/api/users/{id}', [CommunicationUsersController::class, 'remove'])->whereNumber('id');

Route::post('/broadcasting/auth', function (Request $r) {
    CommunicationAccess::authorize($r);
    $r->validate(['socket_id' => 'required|string|max:100', 'channel_name' => 'required|string|max:200']);
    if (CommunicationAccess::super($r)) {
        $r->setUserResolver(fn () => new GenericUser(['id' => 'super']));
    }

    return Broadcast::connection('reverb')->auth($r);
});
