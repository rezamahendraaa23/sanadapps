@php
    $order = ['submitted' => 'Bayar', 'paid' => 'Periksa dokumen', 'verified' => 'Tunggu seleksi', 'in_selection' => 'Seleksi', 'result' => 'Hasil'];
    $position = fn ($status) => match ($status) { 'draft' => -1, 'submitted' => 0, 'paid' => 1, 'verified' => 2, 'in_selection' => 3, default => 4 };
    $money = fn (int $value) => \App\Modules\Finance\Support\Rupiah::format($value);
@endphp

<div class="flex flex-col gap-4">
    <h1 class="font-display text-2xl font-semibold">Cek Status Pendaftaran</h1>

    @if (! $applicant)
        <form wire:submit="{{ $otpSent ? 'verifyOtp' : 'sendOtp' }}" class="flex flex-col gap-3">
            <x-ui.input label="Nomor pendaftaran" name="reg_no" autocomplete="off" required wire:model="reg_no" :disabled="$otpSent" help="Contoh: PPDB-202611-00001." />
            @if ($otpSent)
                <p class="text-base">Bila nomornya terdaftar, kode sudah dikirim ke WhatsApp wali.</p>
                <x-ui.input label="Kode dari WhatsApp" name="code" inputmode="numeric" autocomplete="one-time-code" required wire:model="code" />
                <x-ui.button type="submit" class="w-full" wire:loading.attr="disabled">Lihat Status</x-ui.button>
            @else
                <x-ui.button type="submit" class="w-full" wire:loading.attr="disabled">Kirim Kode</x-ui.button>
            @endif
        </form>
    @else
        <div class="rounded-card border border-border bg-surface p-4">
            <p class="text-sm text-text-muted">Nomor pendaftaran</p>
            <p class="font-tabular text-lg font-semibold break-words" x-data>
                {{ $applicant->reg_no }}
                <button type="button" class="ml-1 inline-flex min-h-11 items-center text-sm font-semibold text-primary underline" x-on:click="navigator.clipboard?.writeText(@js($applicant->reg_no)); $dispatch('toast', { message: 'Nomor disalin.', tone: 'success' })">Salin</button>
            </p>
            <p class="mt-2 text-base break-words">{{ $applicant->name ?: 'Draf belum lengkap' }}</p>
            <p class="font-display text-xl font-semibold">{{ \App\Modules\Admission\Models\Applicant::STATUSES[$publicStatus] }}</p>
        </div>

        @if ($applicant->status === 'draft')
            <x-ui.button :href="route('ppdb.register')">Lanjutkan Pendaftaran</x-ui.button>
        @else
            <ol class="flex flex-col gap-2" aria-label="Tahapan">
                @foreach ($order as $key => $label)
                    @php($done = $position($publicStatus) > $loop->index)
                    @php($current = $position($publicStatus) === $loop->index)
                    <li @class(['flex items-center gap-2 text-base', 'font-semibold' => $current, 'text-text-muted' => ! $done && ! $current]) @if ($current) aria-current="step" @endif>
                        <x-ui.icon :name="$done ? 'circle-check' : ($current ? 'circle-dot' : 'circle-dashed')" size="sm" />{{ $label }}
                    </li>
                @endforeach
            </ol>
        @endif

        @if ($rejectedDocuments->isNotEmpty())
            <x-ui.banner tone="warning">
                Dokumen perlu diperbaiki, hubungi panitia:
                @foreach ($rejectedDocuments as $document)
                    <span class="block text-sm">{{ \App\Modules\Admission\Models\Applicant::DOCUMENTS[$document->type] }}: {{ $document->note }}</span>
                @endforeach
            </x-ui.banner>
        @endif

        @if ($invoice && in_array($invoice->status, ['unpaid', 'partial', 'overdue'], true))
            <div class="rounded-card border border-border bg-surface p-4">
                <p class="text-sm text-text-muted">Biaya pendaftaran</p>
                <p class="font-display font-tabular text-2xl font-semibold">{{ $money($invoice->outstanding()) }}</p>
                @if ($onlineReady)
                    <x-ui.button class="mt-3 w-full" wire:click="pay" wire:loading.attr="disabled" wire:target="pay">Bayar Online</x-ui.button>
                    <p class="mt-2 text-sm text-text-muted">Atau bayar tunai ke panitia dengan menyebut nomor pendaftaran.</p>
                @else
                    <p class="mt-2 text-base">Bayar tunai ke panitia dengan menyebut nomor pendaftaran.</p>
                @endif
                @error('pay')<p role="alert" class="mt-2 text-sm text-danger">{{ $message }}</p>@enderror
            </div>
        @endif

        @foreach ($exams as $step)
            <div wire:key="ujian-{{ $step->uuid }}" class="rounded-card border border-border bg-surface p-4">
                <h2 class="text-base font-semibold break-words">{{ $step->name }}</h2>
                <p class="text-sm text-text-muted">{{ $step->exam->duration_minutes }} menit, tidak bisa dijeda.</p>
                @if (filled($step->exam->access_code))
                    <div class="mt-2"><x-ui.input label="Kode akses" name="examCode" autocomplete="off" wire:model="examCode" /></div>
                @endif
                <x-ui.button class="mt-3 w-full" wire:click="startExam('{{ $step->uuid }}')" wire:loading.attr="disabled" wire:target="startExam">Mulai Ujian<span class="sr-only"> {{ $step->name }}</span></x-ui.button>
            </div>
        @endforeach
        @error('exam')<x-ui.banner tone="danger" role="alert">{{ $message }}</x-ui.banner>@enderror

        @if ($publicStatus === 'accepted')
            <x-ui.banner tone="success">Selamat, calon santri diterima. Silakan daftar ulang ke panitia.</x-ui.banner>
        @elseif ($publicStatus === 'waitlist')
            <x-ui.banner tone="info">Masuk daftar cadangan. Panitia menghubungi bila ada tempat.</x-ui.banner>
        @elseif ($publicStatus === 'enrolled')
            <x-ui.banner tone="success">Daftar ulang selesai. Akun wali bisa dipakai masuk dengan nomor WhatsApp ini.</x-ui.banner>
        @endif

        <x-ui.button variant="tertiary" wire:click="logout">Keluar dari Halaman Ini</x-ui.button>
    @endif
</div>
