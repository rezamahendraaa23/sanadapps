<x-layouts.base title="Absen Selfie">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <div class="flex min-h-dvh flex-col bg-bg">
        <header class="sticky top-0 z-20 border-b border-border bg-surface pt-[env(safe-area-inset-top)]">
            <div class="mx-auto flex min-h-14 w-full max-w-[560px] items-center gap-2 px-3">
                <a href="{{ route('santri.home') }}" class="inline-flex size-11 shrink-0 items-center justify-center rounded-control" aria-label="Kembali ke beranda"><x-ui.icon name="chevron-left" /></a>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-semibold">{{ $session->title }}</p>
                    <p class="text-xs text-text-muted">Absen selfie</p>
                </div>
            </div>
        </header>

        @if ($done)
            <main class="mx-auto flex w-full max-w-[560px] flex-1 flex-col items-center justify-center gap-2 p-6 text-center">
                <x-ui.icon name="circle-check" size="xl" class="text-success" />
                <h1 class="font-display text-2xl font-semibold">Absensi sudah terkirim</h1>
                <p class="text-base">Tercatat {{ \App\Modules\Academic\Models\AttendanceRecord::STATUSES[$record->status] }}{{ in_array($record->review_status, ['flagged', 'pending'], true) ? ', menunggu tinjauan' : '' }}.</p>
                <x-ui.button variant="secondary" class="mt-3" :href="route('santri.home')">Kembali ke Beranda</x-ui.button>
            </main>
        @else
            <main class="mx-auto flex w-full max-w-[560px] flex-1 flex-col gap-3 p-3" x-data="selfieCamera(@js($config))">
                @if ($record->review_status === 'rejected')
                    <x-ui.banner tone="danger">Absen sebelumnya ditolak{{ $record->note ? ': '.$record->note : '' }}. Silakan ulangi, atau hubungi musyrif.</x-ui.banner>
                @endif

                <template x-if="config.closed">
                    <x-ui.banner tone="danger" role="alert">Sesi ini sudah ditutup. Hubungi ustadz atau musyrif.</x-ui.banner>
                </template>

                <div x-show="state === 'done'" x-cloak class="flex flex-col items-center gap-2 py-8 text-center">
                    <x-ui.icon name="circle-check" size="xl" class="text-success" />
                    <h1 class="font-display text-2xl font-semibold" x-text="review ? 'Absensi terkirim, menunggu tinjauan' : 'Absensi tercatat'"></h1>
                    <p class="text-base" x-text="review ? 'Musyrif akan memeriksa fotonya.' : 'Status: ' + statusLabel"></p>
                    <x-ui.button variant="secondary" class="mt-3" :href="route('santri.home')">Kembali ke Beranda</x-ui.button>
                </div>

                <div x-show="state === 'queued'" x-cloak role="status" class="flex flex-col items-center gap-2 py-8 text-center">
                    <x-ui.icon name="wifi-off" size="xl" class="text-text-muted" />
                    <h1 class="font-display text-2xl font-semibold">Foto tersimpan di HP</h1>
                    <p class="text-base">Tetap di halaman ini. Foto terkirim otomatis begitu tersambung, dan akan ditinjau musyrif.</p>
                </div>

                <div x-show="state === 'denied'" x-cloak role="alert" class="rounded-card border border-border bg-surface p-4">
                    <h1 class="text-lg font-semibold">Kamera tidak bisa dibuka</h1>
                    <p class="mt-1 text-base">Buka pengaturan browser, izinkan kamera untuk situs ini, lalu muat ulang. Atau ambil foto lewat tombol di bawah, tetapi absennya akan ditinjau musyrif.</p>
                    <label class="mt-3 inline-flex min-h-12 w-full cursor-pointer items-center justify-center rounded-control border border-primary font-semibold text-primary">
                        Ambil Foto dari HP
                        <input type="file" accept="image/*" capture="user" class="sr-only" x-on:change="fromFile($event)">
                    </label>
                </div>

                <div x-show="['starting', 'live', 'preview', 'sending'].includes(state)" class="flex flex-col gap-3">
                    <div class="relative mx-auto aspect-[3/4] max-h-[55dvh] max-w-full overflow-hidden rounded-card bg-text">
                        <video x-ref="video" x-show="state !== 'preview' && state !== 'sending'" autoplay playsinline muted class="size-full -scale-x-100 object-cover" aria-label="Kamera depan"></video>
                        <img x-ref="preview" x-show="state === 'preview' || state === 'sending'" x-cloak alt="Pratinjau foto" class="size-full object-cover">
                        <div x-show="state === 'live' || state === 'starting'" class="pointer-events-none absolute inset-[12%] rounded-[50%] border-2 border-on-primary"></div>
                    </div>
                    <p class="text-center text-base">Kode absen Anda: <strong class="font-display font-tabular text-2xl" x-text="config.code"></strong></p>
                    <p x-show="config.geofence" class="flex items-center justify-center gap-2 text-sm" aria-live="polite">
                        <span class="inline-flex" x-show="location === 'ok'"><x-ui.icon name="map-pin" size="sm" /></span>
                        <span class="inline-flex" x-show="location !== 'ok'"><x-ui.icon name="map-pin-off" size="sm" /></span>
                        <span x-text="locationLabel"></span>
                    </p>
                    <p x-show="error" x-cloak role="alert" class="flex items-start gap-1 text-sm text-danger"><x-ui.icon name="circle-alert" size="sm" class="mt-0.5" /><span x-text="error"></span></p>
                    <button type="button" x-show="state === 'live' || state === 'starting'" :disabled="state !== 'live' || config.closed" x-on:click="shoot()" class="min-h-12 w-full rounded-control bg-primary font-semibold text-on-primary disabled:opacity-40">Ambil Foto</button>
                    <div x-show="state === 'preview' || state === 'sending'" x-cloak class="grid grid-cols-2 gap-2">
                        <button type="button" :disabled="state === 'sending'" x-on:click="retake()" class="min-h-12 rounded-control border border-primary font-semibold text-primary disabled:opacity-40">Ulangi</button>
                        <button type="button" :disabled="state === 'sending'" x-on:click="send()" class="min-h-12 rounded-control bg-primary font-semibold text-on-primary disabled:opacity-40" x-text="state === 'sending' ? 'Mengirim…' : 'Kirim'"></button>
                    </div>
                </div>
            </main>
        @endif
    </div>
</x-layouts.base>
