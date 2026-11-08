<?php

namespace App\Modules\Academic\Livewire;

use App\Modules\Academic\Actions\ManagePhases;
use App\Modules\Academic\Models\Curriculum;
use App\Modules\Academic\Models\CurriculumPhase;
use App\Modules\Academic\Models\PhaseResult;
use App\Modules\Academic\Support\AcademicException;
use App\Modules\Academic\Support\CertificateDesigns;
use App\Modules\Core\Models\Classroom;
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

    private const TABS = ['kurikulum', 'rombongan', 'evaluasi', 'desain'];

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

    // Evaluasi
    /** @var array<string, string> */
    public array $decisions = [];

    public string $promoteTo = '';

    public function mount(): void
    {
        abort_unless(in_array($this->tab, self::TABS, true), 404);
        $this->a_start = now(config('sanad.display_timezone'))->toDateString();
        $settings = CertificateDesigns::settings();
        $this->school_name_ar = $settings['school_name_ar'];
        $this->default_design = $settings['default_design'];
    }

    public function selectTab(string $tab): void
    {
        abort_unless(in_array($tab, self::TABS, true), 404);
        $this->tab = $tab;
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
        $this->reset('decisions', 'promoteTo');
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

    public function promote(ManagePhases $phases): void
    {
        $this->resetErrorBag();
        $classroom = $this->classroom();
        abort_if($classroom === null, 404);
        $target = Classroom::query()->where('uuid', $this->promoteTo)->first();

        if ($target === null) {
            $this->addError('promoteTo', 'Pilih kelas tujuan.');

            return;
        }

        try {
            $count = $phases->promote($classroom, $target);
        } catch (AcademicException $exception) {
            $this->addError('promoteTo', $exception->getMessage());

            return;
        }

        $this->dispatch('toast', message: "{$count} santri naik ke {$target->name}.", tone: 'success');
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
            'today' => now(config('sanad.display_timezone'))->toDateString(),
            'designs' => CertificateDesigns::ALL,
            'logoCount' => count(CertificateDesigns::settings()['logos']),
        ]);
    }
}
