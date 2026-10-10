<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;

class NotificationController extends Controller
{
    public function markAllAsRead()
    {
        $unreadNotifications = auth()->user()->unreadNotifications->markAsRead();
        if($unreadNotifications) {
            return response()->json([
                'success' => true,
                'message' => 'All notifications marked as read successfully'
            ]);
        }
    }

    public function open(string $notification): RedirectResponse
    {
        $item = auth()->user()->notifications()->whereKey($notification)->firstOrFail();
        $item->markAsRead();

        return redirect()->to($item->data['url'] ?? route('my-work.index'));
    }
}
