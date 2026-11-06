<div class="flex flex-col gap-4">
    <div class="grid gap-4 md:grid-cols-3">
        <x-ui.card title="Antrean">
            @if ($queueSize === null)
                <p class="flex items-center gap-2 text-base text-danger"><x-ui.icon name="circle-alert" />Antrean tidak bisa dihubungi.</p>
            @else
                <p class="font-display font-tabular text-2xl font-semibold">{{ $queueSize }}</p>
                <p class="text-sm text-text-muted">job menunggu</p>
            @endif
        </x-ui.card>
        <x-ui.card title="Ruang penyimpanan">
            @if ($freeGb === null)
                <p class="text-base text-text-muted">Tidak bisa dibaca di server ini.</p>
            @else
                <p class="font-display font-tabular text-2xl font-semibold">{{ number_format($freeGb, 1, ',', '.') }} GB</p>
                <p class="text-sm text-text-muted">tersisa, {{ $usedPercent }}% terpakai</p>
            @endif
        </x-ui.card>
        <x-ui.card title="Webhook terakhir">
            @if ($lastWebhook)
                <p class="text-base">{{ $lastWebhook->provider }}</p>
                <p class="text-sm text-text-muted">{{ $lastWebhook->created_at->setTimezone(config('sanad.display_timezone'))->translatedFormat('j M Y, H.i') }}</p>
            @else
                <p class="text-base text-text-muted">Belum ada webhook masuk.</p>
            @endif
        </x-ui.card>
    </div>

    <x-ui.banner tone="info">Status backup tampil di sini setelah penjadwalan backup dibuat di Tahap 13.</x-ui.banner>

    <x-ui.card title="Job gagal">
        @if ($failedJobs->isEmpty())
            <x-ui.empty-state message="Tidak ada job gagal." class="py-2" />
        @else
            <x-ui.table caption="Job gagal">
                <thead><tr><x-ui.th>Job</x-ui.th><x-ui.th>Waktu</x-ui.th><x-ui.th>Penyebab</x-ui.th><x-ui.th>Aksi</x-ui.th></tr></thead>
                <tbody>
                    @foreach ($failedJobs as $job)
                        <tr wire:key="failed-{{ $job->uuid }}">
                            <x-ui.td label="Job">{{ $job->name }}</x-ui.td>
                            <x-ui.td label="Waktu">{{ $job->failed_at }}</x-ui.td>
                            <x-ui.td label="Penyebab"><span class="break-words">{{ \Illuminate\Support\Str::limit($job->error, 120) }}</span></x-ui.td>
                            <x-ui.td label="Aksi"><x-ui.button variant="tertiary" wire:click="retry('{{ $job->uuid }}')">Ulangi<span class="sr-only"> {{ $job->name }}</span></x-ui.button></x-ui.td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
        @endif
    </x-ui.card>
</div>
