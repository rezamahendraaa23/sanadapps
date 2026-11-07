<?php

namespace App\Modules\Masjid\Livewire;

use App\Modules\Core\Models\Student;
use App\Modules\Finance\Actions\CompressProofImage;
use App\Modules\Masjid\Actions\ManageContent;
use App\Modules\Masjid\Models\DisplayContent;
use App\Modules\Masjid\Models\DisplayScreen;
use App\Modules\Masjid\Models\KajianSchedule;
use App\Modules\Masjid\Models\KhutbahSchedule;
use App\Modules\Masjid\Models\Quote;
use App\Modules\Masjid\Support\MasjidException;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/**
 * Konten layar, persetujuan, darurat, pustaka, imam dan kajian (MS-08 sampai MS-14).
 */
#[Layout('components.layouts.staff', ['active' => 'masjid-contents'])]
#[Title('Konten Layar')]
class DisplayContents extends Component
{
    use WithFileUploads, WithPagination;

    #[Url(except: 'konten')]
    public string $tab = 'konten';

    #[Url(as: 'status', except: '')]
    public string $statusFilter = '';

    #[Locked]
    public ?int $editing = null;

    public string $type = 'text';

    public string $title = '';

    public string $text = '';

    public $media = null;

    public string $quote_id = '';

    public string $starts_at = '';

    public string $ends_at = '';

    /** @var list<string> */
    public array $days = [];

    public string $before_prayer = '';

    public string $priority = '0';

    public string $duration = '12';

    /** @var list<string> */
    public array $screens = [];

    public bool $is_public = false;

    public bool $is_ticker = false;

    public string $student_search = '';

    public string $studentUuid = '';

    #[Locked]
    public ?string $rejecting = null;

    public string $reason = '';

    public string $emergency_text = '';

    public string $emergency_until = '';

    /** @var list<string> */
    public array $emergency_screens = [];

    public string $q_ar = '';

    public string $q_id = '';

    public string $q_ref = '';

    public string $k_date = '';

    public string $k_imam = '';

    public string $k_khatib = '';

    public string $k_theme = '';

    public string $j_title = '';

    public string $j_speaker = '';

    public string $j_day = '4';

    public string $j_time = '';

    public string $j_location = '';

    private function approve(): void
    {
        abort_unless(auth()->user()->can('masjid.approve'), 403);
    }

    public function create(): void
    {
        $this->reset('editing', 'type', 'title', 'text', 'media', 'quote_id', 'starts_at', 'ends_at', 'days', 'before_prayer', 'priority', 'duration', 'screens', 'is_public', 'is_ticker', 'studentUuid', 'student_search');
        $this->resetErrorBag();
        $this->dispatch('open-modal', 'form-konten');
    }

    public function edit(string $uuid): void
    {
        $content = DisplayContent::query()->where('uuid', $uuid)->whereNull('announcement_id')->firstOrFail();
        $zone = config('sanad.display_timezone');
        $this->editing = $content->id;
        $this->fill([
            'type' => $content->type,
            'title' => $content->title,
            'text' => (string) ($content->body['text'] ?? ''),
            'quote_id' => (string) ($content->body['quote_id'] ?? ''),
            'starts_at' => (string) $content->starts_at?->setTimezone($zone)->format('Y-m-d\TH:i'),
            'ends_at' => (string) $content->ends_at?->setTimezone($zone)->format('Y-m-d\TH:i'),
            'days' => array_map('strval', $content->schedule_rule['days'] ?? []),
            'before_prayer' => (string) ($content->schedule_rule['before_prayer'] ?? ''),
            'priority' => (string) $content->priority,
            'duration' => (string) $content->duration_seconds,
            'screens' => $content->screens()->pluck('display_screens.uuid')->all(),
            'is_public' => $content->is_public,
            'is_ticker' => $content->is_ticker,
            'studentUuid' => (string) $content->student?->uuid,
            'media' => null,
        ]);
        $this->resetErrorBag();
        $this->dispatch('open-modal', 'form-konten');
    }

    public function chooseStudent(string $uuid): void
    {
        $this->studentUuid = $uuid;
        $this->reset('student_search');
    }

    public function save(CompressProofImage $images): void
    {
        $this->resetErrorBag();
        $needsMedia = in_array($this->type, ['poster', 'qr_donation', 'video'], true);
        $this->validate([
            'type' => 'required|in:'.implode(',', array_keys(DisplayContent::TYPES)),
            'title' => 'required|string|max:120',
            'text' => in_array($this->type, ['text', 'agenda', 'obituary'], true) ? 'required|string|max:400' : 'nullable|string|max:400',
            'quote_id' => $this->type === 'quote' ? 'required|exists:quotes,id' : 'nullable',
            'media' => $needsMedia && ! $this->editing ? ($this->type === 'video' ? 'required|file|mimes:mp4,webm|max:20480' : 'required|image|max:8192') : 'nullable|file|max:20480',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after:starts_at',
            'days' => 'array',
            'days.*' => 'integer|between:1,7',
            'before_prayer' => 'nullable|in:fajr,dhuhr,asr,maghrib,isha',
            'priority' => 'required|integer|between:0,10',
            'duration' => 'required|integer|between:6,60',
        ], ['text.max' => 'Teks terlalu panjang untuk dibaca dari jauh. Maksimal 400 karakter, sekitar 40 kata.', 'quote_id.required' => 'Pilih ayat atau hadis yang sudah terverifikasi.'], ['title' => 'judul', 'text' => 'isi', 'media' => 'berkas', 'ends_at' => 'akhir tayang', 'duration' => 'durasi']);

        $zone = config('sanad.display_timezone');
        $student = Str::isUuid($this->studentUuid) ? Student::query()->where('uuid', $this->studentUuid)->first() : null;
        $values = [
            'type' => $this->type,
            'title' => $this->title,
            'body' => $this->type === 'quote' ? ['quote_id' => (int) $this->quote_id] : ['text' => trim($this->text)],
            'starts_at' => $this->starts_at ? CarbonImmutable::parse($this->starts_at, $zone)->utc() : null,
            'ends_at' => $this->ends_at ? CarbonImmutable::parse($this->ends_at, $zone)->utc() : null,
            'schedule_rule' => array_filter(['days' => array_map('intval', $this->days), 'before_prayer' => $this->before_prayer ?: null]),
            'priority' => (int) $this->priority,
            'duration_seconds' => (int) $this->duration,
            'is_public' => $this->is_public,
            'is_ticker' => $this->is_ticker,
            'student_id' => $student?->id,
        ];

        if ($this->media) {
            $values['media_path'] = $this->type === 'video' ? $this->media->store('display-media', 'local') : $images->store($this->media, 'display-media');
        }

        $content = $this->editing ? DisplayContent::query()->findOrFail($this->editing) : new DisplayContent(['created_by' => auth()->id()]);
        // Mengubah konten yang sudah tayang mengembalikannya ke persetujuan.
        $values['status'] = $content->exists && in_array($content->status, ['approved', 'pending'], true) ? 'pending' : ($content->status ?? 'draft');
        $content->fill($values)->save();
        $content->screens()->sync(DisplayScreen::query()->whereIn('uuid', $this->screens)->pluck('id'));

        $this->dispatch('close-modal', 'form-konten');
        $this->dispatch('toast', message: 'Konten tersimpan sebagai '.mb_strtolower(DisplayContent::STATUSES[$content->status]).'.', tone: 'success');
    }

    public function submit(string $uuid, ManageContent $manage): void
    {
        try {
            $manage->submit(DisplayContent::query()->where('uuid', $uuid)->firstOrFail());
            $this->dispatch('toast', message: 'Diajukan untuk disetujui.', tone: 'success');
        } catch (MasjidException $exception) {
            $this->dispatch('toast', message: $exception->getMessage(), tone: 'danger');
        }
    }

    public function approveContent(string $uuid, ManageContent $manage): void
    {
        $this->approve();

        try {
            $manage->approve(DisplayContent::query()->where('uuid', $uuid)->firstOrFail(), auth()->user());
            $this->dispatch('toast', message: 'Disetujui dan tayang sesuai jadwalnya.', tone: 'success');
        } catch (MasjidException $exception) {
            $this->dispatch('toast', message: $exception->getMessage(), tone: 'danger');
        }
    }

    public function askReject(string $uuid): void
    {
        $this->approve();
        $this->rejecting = DisplayContent::query()->where('uuid', $uuid)->firstOrFail()->uuid;
        $this->reset('reason');
        $this->resetErrorBag();
        $this->dispatch('open-modal', 'tolak-konten');
    }

    public function reject(ManageContent $manage): void
    {
        $this->approve();

        try {
            $manage->reject(DisplayContent::query()->where('uuid', (string) $this->rejecting)->firstOrFail(), $this->reason, auth()->user());
        } catch (MasjidException $exception) {
            $this->addError('reason', $exception->getMessage());

            return;
        }

        $this->dispatch('close-modal', 'tolak-konten');
    }

    public function archive(string $uuid): void
    {
        DisplayContent::query()->where('uuid', $uuid)->firstOrFail()->update(['status' => 'archived']);
    }

    public function sendEmergency(ManageContent $manage): void
    {
        $this->approve();
        $this->resetErrorBag();
        $this->validate(['emergency_text' => 'required|string|max:300', 'emergency_until' => 'required|date'], ['emergency_until.required' => 'Waktu akhir wajib diisi.'], ['emergency_text' => 'pesan']);

        try {
            $manage->sendEmergency($this->emergency_text, CarbonImmutable::parse($this->emergency_until, config('sanad.display_timezone'))->utc(), DisplayScreen::query()->whereIn('uuid', $this->emergency_screens)->pluck('id')->all(), auth()->user());
        } catch (MasjidException $exception) {
            $this->addError('emergency_until', $exception->getMessage());

            return;
        }

        $this->reset('emergency_text', 'emergency_until', 'emergency_screens');
        $this->dispatch('toast', message: 'Pesan darurat tayang di layar dalam beberapa detik.', tone: 'success');
    }

    public function clearEmergency(ManageContent $manage): void
    {
        $this->approve();
        $manage->clearEmergency(auth()->user());
        $this->dispatch('toast', message: 'Pesan darurat dicabut.', tone: 'success');
    }

    public function saveQuote(): void
    {
        $this->resetErrorBag();
        $this->validate(['q_ar' => 'required|string|max:1000', 'q_id' => 'required|string|max:600', 'q_ref' => 'required|string|max:150'], [], ['q_ar' => 'teks Arab', 'q_id' => 'terjemah', 'q_ref' => 'rujukan']);
        Quote::create(['text_ar' => $this->q_ar, 'text_id' => $this->q_id, 'source_ref' => $this->q_ref]);
        $this->reset('q_ar', 'q_id', 'q_ref');
    }

    public function verifyQuote(string $uuid): void
    {
        $this->approve();
        Quote::query()->where('uuid', $uuid)->firstOrFail()->update(['verified_by' => auth()->id(), 'verified_at' => now()]);
    }

    public function saveKhutbah(): void
    {
        $this->resetErrorBag();
        $this->validate(['k_date' => 'required|date', 'k_imam' => 'nullable|string|max:100', 'k_khatib' => 'required|string|max:100', 'k_theme' => 'nullable|string|max:150'], [], ['k_date' => 'tanggal', 'k_khatib' => 'khatib']);

        if (CarbonImmutable::parse($this->k_date)->dayOfWeekIso !== 5) {
            $this->addError('k_date', 'Tanggal itu bukan hari Jumat.');

            return;
        }

        KhutbahSchedule::query()->updateOrCreate(['date' => $this->k_date], ['imam_name' => $this->k_imam ?: null, 'khatib_name' => $this->k_khatib, 'theme' => $this->k_theme ?: null]);
        $this->reset('k_date', 'k_imam', 'k_khatib', 'k_theme');
    }

    public function saveKajian(): void
    {
        $this->resetErrorBag();
        $this->validate(['j_title' => 'required|string|max:120', 'j_speaker' => 'nullable|string|max:100', 'j_day' => 'required|integer|between:1,7', 'j_time' => 'required|date_format:H:i', 'j_location' => 'nullable|string|max:100'], [], ['j_title' => 'judul', 'j_time' => 'jam']);
        KajianSchedule::create(['title' => $this->j_title, 'speaker' => $this->j_speaker ?: null, 'day_of_week' => (int) $this->j_day, 'start_time' => $this->j_time, 'location' => $this->j_location ?: null]);
        $this->reset('j_title', 'j_speaker', 'j_time', 'j_location');
    }

    public function removeKajian(string $uuid): void
    {
        KajianSchedule::query()->where('uuid', $uuid)->firstOrFail()->delete();
    }

    public function render(): View
    {
        $term = trim($this->student_search);

        return view('masjid.contents', [
            'contents' => DisplayContent::query()->whereNot('type', 'emergency')->when($this->statusFilter !== '', fn ($query) => $query->where('status', $this->statusFilter))->latest('id')->paginate(20),
            'pending' => DisplayContent::query()->where('status', 'pending')->latest('id')->get(),
            'emergencies' => DisplayContent::query()->where('type', 'emergency')->where('status', 'approved')->where('ends_at', '>', now())->get(),
            'screenOptions' => DisplayScreen::query()->where('is_active', true)->orderBy('name')->pluck('name', 'uuid')->all(),
            'quotes' => Quote::query()->latest('id')->get(),
            'verifiedQuotes' => Quote::query()->whereNotNull('verified_at')->where('is_active', true)->get()->mapWithKeys(fn (Quote $quote) => [$quote->id => Str::limit($quote->text_id, 60).' ('.$quote->source_ref.')'])->all(),
            'khutbah' => KhutbahSchedule::query()->whereDate('date', '>=', now()->subWeek())->orderBy('date')->get(),
            'kajian' => KajianSchedule::query()->orderBy('day_of_week')->orderBy('start_time')->get(),
            'student' => Str::isUuid($this->studentUuid) ? Student::query()->where('uuid', $this->studentUuid)->first() : null,
            'studentResults' => mb_strlen($term) >= 2 ? Student::query()->where('status', 'active')->where('name', 'ilike', '%'.addcslashes($term, '%_').'%')->limit(5)->get() : collect(),
            'types' => DisplayContent::TYPES,
            'statuses' => DisplayContent::STATUSES,
            'canApprove' => auth()->user()->can('masjid.approve'),
            'days' => [1 => 'Sen', 2 => 'Sel', 3 => 'Rab', 4 => 'Kam', 5 => 'Jum', 6 => 'Sab', 7 => 'Ahd'],
        ]);
    }
}
