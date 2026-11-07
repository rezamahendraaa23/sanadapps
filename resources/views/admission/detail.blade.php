@php $statuses = \App\Modules\Admission\Models\Applicant::STATUSES; $docLabels = \App\Modules\Admission\Models\Applicant::DOCUMENTS; @endphp

<div class="flex flex-col gap-4">
    <div><x-ui.button variant="tertiary" icon="chevron-left" :href="route('staff.ppdb.applicants.index')" wire:navigate>Daftar Pendaftar</x-ui.button></div>

    @if ($issuedPassword)
        <x-ui.banner tone="warning">Sandi awal santri: <strong class="font-tabular">{{ $issuedPassword }}</strong>. Catat sekarang, sandi ini tidak ditampilkan lagi. <a class="font-semibold underline" href="{{ $studentUrl }}" wire:navigate>Buka data santri</a></x-ui.banner>
    @endif

    <x-ui.card>
        <div class="flex flex-wrap items-start justify-between gap-2">
            <div class="min-w-0">
                <h2 class="font-display text-xl font-semibold break-words">{{ $applicant->name }}</h2>
                <p class="font-tabular text-sm text-text-muted">{{ $applicant->reg_no }}</p>
            </div>
            <div class="text-right">
                <p class="text-base font-semibold">{{ $statuses[$applicant->status] }}</p>
                <p class="text-sm">Skor akhir <strong class="font-tabular">{{ $applicant->total_score === null ? 'belum lengkap' : rtrim(rtrim(number_format($applicant->total_score, 2, ',', ''), '0'), ',') }}</strong></p>
            </div>
        </div>

        @if ($canManage)
            <div class="mt-3 flex flex-wrap gap-2">
                @if ($applicant->status === 'paid')
                    <x-ui.button wire:click="verify" wire:loading.attr="disabled" wire:target="verify">Verifikasi Dokumen</x-ui.button>
                @elseif ($applicant->status === 'verified')
                    <x-ui.button wire:click="startSelection" wire:loading.attr="disabled" wire:target="startSelection">Masukkan ke Seleksi</x-ui.button>
                @elseif (in_array($applicant->status, ['in_selection', 'accepted', 'rejected', 'waitlist'], true))
                    @unless ($applicant->status === 'accepted')<x-ui.button wire:click="decide('accepted')" wire:confirm="Terima {{ $applicant->name }}?">Terima</x-ui.button>@endunless
                    @unless ($applicant->status === 'waitlist')<x-ui.button variant="secondary" wire:click="decide('waitlist')">Cadangan</x-ui.button>@endunless
                    @unless ($applicant->status === 'rejected')<x-ui.button variant="danger" wire:click="decide('rejected')" wire:confirm="Tolak {{ $applicant->name }}?">Tolak</x-ui.button>@endunless
                @endif
                @if ($applicant->status === 'accepted')
                    <x-ui.button x-data x-on:click="$dispatch('open-modal', 'daftar-ulang')">Daftar Ulang</x-ui.button>
                @endif
            </div>
        @endif
    </x-ui.card>

    <div class="grid gap-4 lg:grid-cols-2">
        <x-ui.card title="Data" class="min-w-0">
            <dl class="flex flex-col text-base">
                @foreach (['Jenis kelamin' => ['L' => 'Laki-laki', 'P' => 'Perempuan'][$applicant->gender] ?? '-', 'Lahir' => trim($applicant->birth_place.', '.$applicant->birth_date?->translatedFormat('j F Y'), ', '), 'Alamat' => $applicant->address, 'Sekolah asal' => $applicant->previous_school ?: '-', 'Wali' => $applicant->guardian_name.' ('.ucfirst($applicant->guardian_relation).')', 'WhatsApp' => $applicant->guardian_phone, 'Email' => $applicant->guardian_email ?: '-'] as $label => $value)
                    <div class="flex justify-between gap-3 border-b border-border py-2 last:border-b-0"><dt class="shrink-0 text-text-muted">{{ $label }}</dt><dd class="min-w-0 text-right break-words">{{ $value }}</dd></div>
                @endforeach
            </dl>

            @if ($invoice)
                <h3 class="mt-4 text-sm font-semibold">Biaya pendaftaran</h3>
                <p class="text-base"><span class="font-tabular font-semibold">{{ $money((int) $invoice->total) }}</span>, <x-ui.invoice-status :status="$invoice->status" /></p>
                @if ($canCash && $invoice->outstanding() > 0)
                    <x-ui.button variant="secondary" class="mt-2" wire:click="recordCash" wire:confirm="Catat pembayaran tunai {{ $money($invoice->outstanding()) }}?" wire:loading.attr="disabled" wire:target="recordCash">Catat Tunai</x-ui.button>
                @endif
            @endif
        </x-ui.card>

        <x-ui.card title="Dokumen" class="min-w-0">
            @forelse ($documents as $document)
                <div wire:key="d-{{ $document->uuid }}" class="border-b border-border py-3 last:border-b-0">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="font-semibold">{{ $docLabels[$document->type] }}</p>
                        <span class="text-sm font-semibold">{{ ['pending' => 'Belum diperiksa', 'approved' => 'Disetujui', 'rejected' => 'Ditolak'][$document->status] }}</span>
                    </div>
                    @if ($document->isPdf())
                        <a class="text-sm font-semibold text-primary underline" href="{{ route('staff.ppdb.document', $document) }}">Buka PDF</a>
                    @else
                        <img src="{{ route('staff.ppdb.document', $document) }}" alt="{{ $docLabels[$document->type] }} {{ $applicant->name }}" loading="lazy" class="mt-2 max-h-64 rounded-control object-contain">
                    @endif
                    @if ($document->note)<p class="mt-1 text-sm">{{ $document->note }}</p>@endif
                    @if ($canManage)
                        <div class="mt-2 flex flex-col gap-2">
                            <input type="text" wire:model="documentNotes.{{ $document->uuid }}" aria-label="Alasan menolak {{ $docLabels[$document->type] }}" placeholder="Alasan bila ditolak" class="min-h-11 w-full rounded-control border border-text-muted bg-surface px-3 text-base">
                            <div class="flex gap-2">
                                <x-ui.button variant="secondary" wire:click="reviewDocument('{{ $document->uuid }}', 'approved')">Setujui<span class="sr-only"> {{ $docLabels[$document->type] }}</span></x-ui.button>
                                <x-ui.button variant="tertiary" wire:click="reviewDocument('{{ $document->uuid }}', 'rejected')">Tolak<span class="sr-only"> {{ $docLabels[$document->type] }}</span></x-ui.button>
                            </div>
                        </div>
                    @endif
                </div>
            @empty
                <x-ui.empty-state icon="file-text" message="Belum ada dokumen." class="py-2" />
            @endforelse
        </x-ui.card>
    </div>

    <x-ui.card title="Seleksi dan nilai">
        @if ($steps->isEmpty())
            <x-ui.empty-state icon="list-checks" message="Periode ini belum punya tahap seleksi." class="py-2" />
        @else
            <div class="flex flex-col gap-3">
                @foreach ($steps as $step)
                    <div wire:key="n-{{ $step->uuid }}" class="max-w-xs">
                        <x-ui.input :label="$step->name.' (bobot '.$step->weight.'%)'" :name="'scores.'.$step->uuid" inputmode="decimal" :disabled="! $canManage || $applicant->status !== 'in_selection'" wire:model="scores.{{ $step->uuid }}" />
                    </div>
                @endforeach
                @if ($canManage && $applicant->status === 'in_selection')
                    <x-ui.button class="self-start" wire:click="saveScores" wire:loading.attr="disabled" wire:target="saveScores">Simpan Nilai</x-ui.button>
                @endif
            </div>
        @endif
    </x-ui.card>

    <x-ui.modal name="daftar-ulang" title="Daftar ulang">
        <div class="flex flex-col gap-3">
            <p class="text-base">Dibuat otomatis untuk <strong class="break-words">{{ $applicant->name }}</strong>:</p>
            <ul class="flex list-disc flex-col gap-1 pl-5 text-base">
                <li>Data santri dengan NIS otomatis dan akun masuk</li>
                <li>Akun wali {{ $applicant->guardian_name }} ({{ $applicant->guardian_phone }})</li>
                <li>{{ $feeNames === [] ? 'Tidak ada tagihan uang masuk, jenis tagihan sekali bayar belum diatur' : 'Tagihan: '.implode(', ', $feeNames) }}</li>
            </ul>
            <x-ui.select label="Kelas" name="classroom_id" placeholder="Belum ditentukan" :options="$classrooms" wire:model="classroom_id" />
            <x-ui.select label="Asrama" name="dormitory_id" placeholder="Belum ditentukan" :options="$dormitories" wire:model="dormitory_id" />
        </div>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">Batal</x-ui.button>
            <x-ui.button wire:click="enroll" wire:loading.attr="disabled" wire:target="enroll">Proses Daftar Ulang</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
