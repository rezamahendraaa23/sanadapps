<?php

namespace App\Modules\Notification\Livewire;

use App\Modules\Notification\Models\GuardianNotificationPref;
use App\Modules\Notification\Support\NotificationCatalog;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.portal', ['role' => 'wali', 'active' => 'more', 'title' => 'Lainnya'])]
#[Title('Lainnya')]
class GuardianMore extends Component
{
    public function togglePref(string $category): void
    {
        abort_unless(isset(NotificationCatalog::CATEGORIES[$category]) && ! NotificationCatalog::CATEGORIES[$category]['critical'], 403);

        $guardian = auth()->user()->guardian;
        abort_if($guardian === null, 404);

        $pref = GuardianNotificationPref::query()->firstOrNew(['guardian_id' => $guardian->id, 'category' => $category]);
        $pref->is_enabled = ! ($pref->exists ? $pref->is_enabled : true);
        $pref->save();
    }

    public function markAllRead(): void
    {
        auth()->user()->inAppNotifications()->whereNull('read_at')->update(['read_at' => now()]);
    }

    public function render(): View
    {
        $user = auth()->user();
        $guardian = $user->guardian;

        return view('notification.guardian-more', [
            'guardian' => $guardian,
            'children' => $guardian?->students()->orderBy('name')->get() ?? collect(),
            'categories' => NotificationCatalog::CATEGORIES,
            'prefs' => $guardian ? GuardianNotificationPref::query()->where('guardian_id', $guardian->id)->pluck('is_enabled', 'category') : collect(),
            'notifications' => $user->inAppNotifications()->latest('id')->limit(30)->get(),
            'unread' => $user->inAppNotifications()->whereNull('read_at')->count(),
        ]);
    }
}
