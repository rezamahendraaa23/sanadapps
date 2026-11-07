@php $tz = config('sanad.display_timezone'); @endphp

<form wire:submit="start" class="flex flex-col gap-4" x-data="examPrepare">
    <x-ui.card :title="$exam->title">
        <dl class="flex flex-col gap-1 text-base">
            <div class="flex justify-between gap-3"><dt class="text-text-muted">Durasi</dt><dd class="font-tabular">{{ $exam->duration_minutes }} menit</dd></div>
            <div class="flex justify-between gap-3"><dt class="text-text-muted">Ditutup</dt><dd class="font-tabular">{{ $exam->end_at?->setTimezone($tz)->translatedFormat('j M, H.i') }}</dd></div>
        </dl>
        <ul class="mt-3 flex list-disc flex-col gap-1 pl-5 text-sm">
            <li>Waktu berjalan sejak Mulai, tidak bisa dijeda. Batasnya mengikuti jam server.</li>
            <li>Jawaban tersimpan di HP walau koneksi putus, dan terkirim saat tersambung lagi.</li>
            <li>Pindah aplikasi atau tab tercatat dan terlihat oleh ustadz.</li>
            <li>Jika waktu habis, jawaban terkumpul otomatis.</li>
        </ul>
    </x-ui.card>

    <x-ui.card title="Pemeriksaan">
        <ul class="flex flex-col gap-2 text-base">
            <li class="flex items-center gap-2">
                <span x-show="online" class="inline-flex text-success"><x-ui.icon name="circle-check" /></span>
                <span x-show="!online" x-cloak class="inline-flex text-danger"><x-ui.icon name="circle-alert" /></span>
                <span x-text="online ? 'Koneksi tersambung' : 'Tidak tersambung'">Koneksi tersambung</span>
            </li>
            <li class="flex items-center gap-2">
                <span x-show="storage !== false" class="inline-flex text-success"><x-ui.icon name="circle-check" /></span>
                <span x-show="storage === false" x-cloak class="inline-flex text-danger"><x-ui.icon name="circle-alert" /></span>
                <span x-text="storage === false ? 'Penyimpanan HP tidak tersedia. Jangan pakai mode penyamaran.' : 'Penyimpanan HP siap'">Penyimpanan HP siap</span>
            </li>
        </ul>
    </x-ui.card>

    <x-ui.card>
        <div class="flex flex-col gap-3">
            @if (filled($exam->access_code))
                <x-ui.input label="Kode akses" name="code" autocomplete="off" required wire:model="code" />
            @endif
            @error('code')
                @if (blank($exam->access_code))
                    <p role="alert" class="flex items-start gap-1 text-sm text-danger"><x-ui.icon name="circle-alert" size="sm" class="mt-0.5" />{{ $message }}</p>
                @endif
            @enderror
            <label class="flex min-h-11 items-start gap-3 text-base">
                <input type="checkbox" wire:model.live="honest" class="mt-1 size-5 shrink-0 accent-primary">
                Saya mengerjakan sendiri dengan jujur.
            </label>
            @error('honest')
                <p role="alert" class="flex items-start gap-1 text-sm text-danger"><x-ui.icon name="circle-alert" size="sm" class="mt-0.5" />{{ $message }}</p>
            @enderror
            <x-ui.button type="submit" class="w-full" x-bind:disabled="!online || storage === false || !$wire.honest" wire:loading.attr="disabled" wire:target="start">{{ $continuing ? 'Lanjutkan Ujian' : 'Mulai Ujian' }}</x-ui.button>
            @unless ($honest)
                <p class="text-center text-sm text-text-muted">Tombol aktif setelah pernyataan dicentang.</p>
            @endunless
        </div>
    </x-ui.card>
</form>
