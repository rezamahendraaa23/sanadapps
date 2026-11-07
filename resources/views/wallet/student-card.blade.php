@php use App\Modules\Finance\Support\Rupiah; @endphp

<div class="flex flex-col gap-4">
    @if (! $card)
        <x-ui.card>
            <x-ui.empty-state icon="id-card" message="Kartu belum diterbitkan. Hubungi admin pesantren." />
        </x-ui.card>
    @else
        <div
            wire:key="kartu-{{ $secondsLeft > 0 ? 'qr' : 'x' }}"
            x-data="{ left: @js($secondsLeft), bright: false, timer: null, lock: null,
                start() { clearInterval(this.timer); this.timer = setInterval(() => { this.left -= 1; if (this.left <= 0) { clearInterval(this.timer); $wire.$refresh().then(() => { this.left = Number($el.dataset.seconds); this.start() }) } }, 1000) },
                async toggleBright() { this.bright = !this.bright; try { if (this.bright) this.lock = await navigator.wakeLock?.request('screen'); else { await this.lock?.release(); this.lock = null } } catch { this.lock = null } } }"
            x-init="@if ($active) start() @endif"
            x-on:livewire:navigating.window="clearInterval(timer)"
            data-seconds="{{ $secondsLeft }}"
            :class="bright && 'fixed inset-0 z-50 flex items-center overflow-y-auto bg-surface p-4'"
        >
            <x-ui.ticket-card class="w-full">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <h2 class="font-display text-xl font-semibold break-words">{{ $student->name }}</h2>
                        <p class="font-tabular text-sm text-text-muted">NIS {{ $student->nis }}</p>
                        @if ($student->classroom)<p class="text-sm text-text-muted">{{ $student->classroom->name }}</p>@endif
                    </div>
                    <x-ui.status-badge :status="$active ? 'active' : 'blocked'" class="shrink-0" />
                </div>
                <x-slot:proof>
                    @if ($active)
                        <div class="mx-auto w-full max-w-[260px] bg-on-primary [&_svg]:h-auto [&_svg]:w-full" aria-label="QR kartu untuk dipindai kasir" role="img">{!! $qr !!}</div>
                        <p class="mt-2 text-center text-sm text-text-muted" aria-live="off">QR berganti dalam <span class="font-tabular font-semibold" x-text="left">{{ $secondsLeft }}</span> detik</p>
                        <div wire:offline class="mt-2 rounded-control bg-warning-soft p-2 text-center text-sm">Perlu koneksi untuk QR baru. QR ini akan kedaluwarsa.</div>
                        <x-ui.button variant="secondary" class="mt-3 w-full" x-on:click="toggleBright()"><span x-text="bright ? 'Kembali' : 'Tampilkan lebih terang'">Tampilkan lebih terang</span></x-ui.button>
                    @else
                        <p class="text-base">Kartu ini tidak bisa dipakai. Hubungi wali atau admin pesantren.</p>
                    @endif
                </x-slot:proof>
            </x-ui.ticket-card>
        </div>
    @endif

    @if ($balance !== null)
        <x-ui.card>
            <p class="text-sm text-text-muted">Saldo</p>
            <p class="font-display font-tabular text-2xl font-semibold">{{ Rupiah::format($balance) }}</p>
        </x-ui.card>
    @endif
</div>
