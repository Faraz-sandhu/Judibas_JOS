<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Chatify\Traits\UUID;
use App\Events\NewUnseenMessage; // Your custom event
use Illuminate\Support\Facades\Broadcast;

class ChMessage extends Model
{
    use UUID;

    protected static function booted()
    {
        static::created(function ($message) {
            $receiverId = $message->to_id;

            $unseenCount = self::where('to_id', $receiverId)
                ->where('seen', 0)
                ->count();
            broadcast(new NewUnseenMessage($receiverId, $unseenCount));
        });
    }
}
