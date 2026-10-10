<?php

namespace App\Modules\Pms\Http\Controllers;
use App\Http\Controllers\Controller;
use App\Modules\Pms\Services\PmsAccess;

use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;

class NotificationController extends Controller
{
    public function index(){ $user=\App\Modules\Pms\Services\PmsAuth::user();return response()->json(['items'=>$user->notifications()->latest()->limit(50)->get(),'unread'=>$user->unreadNotifications()->count(),'user_id'=>$user->id]); }
    public function markAllAsRead()
    {
        \App\Modules\Pms\Services\PmsAuth::user()->unreadNotifications()->update(['read_at'=>now()]);
        return response()->json(['message'=>'All notifications marked as read.']);
    }

    public function open(string $notification): RedirectResponse
    {
        $item = \App\Modules\Pms\Services\PmsAuth::user()->notifications()->whereKey($notification)->firstOrFail();
        $item->markAsRead();

        return redirect()->to($item->data['url'] ?? '/pms?section=my-work');
    }
}
