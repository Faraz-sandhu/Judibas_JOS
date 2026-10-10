<?php

namespace App\View\Components;

use App\Models\TimerLog;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\Component;

class HeaderComp extends Component
{
    public $notifications;

    public $unreadCount;

    public $currentyRunningTask;

    /**
     * Create a new component instance.
     */
    public function __construct()
    {
        $user = Auth::user();
        $this->notifications = $user->notifications()
            ->select(['id', 'type', 'notifiable_type', 'notifiable_id', 'data', 'read_at', 'created_at'])
            ->latest()
            ->limit(30)
            ->get();
        $this->unreadCount = $user->unreadNotifications()->count();

        $this->currentyRunningTask = TimerLog::with(['task.project', 'subtask:id,title'])
            ->where('user_id', $user->id)
            ->whereNotNull('start_time')
            ->whereNull('end_time')
            ->latest('start_time')
            ->first();
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('components.header-comp', ['notifications' => $this->notifications, 'unreadCount' => $this->unreadCount, 'currentyRunningTask' => $this->currentyRunningTask]);
    }
}
