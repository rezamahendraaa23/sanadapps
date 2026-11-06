<?php

namespace App\Modules\Core\Livewire\Staff;

use Illuminate\Contracts\View\View;
use Livewire\Component;

class NotificationBell extends Component
{
    public function markAllRead(): void
    {
        auth()->user()->inAppNotifications()->whereNull('read_at')->update(['read_at' => now()]);
    }

    public function render(): View
    {
        $notifications = auth()->user()->inAppNotifications();

        return view('core.staff.notification-bell', [
            'unread' => (clone $notifications)->whereNull('read_at')->count(),
            'items' => $notifications->latest('id')->limit(10)->get(),
        ]);
    }
}
