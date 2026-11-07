<div class="flex flex-col gap-4">
    <div>
        <h1 class="font-display text-2xl font-semibold">Pendaftaran Santri Baru</h1>
        @if ($period)<p class="text-sm text-text-muted break-words">{{ $period->name }}</p>@endif
    </div>

    @if (! $accepting && $step === 0)
        <x-ui.banner tone="warning" role="alert">Pendaftaran sedang ditutup.</x-ui.banner>
        <x-ui.button variant="secondary" :href="route('ppdb.status')">Cek Status Pendaftaran</x-ui.button>
    @elseif ($step === 0)
        <form wire:submit="{{ $otpSent ? 'verifyOtp' : 'sendOtp' }}" class="flex flex-col gap-3">
            <p class="text-base">Tidak perlu membuat akun. Kami kirim kode ke WhatsApp wali, dan nomor ini dipakai untuk melanjutkan pendaftaran.</p>
            <x-ui.input label="Nomor WhatsApp wali" name="phone" type="tel" inputmode="tel" autocomplete="tel" required wire:model="phone" :disabled="$otpSent" />
            @if ($otpSent)
                <x-ui.input label="Kode dari WhatsApp" name="code" inputmode="numeric" autocomplete="one-time-code" required wire:model="code" help="Berlaku 5 menit." />
                <x-ui.button type="submit" class="w-full" wire:loading.attr="disabled">Lanjutkan</x-ui.button>
            @else
                <x-ui.button type="submit" class="w-full" wire:loading.attr="disabled">Kirim Kode</x-ui.button>
            @endif
        </form>
        <p class="text-center text-sm"><a class="font-semibold text-primary underline" href="{{ route('ppdb.status') }}">Sudah mendaftar? Cek status</a></p>
    @else
        <ol class="grid grid-cols-5 gap-1" aria-label="Langkah">
            @foreach ([1 => 'Calon santri', 2 => 'Wali', 3 => 'Dokumen', 4 => 'Tinjau', 5 => 'Bayar'] as $number => $label)
                <li @class(['flex min-w-0 flex-col items-center gap-1 text-center text-xs', 'font-semibold text-primary' => $step === $number, 'text-text-muted' => $step !== $number]) @if ($step === $number) aria-current="step" @endif>
                    <span @class(['font-tabular flex size-7 items-center justify-center rounded-full border', 'border-primary bg-primary text-on-primary' => $step >= $number, 'border-border' => $step < $number])>{{ $number }}</span>
                    <span class="break-words">{{ $label }}</span>
                </li>
            @endforeach
        </ol>
        @if ($applicant)
            <p class="text-sm text-text-muted">Nomor pendaftaran <strong class="font-tabular">{{ $applicant->reg_no }}</strong>. Data tersimpan otomatis tiap langkah.</p>
        @endif

        @if ($step === 1)
            <form wire:submit="saveChild" class="flex flex-col gap-3">
                <x-ui.input label="Nama lengkap" name="name" required wire:model="name" />
                <x-ui.select label="Jenis kelamin" name="gender" required placeholder="Pilih" :options="['L' => 'Laki-laki', 'P' => 'Perempuan']" wire:model="gender" />
                <x-ui.input label="Tempat lahir" name="birth_place" wire:model="birth_place" />
                <x-ui.input label="Tanggal lahir" name="birth_date" type="date" required wire:model="birth_date" />
                <x-ui.textarea label="Alamat" name="address" required wire:model="address" />
                <x-ui.input label="Sekolah asal" name="previous_school" wire:model="previous_school" />
                <x-ui.button type="submit" class="w-full" wire:loading.attr="disabled">Lanjut</x-ui.button>
            </form>
        @elseif ($step === 2)
            <form wire:submit="saveGuardian" class="flex flex-col gap-3">
                <x-ui.input label="Nama wali" name="guardian_name" required wire:model="guardian_name" />
                <x-ui.select label="Hubungan" name="guardian_relation" required :options="['ayah' => 'Ayah', 'ibu' => 'Ibu', 'wali' => 'Wali']" wire:model="guardian_relation" />
                <x-ui.input label="Email" name="guardian_email" type="email" wire:model="guardian_email" help="Tidak wajib." />
                <div class="grid grid-cols-2 gap-2">
                    <x-ui.button variant="secondary" wire:click="back">Kembali</x-ui.button>
                    <x-ui.button type="submit" wire:loading.attr="disabled">Lanjut</x-ui.button>
                </div>
            </form>
        @elseif ($step === 3)
            <div class="flex flex-col gap-3">
                <p class="text-base">JPG, PNG, atau PDF, maksimal 5 MB. Bisa langsung difoto dari HP.</p>
                @foreach (\App\Modules\Admission\Models\Applicant::DOCUMENTS as $type => $label)
                    @php($document = $documents[$type] ?? null)
                    <div wire:key="dok-{{ $type }}" x-data="documentUpload(@js($type))" class="rounded-card border border-border bg-surface p-3">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="text-base font-semibold">{{ $label }}@if (in_array($type, \App\Modules\Admission\Models\Applicant::REQUIRED_DOCUMENTS, true))<span class="text-danger" aria-hidden="true"> *</span>@else <span class="text-sm font-normal text-text-muted">(tidak wajib)</span>@endif</p>
                            @if ($document)
                                <span class="inline-flex items-center gap-1 text-sm font-semibold text-success"><x-ui.icon name="circle-check" size="sm" />Terunggah</span>
                            @endif
                        </div>
                        @if ($document && ! $document->isPdf())
                            <img src="{{ route('ppdb.document', $document) }}?v={{ $document->updated_at?->timestamp }}" alt="Pratinjau {{ $label }}" class="mt-2 max-h-40 rounded-control object-contain">
                        @elseif ($document)
                            <a class="mt-2 inline-block text-sm font-semibold text-primary underline" href="{{ route('ppdb.document', $document) }}">Lihat PDF</a>
                        @endif
                        <label for="dok-{{ $type }}" class="mt-2 block text-sm font-semibold">{{ $document ? 'Ganti berkas' : 'Pilih berkas' }}<span class="sr-only"> {{ $label }}</span></label>
                        <input id="dok-{{ $type }}" type="file" accept="image/jpeg,image/png,application/pdf" x-on:change="pick($event)" class="min-h-11 w-full text-base">
                        <p x-show="busy" x-cloak class="text-sm" role="status">Mengunggah…</p>
                        <p x-show="failed" x-cloak class="text-sm text-danger" role="alert">Unggah gagal. Periksa koneksi lalu coba lagi.</p>
                        @error('uploads.'.$type)<p role="alert" class="text-sm text-danger">{{ $message }}</p>@enderror
                    </div>
                @endforeach
                @error('documents')<x-ui.banner tone="danger" role="alert">{{ $message }}</x-ui.banner>@enderror
                <div class="grid grid-cols-2 gap-2">
                    <x-ui.button variant="secondary" wire:click="back">Kembali</x-ui.button>
                    <x-ui.button wire:click="toReview" wire:loading.attr="disabled">Lanjut</x-ui.button>
                </div>
            </div>
        @elseif ($step === 4)
            <div class="flex flex-col gap-3">
                <dl class="flex flex-col gap-1 text-base">
                    @foreach (['Nama' => $name, 'Tanggal lahir' => $birth_date, 'Alamat' => $address, 'Sekolah asal' => $previous_school ?: '-', 'Wali' => $guardian_name, 'Dokumen' => $documents->count().' berkas', 'Biaya pendaftaran' => \App\Modules\Finance\Support\Rupiah::format((int) $period?->registration_fee)] as $label => $value)
                        <div class="flex justify-between gap-3 border-b border-border py-1"><dt class="shrink-0 text-text-muted">{{ $label }}</dt><dd class="min-w-0 text-right break-words">{{ $value }}</dd></div>
                    @endforeach
                </dl>
                <label class="flex min-h-11 items-start gap-3 text-base">
                    <input type="checkbox" wire:model="consent" class="mt-1 size-5 shrink-0 accent-primary">
                    Saya wali calon santri dan menyetujui pengolahan data ini untuk keperluan pendaftaran.
                </label>
                @error('consent')<x-ui.banner tone="danger" role="alert">{{ $message }}</x-ui.banner>@enderror
                <div class="grid grid-cols-2 gap-2">
                    <x-ui.button variant="secondary" wire:click="back">Kembali</x-ui.button>
                    <x-ui.button wire:click="submit" wire:loading.attr="disabled" wire:target="submit">Kirim Pendaftaran</x-ui.button>
                </div>
            </div>
        @endif
    @endif
</div>
