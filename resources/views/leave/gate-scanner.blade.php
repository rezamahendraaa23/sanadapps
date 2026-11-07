@php
    $outcome = $result['outcome'] ?? null;
    [$bg, $icon, $headline] = match ($outcome) {
        'out' => ['bg-info-soft', 'log-out', 'KELUAR'],
        'in' => ['bg-success-soft', 'log-in', 'MASUK'],
        'denied' => ['bg-danger-soft', 'circle-alert', 'DITOLAK'],
        'needs_pickup' => ['bg-warning-soft', 'user-check', 'CATAT PENJEMPUT'],
        default => ['bg-surface', 'scan-line', 'Siap memindai'],
    };
@endphp

<div class="flex flex-col gap-4"
    x-data="{ audio: null, beep(ok) { try { this.audio ??= new AudioContext(); const tone = this.audio.createOscillator(); const gain = this.audio.createGain(); tone.frequency.value = ok ? 880 : 220; gain.gain.value = 0.15; tone.connect(gain).connect(this.audio.destination); tone.start(); tone.stop(this.audio.currentTime + (ok ? 0.15 : 0.6)); } catch {} navigator.vibrate?.(ok ? 80 : [120, 60, 120]); }, focusInput() { $nextTick(() => $refs.scan?.focus({ preventScroll: true })) } }"
    x-init="focusInput()"
    x-on:gate-beep.window="beep($event.detail.ok); focusInput()"
    x-on:click="if (! $event.target.closest('input, button, a, details')) focusInput()"
>
    @include('leave.partials.gate-nav')

    <div wire:offline class="flex items-center gap-2 rounded-control bg-danger-soft p-3 text-base font-semibold"><x-ui.icon name="wifi-off" />Tidak tersambung, pindaian belum tercatat.</div>

    <section role="status" aria-live="assertive" @class(['flex min-h-[60dvh] flex-col items-center justify-center gap-3 rounded-card border border-border p-6 text-center', $bg])>
        @if ($result && $result['name'])
            @include('leave.partials.student-photo', ['student' => $studentModel, 'class' => 'size-32'])
            <p class="font-display text-2xl font-semibold break-words">{{ $result['name'] }}</p>
            @if ($result['classroom'])<p class="text-base">{{ $result['classroom'] }}</p>@endif
        @endif
        <x-ui.icon :name="$icon" size="xl" />
        <h2 class="font-display text-hero font-semibold">{{ $headline }}</h2>
        @if ($result)
            <p class="text-xl font-semibold">{{ $result['message'] }}</p>
        @else
            <p class="text-base text-text-muted">Tap kartu atau pindai QR izin.</p>
        @endif

        @if ($outcome === 'needs_pickup')
            <form wire:submit="submitPickup" class="flex w-full max-w-sm flex-col gap-3 text-left">
                <x-ui.input label="Nama penjemput" name="pickup_name" required wire:model="pickup_name" />
                <x-ui.input label="Hubungan" name="pickup_relation" wire:model="pickup_relation" />
                <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="submitPickup">Catat Keluar</x-ui.button>
            </form>
        @endif

        @if ($outcome === 'denied' && $result['student'])
            <x-ui.button variant="secondary" icon="bell" wire:click="callMusyrif">Panggil Musyrif</x-ui.button>
        @endif
        @if ($result)
            <x-ui.button variant="tertiary" wire:click="clear">Selesai</x-ui.button>
        @endif
    </section>

    <x-ui.card>
        <form wire:submit="submitScan">
            <label for="field-scan" class="text-sm font-semibold">Hasil pindai</label>
            <input id="field-scan" x-ref="scan" type="password" autocomplete="off" wire:model="scan" class="mt-1 min-h-12 w-full rounded-control border border-text-muted bg-surface px-3 text-base">
        </form>
        <div x-data="qrScanner" x-show="supported" class="mt-3 flex flex-col gap-2" x-on:qr-found="$wire.set('scan', $event.detail).then(() => $wire.submitScan())">
            <video x-ref="video" x-show="running" muted playsinline class="w-full rounded-control bg-bg" aria-label="Kamera untuk memindai QR"></video>
            <x-ui.button variant="secondary" icon="camera" x-on:click="toggle()"><span x-text="running ? 'Matikan Kamera' : 'Pindai QR dengan Kamera'">Pindai QR dengan Kamera</span></x-ui.button>
            <p x-show="problem" x-text="problem" role="alert" class="text-sm text-danger"></p>
        </div>
    </x-ui.card>
</div>
