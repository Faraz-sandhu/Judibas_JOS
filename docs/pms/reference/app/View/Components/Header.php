<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\Component;

class Header extends Component
{
    public $notifications;

    /**
     * Create a new component instance.
     */
    public function __construct()
    {
        // Ensure Auth::user() is not null before accessing notifications
        $user = Auth::user();
        $this->notifications = "djasdj";

    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('components.header', ['notifications' => $this->notifications]);
    }
}
