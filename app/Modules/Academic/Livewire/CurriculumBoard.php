<?php

namespace App\Modules\Academic\Livewire;

use App\Modules\Academic\Actions\ManagePhases;
use App\Modules\Academic\Models\AcademicBreak;
use App\Modules\Academic\Models\Curriculum;
use App\Modules\Academic\Models\CurriculumPhase;
use App\Modules\Academic\Models\PhaseResult;
use App\Modules\Academic\Support\AcademicException;
use App\Modules\Academic\Support\CertificateDesigns;
use App\Modules\Academic\Support\PhaseCalendar;
use App\Modules\Core\Models\Classroom;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Kurikulum berbasis fase: susun fase, tempatkan rombongan, evaluasi, sertifikat, naik fase.
 */
#[Layout('components.layouts.staff', ['active' => 'curriculum'])]
#[Title('Kurikulum')]
class CurriculumBoard extends Component
{
    use WithFileUploads;

    public const MAX_LOGOS = 4;

    /** @var TemporaryUploadedFile|null */
    public $newLogo = null;

    private const TABS = ['kurikulum', 'rombongan', 'jeda', 'evaluasi', 'desain'];

    #[Url(as: 'tab', except: 'kurikulum')]
    public string $tab = 'kurikulum';

    #[Url(as: 'kelas', except: '')]
    public string $classroomUuid = '';

    // Kurikulum
    public string $c_name = '';

    #[Locked]
    public ?string $phaseCurriculum = null;

    #[Locked]
    public ?string $editingPhase = null;

    public string $p_name = '';

    public string $p_code = '';

    public string $p_weeks = '12';

    public string $p_min = '70';

    public bool $p_certificate = true;

    public bool $p_back = false;

    public string $p_certificate_title = '';

    public string $p_description = '';

    public string $p_design = '';

    public string $p_title_ar = '';

    // Desain sertifikat
    public string $school_name_ar = '';

    public string $default_design = '';

    // Rombongan
    public string $a_classroom = '';

    public string $a_phase = '';

    public string $a_start = '';

    /** @var array<string, string> uuid kelas => uuid fase; kosong berarti tidak diubah */
    public array $together = [];

    // Kalender libur
    #[Locked]
    public ?string $editingBreak = null;

    public string $b_name = '';

    public string $b_kind = 'special';

    public string $b_start = '';

    public string $b_end = '';

    // Evaluasi
    /** @var array<string, string> */
    public array $decisions = [];

    public string $promoteTo = '';

    /** @var array<string, string> uuid hasil fase => uuid kelas tujuan */
    public array $mapping = [];

    public string $mapAll = '';

    public function mount(): void
    {
        abort_unless(in_array($this->tab, self::TABS, true), 404);
        $this->a_start = $this->nextStart();
        $this->together = $this->suggestedPlan();
        $settings = CertificateDesigns::settings();
        $this->school_name_ar = $settings['school_name_ar'];
        $this->default_design = $settings['default_design'];
    }

    public function selectTab(string $tab): void
    {
        abort_unless(in_array($tab, self::TABS, true), 404);
        $this->tab = $tab;
        if ($tab === 'rombongan') {
            $this->a_start = $this->nextStart();
            $this->together = $this->suggestedPlan();
        }
    }

    /**
     * Saran tanggal mulai serentak: sehari setelah fase berjalan terakhir selesai, atau hari ini.
     */
    private function nextStart(): string
    {
        $last = Classroom::query()->whereNotNull('phase_ends_on')->max('phase_ends_on');
        $today = now(config('sanad.display_timezone'))->toDateString();
        $next = $last ? CarbonImmutable::parse($last)->addDay()->toDateString() : $today;

        return max($next, $today);
    }

    /**
     * Saran rencana: kelas yang sedang di sebuah fase disarankan ke fase berikutnya di kurikulumnya.
     *
     * @return array<string, string>
     */
    private function suggestedPlan(): array
    {
        return Classroom::query()->with('phase')->orderBy('name')->get()
            ->mapWithKeys(fn (Classroom $room) => [$room->uuid => (string) $room->phase?->next()?->uuid])->all();
    }

    /**
     * Mulai fase serentak untuk kelas yang dipilih, dengan satu tanggal mulai.
     */
    public function startTogether(ManagePhases $phases): void
    {
        $this->resetErrorBag();
        $this->validate(['a_start' => 'required|date', 'together' => 'array', 'together.*' => 'nullable|uuid'], [], ['a_start' => 'tanggal mulai']);
        $chosen = array_filter($this->together);
        if ($chosen === []) {
            $this->addError('together', 'Pilih fase untuk minimal satu kelas.');

            return;
        }

        $rooms = Classroom::query()->whereIn('uuid', array_keys($chosen))->pluck('id', 'uuid');
        $phaseModels = CurriculumPhase::query()->whereIn('uuid', array_values($chosen))->get()->keyBy('uuid');
        $plan = [];
        foreach ($chosen as $roomUuid => $phaseUuid) {
            if (isset($rooms[$roomUuid], $phaseModels[$phaseUuid])) {
                $plan[$rooms[$roomUuid]] = $phaseModels[$phaseUuid];
            }
        }

        $count = $phases->startTogether($plan, $this->a_start);
        $this->together = $this->suggestedPlan();
        $this->dispatch('toast', message: "{$count} kelas mulai fase pada ".CarbonImmutable::parse($this->a_start)->translatedFormat('j F Y').'.', tone: 'success');
    }

    public function newBreak(): void
    {
        $this->reset('editingBreak', 'b_name', 'b_kind', 'b_start', 'b_end');
        $this->resetErrorBag();
        $this->dispatch('open-modal', 'form-libur');
    }

    /**
     * Mengisi form libur dengan tanggal Ramadan dari pengaturan Masjid atau perkiraan hijriah.
     */
    public function suggestRamadan(): void
    {
        $suggestion = PhaseCalendar::ramadanSuggestion(CarbonImmutable::parse(now(config('sanad.display_timezone'))->toDateString()));
        $this->reset('editingBreak');
        $this->resetErrorBag();
        if ($suggestion === null) {
            $this->dispatch('toast', message: 'Tanggal Ramadan belum bisa diperkirakan. Isi manual.', tone: 'warning');
            $this->reset('b_name', 'b_kind', 'b_start', 'b_end');
        } else {
            [$this->b_name, $this->b_kind, $this->b_start, $this->b_end] = [$suggestion['name'], $suggestion['kind'], $suggestion['starts_on'], $suggestion['ends_on']];
            $this->dispatch('toast', message: 'Tanggal diambil dari '.$suggestion['source'].'. Periksa lalu simpan.', tone: 'info');
        }
        $this->dispatch('open-modal', 'form-libur');
    }

    public function editBreak(string $uuid): void
    {
        $break = AcademicBreak::query()->where('uuid', $uuid)->firstOrFail();
        $this->editingBreak = $break->uuid;
        [$this->b_name, $this->b_kind, $this->b_start, $this->b_end] = [$break->name, $break->kind, $break->starts_on->toDateString(), $break->ends_on->toDateString()];
        $this->resetErrorBag();
        $this->dispatch('open-modal', 'form-libur');
    }

    public function saveBreak(ManagePhases $phases): void
    {
        $this->validate(['b_name' => 'required|string|max:100', 'b_kind' => 'required|in:'.implode(',', array_keys(AcademicBreak::KINDS)), 'b_start' => 'required|date', 'b_end' => 'required|date|after_or_equal:b_start'], [], ['b_name' => 'nama', 'b_kind' => 'jenis', 'b_start' => 'tanggal mulai', 'b_end' => 'tanggal selesai']);
        $data = ['name' => trim($this->b_name), 'kind' => $this->b_kind, 'starts_on' => $this->b_start, 'ends_on' => $this->b_end];
        $this->editingBreak ? AcademicBreak::query()->where('uuid', $this->editingBreak)->firstOrFail()->update($data) : AcademicBreak::create($data);

        $moved = $phases->recalculateEnds();
        $this->dispatch('close-modal', 'form-libur');
        $this->dispatch('toast', message: 'Jeda fase tersimpan.'.($moved > 0 ? " Tanggal selesai fase {$moved} kelas disesuaikan." : ''), tone: 'success');
        $this->reset('editingBreak', 'b_name', 'b_kind', 'b_start', 'b_end');
    }

    public function deleteBreak(string $uuid, ManagePhases $phases): void
    {
        AcademicBreak::query()->where('uuid', $uuid)->firstOrFail()->delete();
        $moved = $phases->recalculateEnds();
        $this->dispatch('toast', message: 'Jeda fase dihapus.'.($moved > 0 ? " Tanggal selesai fase {$moved} kelas disesuaikan." : ''), tone: 'success');
    }

    public function saveCurriculum(): void
    {
        $this->validate(['c_name' => 'required|string|max:100'], [], ['c_name' => 'nama kurikulum']);
        Curriculum::create(['name' => trim($this->c_name)]);
        $this->reset('c_name');
        $this->dispatch('toast', message: 'Kurikulum dibuat. Tambahkan fasenya.', tone: 'success');
    }

    public function newPhase(string $curriculumUuid): void
    {
        $this->phaseCurriculum = Curriculum::query()->where('uuid', $curriculumUuid)->firstOrFail()->uuid;
        $this->editingPhase = null;
        $this->reset('p_name', 'p_code', 'p_description', 'p_certificate_title', 'p_design', 'p_title_ar');
        $this->fill(['p_weeks' => '12', 'p_min' => '70', 'p_certificate' => true, 'p_back' => false]);
        $this->resetErrorBag();
        $this->dispatch('open-modal', 'form-fase');
    }

    public function editPhase(string $uuid): void
    {
        $phase = CurriculumPhase::query()->with('curriculum:id,uuid')->where('uuid', $uuid)->firstOrFail();
        $this->phaseCurriculum = $phase->curriculum->uuid;
        $this->editingPhase = $phase->uuid;
        $this->fill([
            'p_name' => $phase->name, 'p_code' => $phase->code, 'p_weeks' => (string) $phase->duration_weeks, 'p_min' => (string) (float) $phase->min_score,
            'p_certificate' => $phase->has_certificate, 'p_certificate_title' => (string) $phase->certificate_title, 'p_description' => (string) $phase->description,
            'p_design' => (string) $phase->certificate_design, 'p_back' => $phase->certificate_back, 'p_title_ar' => (string) $phase->certificate_title_ar,
        ]);
        $this->resetErrorBag();
        $this->dispatch('open-modal', 'form-fase');
    }

    public function savePhase(ManagePhases $phases): void
    {
        $this->validate([
            'p_name' => 'required|string|max:100',
            'p_code' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9\-]+$/'],
            'p_weeks' => 'required|integer|min:1|max:520',
            'p_min' => 'required|numeric|min:0|max:100',
            'p_certificate_title' => 'nullable|string|max:100',
            'p_description' => 'nullable|string|max:500',
            'p_design' => 'nullable|in:'.implode(',', array_keys(CertificateDesigns::ALL)),
            'p_title_ar' => 'nullable|string|max:120',
        ], ['p_code.regex' => 'Kode hanya huruf, angka, atau tanda hubung.'], ['p_name' => 'nama fase', 'p_code' => 'kode', 'p_weeks' => 'durasi', 'p_min' => 'nilai lulus']);

        $curriculum = Curriculum::query()->where('uuid', $this->phaseCurriculum)->firstOrFail();
        $phases->savePhase($curriculum, [
            'name' => trim($this->p_name),
            'code' => strtoupper(trim($this->p_code)),
            'duration_weeks' => (int) $this->p_weeks,
            'min_score' => (float) $this->p_min,
            'has_certificate' => $this->p_certificate,
            'certificate_title' => $this->p_certificate_title ?: null,
            'description' => $this->p_description ?: null,
            'certificate_design' => $this->p_design ?: null,
            'certificate_back' => $this->p_back,
            'certificate_title_ar' => trim($this->p_title_ar) ?: null,
        ], $this->editingPhase ? CurriculumPhase::query()->where('uuid', $this->editingPhase)->where('curriculum_id', $curriculum->id)->firstOrFail() : null);

        $this->dispatch('close-modal', 'form-fase');
        $this->dispatch('toast', message: 'Fase tersimpan.', tone: 'success');
    }

    public function saveDesignSettings(): void
    {
        $this->validate(['school_name_ar' => 'nullable|string|max:150', 'default_design' => 'required|in:'.implode(',', array_keys(CertificateDesigns::ALL))], [], ['school_name_ar' => 'nama pesantren dalam bahasa Arab']);
        CertificateDesigns::save(['school_name_ar' => trim($this->school_name_ar), 'default_design' => $this->default_design]);
        $this->dispatch('toast', message: 'Pengaturan sertifikat tersimpan.', tone: 'success');
    }

    public function addLogo(): void
    {
        $this->validate(['newLogo' => 'required|image|mimes:png,jpg,jpeg,webp|max:2048'], [], ['newLogo' => 'logo']);
        $logos = CertificateDesigns::settings()['logos'];

        if (count($logos) >= self::MAX_LOGOS) {
            $this->addError('newLogo', 'Paling banyak '.self::MAX_LOGOS.' logo.');

            return;
        }

        CertificateDesigns::save(['logos' => [...$logos, $this->newLogo->store('certificate-logos', 'local')]]);
        $this->reset('newLogo');
        $this->dispatch('toast', message: 'Logo ditambahkan ke sertifikat.', tone: 'success');
    }

    public function removeLogo(int $index): void
    {
        $logos = CertificateDesigns::settings()['logos'];
        abort_unless(isset($logos[$index]), 404);
        Storage::disk('local')->delete($logos[$index]);
        unset($logos[$index]);
        CertificateDesigns::save(['logos' => array_values($logos)]);
    }

    public function moveLogo(int $index, int $direction): void
    {
        $logos = CertificateDesigns::settings()['logos'];
        $target = $index + ($direction < 0 ? -1 : 1);
        abort_unless(isset($logos[$index], $logos[$target]), 404);
        [$logos[$index], $logos[$target]] = [$logos[$target], $logos[$index]];
        CertificateDesigns::save(['logos' => $logos]);
    }

    public function useDesign(string $key): void
    {
        abort_unless(isset(CertificateDesigns::ALL[$key]), 404);
        $this->default_design = $key;
        CertificateDesigns::save(['default_design' => $key]);
        $this->dispatch('toast', message: CertificateDesigns::ALL[$key]['name'].' jadi desain bawaan.', tone: 'success');
    }

    public function movePhase(string $uuid, int $direction, ManagePhases $phases): void
    {
        $phases->move(CurriculumPhase::query()->where('uuid', $uuid)->firstOrFail(), $direction < 0 ? -1 : 1);
    }

    public function assign(ManagePhases $phases): void
    {
        $this->validate(['a_classroom' => 'required|uuid', 'a_phase' => 'required|uuid', 'a_start' => 'required|date'], [], ['a_classroom' => 'kelas', 'a_phase' => 'fase', 'a_start' => 'tanggal mulai']);
        $classroom = Classroom::query()->where('uuid', $this->a_classroom)->firstOrFail();
        $phase = CurriculumPhase::query()->where('uuid', $this->a_phase)->firstOrFail();
        $phases->assign($classroom, $phase, $this->a_start);
        $this->reset('a_classroom');
        $this->dispatch('toast', message: $classroom->name.' masuk '.$phase->name.' sampai '.$classroom->phase_ends_on->translatedFormat('j M Y').'.', tone: 'success');
    }

    public function openEvaluation(string $uuid): void
    {
        $this->classroomUuid = Classroom::query()->where('uuid', $uuid)->whereNotNull('phase_id')->firstOrFail()->uuid;
        $this->tab = 'evaluasi';
        $this->reset('decisions', 'promoteTo', 'mapAll');
        $this->prefillMapping();
    }

    /**
     * Bila fase berikutnya hanya punya satu kelas, semua santri lulus langsung diarahkan ke sana; admin tinggal mengubah yang berbeda jalur.
     */
    private function prefillMapping(): void
    {
        $classroom = $this->classroom();
        $next = $classroom?->phase?->next();
        $only = $next ? Classroom::query()->where('phase_id', $next->id)->pluck('uuid') : collect();
        $this->mapping = $classroom ? $this->results($classroom)->where('status', 'passed')->whereNull('promoted_at')
            ->mapWithKeys(fn (PhaseResult $result) => [$result->uuid => $this->mapping[$result->uuid] ?? ($only->count() === 1 ? $only->first() : '')])->all() : [];
    }

    public function applyMapAll(): void
    {
        foreach (array_keys($this->mapping) as $resultUuid) {
            $this->mapping[$resultUuid] = $this->mapAll;
        }
    }

    private function classroom(): ?Classroom
    {
        return Str::isUuid($this->classroomUuid) ? Classroom::query()->with('phase.curriculum')->where('uuid', $this->classroomUuid)->whereNotNull('phase_id')->first() : null;
    }

    public function evaluate(ManagePhases $phases): void
    {
        $this->resetErrorBag();
        $classroom = $this->classroom();
        abort_if($classroom === null, 404);

        try {
            $count = $phases->evaluate($classroom);
        } catch (AcademicException $exception) {
            $this->addError('evaluate', $exception->getMessage());

            return;
        }

        $this->dispatch('toast', message: "Nilai {$count} santri dihitung ulang.", tone: 'success');
    }

    public function suggest(): void
    {
        $classroom = $this->classroom();
        abort_if($classroom === null, 404);
        $min = (float) $classroom->phase->min_score;
        $this->decisions = $this->results($classroom)->where('status', 'pending')->whereNotNull('final_score')
            ->mapWithKeys(fn (PhaseResult $result) => [$result->uuid => $result->final_score >= $min ? 'passed' : 'failed'])->all();
    }

    public function decide(ManagePhases $phases): void
    {
        $classroom = $this->classroom();
        abort_if($classroom === null, 404);
        $count = $phases->decide($classroom, $this->decisions, auth()->user());
        $this->reset('decisions');
        $this->prefillMapping();
        $this->dispatch('toast', message: "{$count} keputusan tersimpan.", tone: 'success');
    }

    public function certify(ManagePhases $phases): void
    {
        $classroom = $this->classroom();
        abort_if($classroom === null, 404);

        try {
            $count = $phases->certify($classroom);
        } catch (AcademicException $exception) {
            $this->addError('evaluate', $exception->getMessage());

            return;
        }

        $this->dispatch('toast', message: "{$count} sertifikat diberi nomor.", tone: 'success');
    }

    /**
     * Menaikkan santri lulus sesuai pemetaan per santri (atau satu kelas tujuan untuk semua lewat promoteTo).
     */
    public function promote(ManagePhases $phases): void
    {
        $this->resetErrorBag();
        $classroom = $this->classroom();
        abort_if($classroom === null, 404);

        $chosen = $this->promoteTo !== '' ? array_fill_keys(array_keys($this->mapping) ?: $this->results($classroom)->where('status', 'passed')->whereNull('promoted_at')->pluck('uuid')->all(), $this->promoteTo) : array_filter($this->mapping);
        if ($chosen === []) {
            $this->addError('promoteTo', 'Pilih kelas tujuan untuk minimal satu santri.');

            return;
        }

        $resultIds = PhaseResult::query()->whereIn('uuid', array_keys($chosen))->pluck('id', 'uuid');
        $roomIds = Classroom::query()->whereIn('uuid', array_unique(array_values($chosen)))->pluck('id', 'uuid');
        $map = [];
        foreach ($chosen as $resultUuid => $roomUuid) {
            if (isset($resultIds[$resultUuid], $roomIds[$roomUuid])) {
                $map[$resultIds[$resultUuid]] = $roomIds[$roomUuid];
            }
        }

        try {
            $count = $phases->promoteMapped($classroom, $map);
        } catch (AcademicException $exception) {
            $this->addError('promoteTo', $exception->getMessage());

            return;
        }

        $this->reset('promoteTo', 'mapAll');
        $this->prefillMapping();
        $this->dispatch('toast', message: "{$count} santri dipindahkan ke kelas tujuannya.", tone: 'success');
    }

    private function results(Classroom $classroom)
    {
        return PhaseResult::query()->with('student:id,name,nis')->where('classroom_id', $classroom->id)->where('phase_id', $classroom->phase_id)
            ->where('started_on', $classroom->phase_started_on)->get()->sortBy('student.name')->values();
    }

    public function render(): View
    {
        $classroom = $this->tab === 'evaluasi' ? $this->classroom() : null;
        $next = $classroom?->phase?->next();

        return view('academic.curriculum', [
            'curricula' => Curriculum::query()->with(['phases' => fn ($q) => $q->withCount('classrooms')])->orderBy('name')->get(),
            'classrooms' => Classroom::query()->with('phase.curriculum')->withCount(['students' => fn ($q) => $q->where('status', 'active')])->orderBy('name')->get(),
            'phaseOptions' => CurriculumPhase::query()->with('curriculum:id,name')->orderBy('curriculum_id')->orderBy('sort_order')->get()->mapWithKeys(fn (CurriculumPhase $phase) => [$phase->uuid => $phase->curriculum->name.': '.$phase->name])->all(),
            'classroom' => $classroom,
            'results' => $classroom ? $this->results($classroom) : collect(),
            'next' => $next,
            'targets' => $next ? Classroom::query()->where('phase_id', $next->id)->orderBy('name')->pluck('name', 'uuid')->all() : [],
            // Semua kelas yang sudah ditempatkan di fase bisa jadi tujuan, termasuk jalur di kurikulum lain (kitab, tahfidz).
            'mapTargets' => $classroom ? Classroom::query()->with('phase.curriculum')->whereNotNull('phase_id')->whereKeyNot($classroom->id)->orderBy('name')->get()
                ->mapWithKeys(fn (Classroom $room) => [$room->uuid => $room->name.' ('.$room->phase->curriculum->name.', '.$room->phase->name.')'])->all() : [],
            'breaks' => AcademicBreak::query()->orderBy('starts_on')->get(),
            'today' => now(config('sanad.display_timezone'))->toDateString(),
            'designs' => CertificateDesigns::ALL,
            'logoCount' => count(CertificateDesigns::settings()['logos']),
        ]);
    }
}
