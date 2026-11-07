<?php

namespace App\Modules\Letters\Livewire;

use App\Modules\Core\Models\Student;
use App\Modules\Letters\Actions\IssueLetter;
use App\Modules\Letters\Models\Letter;
use App\Modules\Letters\Models\Letterhead;
use App\Modules\Letters\Models\LetterTemplate;
use App\Modules\Letters\Support\DefaultTemplates;
use App\Modules\Letters\Support\LetterFields;
use App\Modules\Letters\Support\LetterSettings;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use RuntimeException;

/**
 * Surat keluar: buat dari template, arsip bernomor, template, kop, dan format nomor.
 */
#[Layout('components.layouts.staff', ['active' => 'letters'])]
#[Title('Surat')]
class LetterCenter extends Component
{
    use WithFileUploads, WithPagination;

    private const TABS = ['buat', 'arsip', 'template', 'pengaturan'];

    #[Url(as: 'tab', except: 'buat')]
    public string $tab = 'buat';

    // Buat surat
    public string $templateUuid = '';

    public string $studentSearch = '';

    public string $studentUuid = '';

    public string $recipient = '';

    /** @var array<string, string> */
    public array $extra = [];

    public string $issued_on = '';

    public string $signer_name = '';

    public string $signer_title = '';

    public string $letterheadUuid = '';

    public string $subject = '';

    public string $body = '';

    #[Locked]
    public ?string $issuedUuid = null;

    // Arsip
    #[Url(as: 'cari', except: '')]
    public string $search = '';

    #[Locked]
    public ?string $voiding = null;

    public string $voidReason = '';

    // Template
    #[Locked]
    public ?string $editingTemplate = null;

    public string $t_name = '';

    public string $t_code = '';

    public string $t_subject = '';

    public string $t_body = '';

    public bool $t_needs_student = false;

    public string $t_fields = '';

    // Pengaturan
    public string $number_format = '';

    public string $number_digits = '3';

    public string $city = '';

    public string $default_signer_name = '';

    public string $default_signer_title = '';

    public string $kop_name = '';

    /** @var TemporaryUploadedFile|null */
    public $kop_header = null;

    /** @var TemporaryUploadedFile|null */
    public $kop_footer = null;

    public function mount(): void
    {
        abort_unless(in_array($this->tab, self::TABS, true), 404);
        DefaultTemplates::ensure();
        $settings = LetterSettings::all();
        $this->fill([
            'issued_on' => now(config('sanad.display_timezone'))->toDateString(),
            'signer_name' => $settings['signer_name'],
            'signer_title' => $settings['signer_title'],
            'number_format' => $settings['number_format'],
            'number_digits' => (string) $settings['number_digits'],
            'city' => $settings['city'],
            'default_signer_name' => $settings['signer_name'],
            'default_signer_title' => $settings['signer_title'],
            'letterheadUuid' => (string) Letterhead::default()?->uuid,
        ]);
    }

    public function selectTab(string $tab): void
    {
        abort_unless(in_array($tab, self::TABS, true), 404);
        $this->tab = $tab;
        $this->resetPage();
    }

    private function template(): ?LetterTemplate
    {
        return Str::isUuid($this->templateUuid) ? LetterTemplate::query()->where('uuid', $this->templateUuid)->where('is_active', true)->first() : null;
    }

    private function student(): ?Student
    {
        return Str::isUuid($this->studentUuid) ? Student::query()->with(['classroom:id,name', 'dormitory:id,name'])->where('uuid', $this->studentUuid)->first() : null;
    }

    public function pickTemplate(string $uuid): void
    {
        $this->templateUuid = $uuid;
        $template = $this->template();
        abort_if($template === null, 404);
        $this->subject = $template->subject;
        $this->body = $template->body;
        $this->extra = collect($template->fields)->mapWithKeys(fn (array $field) => [$field['key'] => $this->extra[$field['key']] ?? ''])->all();
        $this->issuedUuid = null;
        $this->resetErrorBag();
    }

    public function pickStudent(string $uuid): void
    {
        $this->studentUuid = Student::query()->where('uuid', $uuid)->firstOrFail()->uuid;
        $this->studentSearch = '';
    }

    public function clearStudent(): void
    {
        $this->studentUuid = '';
    }

    public function issue(IssueLetter $letters): void
    {
        $template = $this->template();
        abort_if($template === null, 404);
        $this->resetErrorBag();
        $this->validate([
            'issued_on' => 'required|date',
            'recipient' => 'nullable|string|max:200',
            'subject' => 'required|string|max:200',
            'body' => 'required|string|max:10000',
            'extra.*' => 'nullable|string|max:300',
        ], [], ['issued_on' => 'tanggal surat', 'recipient' => 'kepada', 'subject' => 'perihal', 'body' => 'isi surat']);

        $letterhead = Str::isUuid($this->letterheadUuid) ? Letterhead::query()->where('uuid', $this->letterheadUuid)->first() : null;

        try {
            $letter = $letters->handle($template, $this->student(), $this->recipient, array_map('strval', $this->extra), $this->issued_on, $this->signer_name, $this->signer_title, $letterhead, auth()->user(), $this->subject, $this->body);
        } catch (RuntimeException $exception) {
            $this->addError('issue', $exception->getMessage());

            return;
        }

        $this->issuedUuid = $letter->uuid;
        $this->dispatch('toast', message: 'Surat '.$letter->letter_no.' terbit dan tersimpan di arsip.', tone: 'success');
    }

    public function startNew(): void
    {
        $this->reset('templateUuid', 'studentUuid', 'recipient', 'extra', 'subject', 'body', 'issuedUuid');
    }

    public function askVoid(string $uuid): void
    {
        $this->voiding = Letter::query()->where('uuid', $uuid)->where('status', 'issued')->firstOrFail()->uuid;
        $this->reset('voidReason');
        $this->resetErrorBag();
        $this->dispatch('open-modal', 'batal-surat');
    }

    public function confirmVoid(IssueLetter $letters): void
    {
        try {
            $letters->void(Letter::query()->where('uuid', $this->voiding)->firstOrFail(), $this->voidReason);
        } catch (RuntimeException $exception) {
            $this->addError('voidReason', $exception->getMessage());

            return;
        }

        $this->dispatch('close-modal', 'batal-surat');
        $this->dispatch('toast', message: 'Surat dibatalkan. Nomornya tetap tercatat dan tidak dipakai ulang.', tone: 'success');
    }

    public function editTemplate(?string $uuid = null): void
    {
        $template = $uuid ? LetterTemplate::query()->where('uuid', $uuid)->firstOrFail() : null;
        $this->editingTemplate = $template?->uuid;
        $this->fill([
            't_name' => (string) $template?->name,
            't_code' => (string) $template?->code,
            't_subject' => (string) $template?->subject,
            't_body' => (string) $template?->body,
            't_needs_student' => (bool) $template?->needs_student,
            't_fields' => collect($template?->fields ?? [])->pluck('label')->implode("\n"),
        ]);
        $this->resetErrorBag();
        $this->dispatch('open-modal', 'form-template');
    }

    public function saveTemplate(): void
    {
        $this->validate([
            't_name' => 'required|string|max:100',
            't_code' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9.\-]+$/'],
            't_subject' => 'required|string|max:200',
            't_body' => 'required|string|max:10000',
            't_fields' => 'nullable|string|max:2000',
        ], ['t_code.regex' => 'Kode hanya huruf, angka, titik, atau tanda hubung.'], ['t_name' => 'nama', 't_code' => 'kode', 't_subject' => 'perihal', 't_body' => 'isi']);

        $fields = collect(preg_split('/\R/', $this->t_fields) ?: [])->map(fn ($line) => trim((string) $line))->filter()
            ->map(fn (string $label) => ['key' => Str::slug($label, '_'), 'label' => $label])
            ->reject(fn (array $field) => $field['key'] === '' || isset(LetterFields::BUILT_IN[$field['key']]))
            ->unique('key')->values()->all();

        $data = ['name' => trim($this->t_name), 'code' => strtoupper(trim($this->t_code)), 'subject' => trim($this->t_subject), 'body' => trim($this->t_body), 'needs_student' => $this->t_needs_student, 'fields' => $fields];
        $this->editingTemplate
            ? LetterTemplate::query()->where('uuid', $this->editingTemplate)->firstOrFail()->update($data)
            : LetterTemplate::create($data);

        $this->dispatch('close-modal', 'form-template');
        $this->dispatch('toast', message: 'Template tersimpan.', tone: 'success');
    }

    public function toggleTemplate(string $uuid): void
    {
        $template = LetterTemplate::query()->where('uuid', $uuid)->firstOrFail();
        $template->update(['is_active' => ! $template->is_active]);
    }

    public function saveSettings(): void
    {
        $this->validate([
            'number_format' => ['required', 'string', 'max:100', 'regex:/\{nomor\}/'],
            'number_digits' => 'required|integer|min:1|max:6',
            'city' => 'nullable|string|max:60',
            'default_signer_name' => 'nullable|string|max:100',
            'default_signer_title' => 'required|string|max:100',
        ], ['number_format.regex' => 'Format wajib memuat {nomor}.'], ['number_format' => 'format nomor', 'number_digits' => 'jumlah digit', 'city' => 'kota', 'default_signer_title' => 'jabatan']);

        LetterSettings::save([
            'number_format' => trim($this->number_format),
            'number_digits' => (int) $this->number_digits,
            'city' => trim($this->city),
            'signer_name' => trim($this->default_signer_name),
            'signer_title' => trim($this->default_signer_title),
        ]);
        $this->dispatch('toast', message: 'Pengaturan surat tersimpan.', tone: 'success');
    }

    public function saveLetterhead(): void
    {
        $this->validate([
            'kop_name' => 'required|string|max:80',
            'kop_header' => 'nullable|image|mimes:png,jpg,jpeg|max:2048',
            'kop_footer' => 'nullable|image|mimes:png,jpg,jpeg|max:2048',
        ], [], ['kop_name' => 'nama kop', 'kop_header' => 'gambar kop', 'kop_footer' => 'gambar footer']);

        if (! $this->kop_header && ! $this->kop_footer) {
            $this->addError('kop_header', 'Unggah gambar kop, footer, atau keduanya.');

            return;
        }

        DB::transaction(function (): void {
            $isFirst = ! Letterhead::query()->exists();
            Letterhead::create([
                'name' => trim($this->kop_name),
                'header_path' => $this->kop_header?->store('letterheads', 'local'),
                'footer_path' => $this->kop_footer?->store('letterheads', 'local'),
                'is_default' => $isFirst,
            ]);
        });

        $this->reset('kop_name', 'kop_header', 'kop_footer');
        $this->letterheadUuid = (string) Letterhead::default()?->uuid;
        $this->dispatch('toast', message: 'Kop tersimpan.', tone: 'success');
    }

    public function makeDefault(string $uuid): void
    {
        DB::transaction(function () use ($uuid): void {
            Letterhead::query()->where('is_default', true)->update(['is_default' => false]);
            Letterhead::query()->where('uuid', $uuid)->firstOrFail()->update(['is_default' => true]);
        });
    }

    public function deleteLetterhead(string $uuid): void
    {
        $letterhead = Letterhead::query()->where('uuid', $uuid)->firstOrFail();
        // Surat lama tetap memakai kop ini, jadi gambarnya disimpan bila sudah pernah dipakai.
        if (! Letter::query()->where('letterhead_id', $letterhead->id)->exists()) {
            Storage::disk('local')->delete(array_filter([$letterhead->header_path, $letterhead->footer_path]));
            $letterhead->delete();
        } else {
            $this->addError('kop', 'Kop ini sudah dipakai surat terbit, jadi tidak bisa dihapus. Jadikan kop lain sebagai utama.');
        }
    }

    public function render(): View
    {
        $template = $this->template();
        $student = $this->student();
        $preview = null;

        if ($template && $this->tab === 'buat') {
            $date = CarbonImmutable::parse($this->issued_on ?: now()->toDateString(), config('sanad.display_timezone'));
            $values = LetterFields::values($student, $date, IssueLetter::nextPreview($template->code, $date->toDateString()), $this->recipient, $this->signer_name, $this->signer_title, LetterSettings::all()['city'], array_map('strval', $this->extra));
            $preview = [
                'number' => $values['nomor_surat'],
                'subject' => LetterFields::fill($this->subject, $values),
                'body' => LetterFields::fill($this->body, $values),
                'missing' => LetterFields::missing($this->subject.' '.$this->body, $values),
                'date' => $values['tanggal_surat'],
            ];
        }

        $term = trim($this->studentSearch);

        return view('letters.center', [
            'templates' => LetterTemplate::query()->orderBy('name')->get(),
            'template' => $template,
            'student' => $student,
            'studentResults' => mb_strlen($term) >= 2 ? Student::query()->where('status', 'active')->where(fn ($q) => $q->where('name', 'ilike', '%'.addcslashes($term, '%_').'%')->orWhere('nis', 'like', addcslashes($term, '%_').'%'))->with('classroom:id,name')->orderBy('name')->limit(6)->get() : collect(),
            'preview' => $preview,
            'issued' => $this->issuedUuid ? Letter::query()->where('uuid', $this->issuedUuid)->first() : null,
            'letters' => $this->tab === 'arsip' ? Letter::query()->with('student:id,name')
                ->when(trim($this->search) !== '', fn ($q) => $q->where(fn ($w) => $w->where('letter_no', 'ilike', '%'.addcslashes(trim($this->search), '%_').'%')->orWhere('subject', 'ilike', '%'.addcslashes(trim($this->search), '%_').'%')->orWhere('recipient', 'ilike', '%'.addcslashes(trim($this->search), '%_').'%')->orWhereHas('student', fn ($s) => $s->where('name', 'ilike', '%'.addcslashes(trim($this->search), '%_').'%'))))
                ->latest('issued_on')->latest('id')->paginate(20) : null,
            'letterheads' => Letterhead::query()->orderByDesc('is_default')->orderBy('name')->get(),
            'builtIn' => LetterFields::BUILT_IN,
            'numberExample' => LetterSettings::formatNumber($this->number_format ?: LetterSettings::DEFAULTS['number_format'], 7, (int) $this->number_digits ?: 3, 'SKS', (int) now(config('sanad.display_timezone'))->month, (int) now(config('sanad.display_timezone'))->year),
        ]);
    }
}
