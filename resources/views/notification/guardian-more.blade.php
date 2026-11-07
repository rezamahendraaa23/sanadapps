<div class="flex flex-col gap-4">
    @if (app(\App\Modules\Core\Support\ModuleRegistry::class)->enabled('leave'))
        <a href="{{ route('wali.leaves') }}" wire:navigate class="flex min-h-11 items-center justify-between gap-2 rounded-card border border-border bg-surface p-4">
            <span class="flex min-w-0 items-center gap-3 text-base font-semibold"><x-ui.icon name="door-open" />Perizinan</span>
            <x-ui.icon name="chevron-right" class="shrink-0" />
        </a>
    @endif

    @if (app(\App\Modules\Core\Support\ModuleRegistry::class)->enabled('face_attendance'))
        <a href="{{ route('wali.face-consent') }}" wire:navigate class="flex min-h-11 items-center justify-between gap-2 rounded-card border border-border bg-surface p-4">
            <span class="flex min-w-0 items-center gap-3 text-base font-semibold"><x-ui.icon name="scan-face" />Persetujuan data wajah</span>
            <x-ui.icon name="chevron-right" class="shrink-0" />
        </a>
    @endif

    @if (app(\App\Modules\Core\Support\ModuleRegistry::class)->enabled('masjid_display'))
        <a href="{{ route('wali.display-consent') }}" wire:navigate class="flex min-h-11 items-center justify-between gap-2 rounded-card border border-border bg-surface p-4">
            <span class="flex min-w-0 items-center gap-3 text-base font-semibold"><x-ui.icon name="monitor" />Nama anak di layar masjid</span>
            <x-ui.icon name="chevron-right" class="shrink-0" />
        </a>
    @endif

    <x-ui.card title="Notifikasi">
        @if ($unread > 0)
            <x-slot:actions>
                <x-ui.button variant="tertiary" wire:click="markAllRead">Tandai semua dibaca</x-ui.button>
            </x-slot:actions>
        @endif
        @forelse ($notifications as $notification)
            <div wire:key="notif-{{ $notification->uuid }}" @class(['border-b border-border py-2 last:border-b-0', 'border-l-4 !border-l-primary pl-3' => ! $notification->read_at])>
                @if ($notification->link)
                    <a href="{{ $notification->link }}" wire:navigate class="block min-h-11">
                        <span @class(['block text-base', 'font-semibold' => ! $notification->read_at])>{{ $notification->title }}</span>
                        <span class="block text-xs text-text-muted">{{ $notification->created_at->setTimezone(config('sanad.display_timezone'))->translatedFormat('j M, H.i') }}</span>
                    </a>
                @else
                    <span @class(['block text-base', 'font-semibold' => ! $notification->read_at])>{{ $notification->title }}</span>
                    <span class="block text-xs text-text-muted">{{ $notification->created_at->setTimezone(config('sanad.display_timezone'))->translatedFormat('j M, H.i') }}</span>
                @endif
            </div>
        @empty
            <x-ui.empty-state icon="bell" message="Belum ada notifikasi." class="py-2" />
        @endforelse
    </x-ui.card>

    <x-ui.card title="Pengaturan WhatsApp">
        <ul class="flex flex-col">
            @foreach ($categories as $key => $category)
                <li wire:key="kategori-{{ $key }}" class="border-b border-border py-2 last:border-b-0">
                    @if ($category['critical'])
                        <p class="flex min-h-11 items-center gap-3 text-base"><x-ui.icon name="lock" size="sm" />{{ $category['label'] }}</p>
                        <p class="text-sm text-text-muted">Selalu dikirim agar tagihan tidak terlewat.</p>
                    @else
                        <label class="flex min-h-11 items-center gap-3 text-base">
                            <input type="checkbox" class="size-5 accent-primary" @checked($prefs[$key] ?? ($category['default'] ?? true)) wire:click="togglePref('{{ $key }}')">
                            {{ $category['label'] }}
                        </label>
                    @endif
                </li>
            @endforeach
        </ul>
    </x-ui.card>

    @if ($guardian)
        <x-ui.card title="Data Anda">
            <p class="text-base font-semibold">{{ $guardian->name }}</p>
            <p class="font-tabular text-sm text-text-muted">{{ $guardian->phone_wa }}</p>
            <ul class="mt-2 text-base">
                @foreach ($children as $child)
                    <li>{{ $child->name }} <span class="font-tabular text-sm text-text-muted">{{ $child->nis }}</span></li>
                @endforeach
            </ul>
        </x-ui.card>
    @endif
</div>
