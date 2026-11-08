@php $tz = config('sanad.display_timezone'); @endphp

<div class="flex flex-col gap-4">
    <div role="tablist" aria-label="Bagian konten" class="flex flex-wrap gap-1">
        @foreach (['konten' => 'Konten', 'persetujuan' => 'Persetujuan ('.$pending->count().')', 'darurat' => 'Darurat', 'pustaka' => 'Ayat & Hadis', 'jumat' => 'Imam & Kajian'] as $key => $label)
            <x-ui.tab :selected="$tab === $key" wire:key="tab-{{ $key }}" wire:click="$set('tab', '{{ $key }}')">{{ $label }}</x-ui.tab>
        @endforeach
    </div>

    @if ($tab === 'konten')
        <x-ui.card>
            <x-ui.page-toolbar>
                <div class="w-full md:max-w-xs"><x-ui.select label="Status" name="statusFilter" placeholder="Semua" :options="$statuses" wire:model.live="statusFilter" /></div>
                <x-ui.button icon="plus" wire:click="create">Buat Konten</x-ui.button>
            </x-ui.page-toolbar>
        </x-ui.card>
        <x-ui.card>
            @forelse ($contents as $content)
                <div wire:key="c-{{ $content->uuid }}" class="flex flex-wrap items-start justify-between gap-2 border-b border-border py-3 last:border-b-0">
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold break-words">{{ $content->title }}</p>
                        <p class="text-sm text-text-muted">{{ $types[$content->type] ?? $content->type }}, {{ $content->layout === 'full' ? 'layar penuh' : 'bagian pengumuman' }}, {{ $statuses[$content->status] }}{{ $content->announcement_id ? ', dari pengumuman' : '' }}{{ $content->ends_at ? ', sampai '.$content->ends_at->setTimezone($tz)->translatedFormat('j M H.i') : '' }}</p>
                        @if ($content->status === 'rejected' && $content->rejected_reason)<p class="text-sm text-danger break-words">Ditolak: {{ $content->rejected_reason }}</p>@endif
                    </div>
                    <div class="flex shrink-0 flex-wrap gap-1">
                        @if (! $content->announcement_id && $content->status !== 'archived')<x-ui.button variant="tertiary" wire:click="edit('{{ $content->uuid }}')">Ubah<span class="sr-only"> {{ $content->title }}</span></x-ui.button>@endif
                        @if (in_array($content->status, ['draft', 'rejected'], true))<x-ui.button variant="secondary" wire:click="submit('{{ $content->uuid }}')">Ajukan<span class="sr-only"> {{ $content->title }}</span></x-ui.button>@endif
                        @if ($content->status === 'approved')<x-ui.button variant="tertiary" wire:click="archive('{{ $content->uuid }}')" wire:confirm="Tarik konten ini dari layar?">Tarik<span class="sr-only"> {{ $content->title }}</span></x-ui.button>@endif
                    </div>
                </div>
            @empty
                <x-ui.empty-state icon="monitor" message="Belum ada konten." class="py-2" />
            @endforelse
            <div class="mt-3">{{ $contents->links() }}</div>
        </x-ui.card>
    @elseif ($tab === 'persetujuan')
        <x-ui.card title="Menunggu persetujuan">
            @forelse ($pending as $content)
                <div wire:key="p-{{ $content->uuid }}" class="grid gap-3 border-b border-border py-3 last:border-b-0 md:grid-cols-[1fr_auto]">
                    <div class="min-w-0">
                        <p class="font-semibold break-words">{{ $content->title }}</p>
                        <p class="text-sm text-text-muted">{{ $types[$content->type] ?? $content->type }}</p>
                        @if (isset($content->body['text']))<p class="mt-1 text-base break-words whitespace-pre-line">{{ $content->body['text'] }}</p>@endif
                        @if ($content->media_path && $content->type !== 'video')<img src="{{ route('display.media.staff', $content) }}" alt="{{ $content->title }}" class="mt-2 max-h-48 rounded-control">@endif
                    </div>
                    @if ($canApprove)
                        <div class="flex gap-2 md:flex-col">
                            <x-ui.button wire:click="approveContent('{{ $content->uuid }}')">Setujui<span class="sr-only"> {{ $content->title }}</span></x-ui.button>
                            <x-ui.button variant="secondary" wire:click="askReject('{{ $content->uuid }}')">Tolak<span class="sr-only"> {{ $content->title }}</span></x-ui.button>
                        </div>
                    @endif
                </div>
            @empty
                <x-ui.empty-state icon="circle-check" message="Tidak ada konten yang menunggu." class="py-2" />
            @endforelse
        </x-ui.card>
    @elseif ($tab === 'darurat')
        @if ($emergencies->isNotEmpty())
            <x-ui.banner tone="danger" role="alert">Pesan darurat sedang tayang: {{ $emergencies->first()->body['text'] ?? '' }}</x-ui.banner>
            @if ($canApprove)<x-ui.button variant="danger" class="self-start" wire:click="clearEmergency" wire:confirm="Cabut pesan darurat dari semua layar?">Cabut Pesan Darurat</x-ui.button>@endif
        @endif
        @if ($canApprove)
            <x-ui.card title="Kirim pesan darurat">
                <p class="text-base">Menimpa semua tampilan di layar sasaran sampai dicabut atau waktu akhirnya lewat.</p>
                <div class="mt-3 flex flex-col gap-3">
                    <x-ui.textarea label="Pesan" name="emergency_text" required wire:model="emergency_text" />
                    <x-ui.input label="Tampil sampai" name="emergency_until" type="datetime-local" required wire:model="emergency_until" />
                    <fieldset><legend class="text-sm font-semibold">Layar (kosongkan untuk semua)</legend>
                        @foreach ($screenOptions as $uuid => $name)
                            <label class="flex min-h-11 items-center gap-3 text-base"><input type="checkbox" value="{{ $uuid }}" wire:model="emergency_screens" class="size-5 accent-primary">{{ $name }}</label>
                        @endforeach
                    </fieldset>
                    <x-ui.button variant="danger" class="self-start" wire:click="sendEmergency" wire:confirm="Kirim pesan darurat ke layar sekarang?" wire:loading.attr="disabled" wire:target="sendEmergency">Kirim Pesan Darurat</x-ui.button>
                </div>
            </x-ui.card>
        @else
            <x-ui.card><p class="text-base">Pesan darurat hanya bisa dikirim takmir atau admin.</p></x-ui.card>
        @endif
    @elseif ($tab === 'pustaka')
        <x-ui.card title="Templat siap pakai">
            <p class="mb-3 text-sm text-text-muted">Ayat dan hadis yang sering dipakai di pesantren. Setelah masuk pustaka, ustadz atau takmir tetap mengecek teks Arab, terjemah, dan rujukannya sebelum tayang.</p>
            <div class="mb-4 flex flex-wrap gap-2" role="group" aria-label="Topik templat">
                @foreach ($topics as $topicKey => $topicName)
                    <button type="button" wire:key="tp-{{ $topicKey }}" wire:click="showTopic('{{ $topicKey }}')" aria-pressed="{{ $templateTopic === $topicKey ? 'true' : 'false' }}"
                        @class(['inline-flex min-h-11 items-center rounded-full border px-4 text-sm font-semibold', 'border-primary bg-primary text-on-primary' => $templateTopic === $topicKey, 'border-border bg-surface hover:border-primary' => $templateTopic !== $topicKey])>{{ $topicName }}</button>
                @endforeach
            </div>
            <div class="grid gap-3 md:grid-cols-2">
                @foreach ($templates as $i => $template)
                    @php $inLibrary = in_array($template['ref'], $quoteRefs, true); @endphp
                    <article wire:key="tpl-{{ $templateTopic }}-{{ $i }}" class="flex flex-col gap-2 rounded-card border border-border bg-bg p-4">
                        <x-ui.arabic class="text-2xl leading-loose">{{ $template['ar'] }}</x-ui.arabic>
                        <p class="text-base">{{ $template['id'] }}</p>
                        <div class="mt-auto flex flex-wrap items-center justify-between gap-2 pt-1">
                            <p class="text-sm font-semibold text-primary">{{ $template['ref'] }}</p>
                            @if ($inLibrary)
                                <span class="inline-flex items-center gap-1 text-sm text-text-muted"><x-ui.icon name="check" size="sm" />Sudah di pustaka</span>
                            @else
                                <x-ui.button variant="secondary" icon="plus" wire:click="addTemplates('{{ $templateTopic }}', {{ $i }})">Tambah<span class="sr-only"> {{ $template['ref'] }}</span></x-ui.button>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
            <x-ui.button variant="tertiary" icon="list-plus" class="mt-3" wire:click="addTemplates('{{ $templateTopic }}')">Tambah Semua Topik {{ $topics[$templateTopic] ?? '' }}</x-ui.button>
        </x-ui.card>
        <x-ui.card title="Tulis sendiri">
            <div class="flex flex-col gap-3">
                <x-ui.textarea label="Teks Arab" name="q_ar" wire:model="q_ar" dir="rtl" />
                <x-ui.textarea label="Terjemah" name="q_id" wire:model="q_id" />
                <x-ui.input label="Rujukan" name="q_ref" help="Contoh: QS. Al-Baqarah: 153, atau HR. Bukhari no. 1." wire:model="q_ref" />
                <x-ui.button variant="secondary" class="self-start" wire:click="saveQuote">Simpan</x-ui.button>
                <p class="text-sm text-text-muted">Hanya yang sudah diverifikasi ustadz atau takmir yang bisa tayang.</p>
            </div>
        </x-ui.card>
        <x-ui.card title="Pustaka">
            @forelse ($quotes as $quote)
                <div wire:key="q-{{ $quote->uuid }}" class="flex flex-wrap items-start justify-between gap-2 border-b border-border py-3 last:border-b-0">
                    <div class="min-w-0 flex-1">
                        <x-ui.arabic class="text-xl">{{ $quote->text_ar }}</x-ui.arabic>
                        <p class="text-base break-words">{{ $quote->text_id }}</p>
                        <p class="text-sm text-text-muted">{{ $quote->source_ref }}</p>
                    </div>
                    <div class="flex flex-col items-end gap-2">
                        @if ($quote->verified_at)
                            <span @class(['inline-flex items-center gap-1 text-sm font-semibold', 'text-success' => $quote->is_active, 'text-text-muted' => ! $quote->is_active])><x-ui.icon :name="$quote->is_active ? 'circle-check' : 'circle-pause'" size="sm" />{{ $quote->is_active ? 'Terverifikasi, tayang' : 'Terverifikasi, nonaktif' }}</span>
                        @elseif ($canApprove)
                            <x-ui.button variant="secondary" wire:click="verifyQuote('{{ $quote->uuid }}')">Verifikasi<span class="sr-only"> {{ $quote->source_ref }}</span></x-ui.button>
                        @else
                            <span class="text-sm">Belum diverifikasi</span>
                        @endif
                        @if (! $quote->verified_at || $canApprove)
                            <div class="flex gap-1">
                                @if ($quote->verified_at)
                                    <x-ui.button variant="tertiary" wire:click="toggleQuote('{{ $quote->uuid }}')">{{ $quote->is_active ? 'Nonaktifkan' : 'Aktifkan' }}<span class="sr-only"> {{ $quote->source_ref }}</span></x-ui.button>
                                @endif
                                <x-ui.button variant="tertiary" class="text-danger" wire:click="deleteQuote('{{ $quote->uuid }}')" wire:confirm="Hapus {{ $quote->source_ref }} dari pustaka?">Hapus<span class="sr-only"> {{ $quote->source_ref }}</span></x-ui.button>
                            </div>
                        @endif
                    </div>
                </div>
            @empty
                <x-ui.empty-state icon="book-open" message="Pustaka masih kosong." class="py-2" />
            @endforelse
        </x-ui.card>
    @else
        <x-ui.card title="Jadwal Jumat">
            <div class="grid gap-3 md:grid-cols-4 md:items-start">
                <x-ui.input label="Tanggal Jumat" name="k_date" type="date" wire:model="k_date" />
                <x-ui.input label="Khatib" name="k_khatib" wire:model="k_khatib" />
                <x-ui.input label="Imam" name="k_imam" wire:model="k_imam" />
                <x-ui.input label="Tema" name="k_theme" wire:model="k_theme" />
            </div>
            <x-ui.button variant="secondary" class="mt-3" wire:click="saveKhutbah">Simpan</x-ui.button>
            @foreach ($khutbah as $row)
                <p wire:key="k-{{ $row->uuid }}" class="border-b border-border py-1 text-base break-words last:border-b-0">{{ $row->date->translatedFormat('j M Y') }}: khatib {{ $row->khatib_name }}{{ $row->imam_name ? ', imam '.$row->imam_name : '' }}{{ $row->theme ? ', '.$row->theme : '' }}</p>
            @endforeach
        </x-ui.card>
        <x-ui.card title="Kajian rutin">
            <div class="grid gap-3 md:grid-cols-5 md:items-start">
                <x-ui.input label="Judul" name="j_title" wire:model="j_title" />
                <x-ui.input label="Pemateri" name="j_speaker" wire:model="j_speaker" />
                <x-ui.select label="Hari" name="j_day" :options="\App\Modules\Affairs\Models\DutyRoster::DAYS" wire:model="j_day" />
                <x-ui.input label="Jam" name="j_time" type="time" wire:model="j_time" />
                <x-ui.input label="Lokasi" name="j_location" wire:model="j_location" />
            </div>
            <x-ui.button variant="secondary" class="mt-3" wire:click="saveKajian">Simpan</x-ui.button>
            @foreach ($kajian as $row)
                <div wire:key="j-{{ $row->uuid }}" class="flex flex-wrap items-center justify-between gap-2 border-b border-border py-1 last:border-b-0">
                    <span class="text-base break-words">{{ \App\Modules\Affairs\Models\DutyRoster::DAYS[$row->day_of_week] }} <span class="font-tabular">{{ substr($row->start_time, 0, 5) }}</span>: {{ $row->title }}{{ $row->speaker ? ', '.$row->speaker : '' }}</span>
                    <x-ui.button variant="tertiary" wire:click="removeKajian('{{ $row->uuid }}')">Hapus<span class="sr-only"> {{ $row->title }}</span></x-ui.button>
                </div>
            @endforeach
        </x-ui.card>
    @endif

    <x-ui.modal name="form-konten" :title="$editing ? 'Ubah konten' : 'Buat konten'" class="md:max-w-2xl">
        <div class="flex flex-col gap-3">
            <fieldset>
                <legend class="text-sm font-semibold">Tampil di mana</legend>
                <div class="mt-1 grid gap-2 md:grid-cols-2">
                    @foreach (['panel' => ['Bagian pengumuman', 'Jam dan jadwal sholat tetap terlihat.'], 'full' => ['Layar penuh', 'Memenuhi layar saat gilirannya, jam kecil di pojok.']] as $value => [$heading, $hint])
                        <label wire:key="layout-{{ $value }}" @class(['flex cursor-pointer gap-3 rounded-control border p-3', 'border-primary bg-primary-soft' => $layout === $value, 'border-border' => $layout !== $value])>
                            <input type="radio" value="{{ $value }}" wire:model.live="layout" class="mt-1 size-5 accent-primary">
                            <span class="min-w-0">
                                <span class="block font-semibold">{{ $heading }}</span>
                                <span class="block text-sm text-text-muted">{{ $hint }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </fieldset>
            <x-ui.select label="Jenis" name="type" :options="$types" wire:model.live="type" />
            <x-ui.input label="Judul" name="title" required :help="in_array($type, ['poster', 'video', 'youtube', 'qr_donation'], true) ? 'Untuk daftar konten, tidak tampil di layar.' : null" wire:model="title" />
            @if ($type === 'quote')
                <x-ui.select label="Ayat atau hadis" name="quote_id" placeholder="Pilih" :options="$verifiedQuotes" wire:model="quote_id" />
            @elseif (in_array($type, ['text', 'agenda', 'obituary'], true))
                <x-ui.textarea label="Isi" name="text" :rows="$layout === 'full' ? 6 : 3" :help="$layout === 'full' ? 'Paling banyak 800 karakter. Hurufnya menyesuaikan panjang teks.' : 'Singkat saja, maksimal sekitar 40 kata agar terbaca dari jauh.'" wire:model="text" />
            @endif
            @if ($type === 'youtube')
                <x-ui.input label="Tautan YouTube" name="youtube_url" inputmode="url" required help="Tempel tautan video atau siaran langsung. Butuh internet; saat TV offline video ini dilewati." wire:model="youtube_url" />
                <label class="flex min-h-11 items-center gap-3 text-base"><input type="checkbox" wire:model="youtube_sound" class="size-5 accent-primary">Nyalakan suara</label>
                <p class="text-sm text-text-muted">Browser di laptop biasanya menolak memutar otomatis dengan suara. Tanpa centang ini video berputar otomatis tanpa suara.</p>
            @elseif ($type === 'video')
                <div class="flex flex-col gap-1">
                    <label for="field-media" class="text-sm font-semibold">Video (mp4 atau webm, paling besar 20 MB)</label>
                    <input id="field-media" type="file" accept="video/mp4,video/webm" wire:model="media" class="min-h-11 w-full text-base">
                    @error('media')<p role="alert" class="text-sm text-danger">{{ $message }}</p>@enderror
                </div>
            @elseif (in_array($type, ['poster', 'qr_donation'], true))
                @include('masjid.partials.image-cropper')
            @endif
            <div class="grid gap-3 md:grid-cols-2">
                <x-ui.input label="Mulai tayang" name="starts_at" type="datetime-local" wire:model="starts_at" />
                <x-ui.input label="Akhir tayang" name="ends_at" type="datetime-local" wire:model="ends_at" />
                <x-ui.input label="Durasi per tampil (detik)" name="duration" inputmode="numeric" :help="in_array($type, ['video', 'youtube'], true) ? 'Paling lama 3600 detik (1 jam).' : 'Paling lama 120 detik.'" wire:model="duration" />
                <x-ui.input label="Prioritas (0 sampai 10)" name="priority" inputmode="numeric" wire:model="priority" />
            </div>
            <fieldset><legend class="text-sm font-semibold">Hanya pada hari (kosongkan untuk setiap hari)</legend>
                <div class="flex flex-wrap gap-x-3">
                    @foreach ($days as $number => $label)
                        <label class="flex min-h-11 items-center gap-2 text-base"><input type="checkbox" value="{{ $number }}" wire:model="days" class="size-5 accent-primary">{{ $label }}</label>
                    @endforeach
                </div>
            </fieldset>
            <x-ui.select label="Hanya satu jam sebelum" name="before_prayer" placeholder="Kapan saja" :options="['fajr' => 'Subuh', 'dhuhr' => 'Dzuhur', 'asr' => 'Ashar', 'maghrib' => 'Maghrib', 'isha' => 'Isya']" wire:model="before_prayer" />
            <fieldset><legend class="text-sm font-semibold">Layar (kosongkan untuk semua)</legend>
                @foreach ($screenOptions as $uuid => $name)
                    <label class="flex min-h-11 items-center gap-3 text-base"><input type="checkbox" value="{{ $uuid }}" wire:model="screens" class="size-5 accent-primary">{{ $name }}</label>
                @endforeach
            </fieldset>
            <div>
                @if ($student)
                    <p class="flex flex-wrap items-center justify-between gap-2 rounded-control bg-primary-soft p-3 text-base">Menyebut santri: <strong class="min-w-0 break-words">{{ $student->name }}</strong><button type="button" wire:click="$set('studentUuid', '')" class="inline-flex min-h-11 items-center text-sm font-semibold text-primary underline">Lepas</button></p>
                @else
                    <x-ui.input label="Konten menyebut santri?" name="student_search" type="search" help="Tayang hanya bila wali sudah setuju nama anaknya tampil di layar." wire:model.live.debounce.250ms="student_search" />
                    @foreach ($studentResults as $result)
                        <button type="button" wire:key="st-{{ $result->uuid }}" wire:click="chooseStudent('{{ $result->uuid }}')" class="flex min-h-11 w-full items-center rounded-control px-2 text-left hover:bg-primary-soft"><span class="font-semibold break-words">{{ $result->name }}</span></button>
                    @endforeach
                @endif
            </div>
            <label class="flex min-h-11 items-center gap-3 text-base"><input type="checkbox" wire:model="is_ticker" class="size-5 accent-primary">Tampilkan sebagai teks berjalan di bawah layar</label>
            <label class="flex min-h-11 items-center gap-3 text-base"><input type="checkbox" wire:model="is_public" class="size-5 accent-primary">Tampil juga di halaman publik jamaah</label>
        </div>
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">Batal</x-ui.button>
            @if ($canApprove)
                <x-ui.button variant="secondary" wire:click="save" wire:loading.attr="disabled" wire:target="save,media">Simpan Draf</x-ui.button>
                <x-ui.button wire:click="save(true)" wire:loading.attr="disabled" wire:target="save,media">Simpan dan Tayangkan</x-ui.button>
            @else
                <x-ui.button wire:click="save" wire:loading.attr="disabled" wire:target="save,media">Simpan</x-ui.button>
            @endif
        </x-slot:footer>
    </x-ui.modal>

    <x-ui.modal name="tolak-konten" title="Tolak konten">
        <x-ui.textarea label="Alasan" name="reason" required wire:model="reason" />
        <x-slot:footer>
            <x-ui.button variant="secondary" x-on:click="open = false">Batal</x-ui.button>
            <x-ui.button variant="danger" wire:click="reject">Tolak</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
