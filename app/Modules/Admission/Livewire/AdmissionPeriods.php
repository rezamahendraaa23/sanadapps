<?php

namespace App\Modules\Admission\Livewire;

use App\Modules\Admission\Models\AdmissionPeriod;
use App\Modules\Admission\Models\AdmissionStep;
use App\Modules\Core\Models\AcademicYear;
use App\Modules\Exam\Models\Exam;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Pengaturan PPDB (PD-01).
 */
#[Layout('components.layouts.staff', ['active' => 'ppdb-periods'])]
#[Title('Periode PPDB')]
class AdmissionPeriods extends Component
{
    #[Locked]
    public ?int $editing = null;

    public string $name = '';

    public string $academic_year_id = '';

    public string $open_at = '';

    public string $close_at = '';

    public string $quota = '';

    public string $registration_fee = '0';

    public string $step_period = '';

    public string $step_name = '';

    public string $step_type = 'document';

    public string $step_weight = '';

    public string $step_exam = '';

    private function authorizeManage(): void
    {
        abort_unless(auth()->user()->can('ppdb.manage'), 403);
    }

    public function create(): void
    {
        $this->authorizeManage();
        $this->reset('editing', 'name', 'academic_year_id', 'open_at', 'close_at', 'quota', 'registration_fee');
        $this->resetErrorBag();
        $this->dispatch('open-modal', 'form-periode');
    }

    public function edit(string $uuid): void
    {
        $this->authorizeManage();
        $period = AdmissionPeriod::query()->where('uuid', $uuid)->firstOrFail();
        $zone = config('sanad.display_timezone');
        $this->editing = $period->id;
        $this->fill([
            'name' => $period->name,
            'academic_year_id' => (string) $period->academic_year_id,
            'open_at' => $period->open_at->setTimezone($zone)->format('Y-m-d\TH:i'),
            'close_at' => $period->close_at->setTimezone($zone)->format('Y-m-d\TH:i'),
            'quota' => (string) $period->quota,
            'registration_fee' => (string) $period->registration_fee,
        ]);
        $this->resetErrorBag();
        $this->dispatch('open-modal', 'form-periode');
    }

    public function save(): void
    {
        $this->authorizeManage();
        $this->resetErrorBag();
        $this->registration_fee = preg_replace('/\D/', '', $this->registration_fee) ?: '0';
        $this->validate([
            'name' => 'required|string|max:100',
            'academic_year_id' => 'nullable|exists:academic_years,id',
            'open_at' => 'required|date',
            'close_at' => 'required|date|after:open_at',
            'quota' => 'required|integer|min:1|max:100000',
            'registration_fee' => 'required|integer|min:0|max:100000000',
        ], ['close_at.after' => 'Waktu tutup harus setelah waktu buka.'], ['name' => 'nama', 'open_at' => 'waktu buka', 'close_at' => 'waktu tutup', 'quota' => 'kuota', 'registration_fee' => 'biaya']);

        $zone = config('sanad.display_timezone');
        $values = [
            'name' => $this->name,
            'academic_year_id' => $this->academic_year_id === '' ? null : (int) $this->academic_year_id,
            'open_at' => CarbonImmutable::parse($this->open_at, $zone)->utc(),
            'close_at' => CarbonImmutable::parse($this->close_at, $zone)->utc(),
            'quota' => (int) $this->quota,
            'registration_fee' => (int) $this->registration_fee,
        ];

        $this->editing ? AdmissionPeriod::query()->whereKey($this->editing)->update($values) : AdmissionPeriod::create($values);
        $this->dispatch('close-modal', 'form-periode');
        $this->dispatch('toast', message: 'Periode tersimpan.', tone: 'success');
    }

    public function open(string $uuid): void
    {
        $this->authorizeManage();
        $this->resetErrorBag();
        $period = AdmissionPeriod::query()->where('uuid', $uuid)->firstOrFail();
        $steps = $period->steps()->get();

        if ($steps->isEmpty() || (int) $steps->sum('weight') !== 100) {
            $this->addError('period-'.$uuid, 'Total bobot tahap seleksi harus 100%.');

            return;
        }

        try {
            DB::transaction(fn () => $period->update(['status' => 'open']));
        } catch (UniqueConstraintViolationException) {
            $this->addError('period-'.$uuid, 'Masih ada periode lain yang dibuka. Tutup dulu.');
        }
    }

    public function closePeriod(string $uuid): void
    {
        $this->authorizeManage();
        AdmissionPeriod::query()->where('uuid', $uuid)->firstOrFail()->update(['status' => 'closed']);
    }

    public function addStep(string $uuid): void
    {
        $this->authorizeManage();
        $this->resetErrorBag();
        $period = AdmissionPeriod::query()->where('uuid', $uuid)->firstOrFail();
        $this->step_period = $uuid;
        $this->validate([
            'step_name' => 'required|string|max:100',
            'step_type' => 'required|in:document,exam,interview',
            'step_weight' => 'required|integer|min:1|max:100',
            'step_exam' => $this->step_type === 'exam' ? 'required|exists:exams,id' : 'nullable',
        ], ['step_exam.required' => 'Pilih ujian untuk tahap ini.'], ['step_name' => 'nama tahap', 'step_weight' => 'bobot']);

        AdmissionStep::create([
            'period_id' => $period->id,
            'name' => $this->step_name,
            'type' => $this->step_type,
            'weight' => (int) $this->step_weight,
            'exam_id' => $this->step_type === 'exam' ? (int) $this->step_exam : null,
            'sort_order' => (int) $period->steps()->max('sort_order') + 1,
        ]);

        $this->reset('step_name', 'step_weight', 'step_exam');
        $this->step_type = 'document';
    }

    public function removeStep(string $uuid): void
    {
        $this->authorizeManage();
        AdmissionStep::query()->where('uuid', $uuid)->firstOrFail()->delete();
    }

    public function moveStep(string $uuid, int $direction): void
    {
        $this->authorizeManage();
        $step = AdmissionStep::query()->where('uuid', $uuid)->firstOrFail();
        $steps = AdmissionPeriod::query()->findOrFail($step->period_id)->steps()->get()->values();
        $index = $steps->search(fn (AdmissionStep $item) => $item->id === $step->id);
        $target = $index + ($direction < 0 ? -1 : 1);

        if (isset($steps[$target])) {
            DB::transaction(function () use ($steps, $index, $target): void {
                foreach ($steps as $position => $item) {
                    $item->update(['sort_order' => match ($position) {
                        $index => $target, $target => $index, default => $position
                    }]);
                }
            });
        }
    }

    public function render(): View
    {
        return view('admission.periods', [
            'periods' => AdmissionPeriod::query()->with('steps.exam:id,title')->withCount('applicants')->latest('id')->get(),
            'years' => AcademicYear::query()->orderByDesc('start_date')->pluck('name', 'id')->all(),
            'exams' => Exam::query()->where('audience', 'applicant')->orderByDesc('id')->pluck('title', 'id')->all(),
            'types' => AdmissionStep::TYPES,
            'canManage' => auth()->user()->can('ppdb.manage'),
        ]);
    }
}
