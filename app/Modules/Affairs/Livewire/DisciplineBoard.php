<?php

namespace App\Modules\Affairs\Livewire;

use App\Modules\Affairs\Actions\RecordViolation;
use App\Modules\Affairs\Models\DisciplineRecord;
use App\Modules\Affairs\Models\DisciplineRule;
use App\Modules\Affairs\Models\DisciplineSanction;
use App\Modules\Affairs\Support\PortalLayout;
use App\Modules\Core\Models\AcademicYear;
use App\Modules\Core\Models\Module;
use App\Modules\Core\Models\Student;
use App\Modules\Finance\Actions\CompressProofImage;
use App\Support\SchoolDate;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/**
 * Pelanggaran dan poin (KS-02): musyrif dan ustadz mencatat, admin mengatur aturan dan ambang.
 */
#[Title('Pelanggaran')]
class DisciplineBoard extends Component
{
    use PortalLayout, WithFileUploads, WithPagination;

    public string $search = '';

    public string $studentUuid = '';

    public string $rule_id = '';

    public string $occurred_on = '';

    public string $notes = '';

    public $evidence = null;

    public string $rule_name = '';

    public string $rule_category = '';

    public string $rule_points = '';

    public string $sanction_name = '';

    public string $sanction_points = '';

    public string $sanction_action = '';

    public bool $sanction_notify = true;

    public string $late_rule = '';

    public function mount(): void
    {
        $this->occurred_on = now(config('sanad.display_timezone'))->toDateString();
        $this->late_rule = (string) (Module::query()->where('module_key', 'discipline')->value('settings')['late_rule_id'] ?? '');
    }

    public function chooseStudent(string $uuid): void
    {
        $this->studentUuid = Student::query()->visibleTo(auth()->user())->where('uuid', $uuid)->firstOrFail()->uuid;
        $this->reset('search');
        $this->resetErrorBag();
    }

    public function record(RecordViolation $action, CompressProofImage $images): void
    {
        abort_unless(auth()->user()->can('discipline.record'), 403);
        $this->resetErrorBag();
        $student = Str::isUuid($this->studentUuid) ? Student::query()->visibleTo(auth()->user())->where('uuid', $this->studentUuid)->first() : null;

        if ($student === null) {
            $this->addError('search', 'Pilih santri dulu.');

            return;
        }

        $this->validate([
            'rule_id' => 'required|exists:discipline_rules,id',
            'occurred_on' => 'required|date|before_or_equal:'.SchoolDate::today()->toDateString(),
            'notes' => 'nullable|string|max:500',
            'evidence' => 'nullable|image|max:5120',
        ], ['rule_id.required' => 'Pilih jenis pelanggaran.'], ['occurred_on' => 'tanggal', 'notes' => 'catatan', 'evidence' => 'foto bukti']);

        $action->handle($student, DisciplineRule::query()->findOrFail((int) $this->rule_id), $this->occurred_on, [
            'notes' => $this->notes ?: null,
            'evidence_path' => $this->evidence ? $images->store($this->evidence, 'discipline-evidence') : null,
        ], auth()->id());

        $this->reset('studentUuid', 'rule_id', 'notes', 'evidence');
        $this->dispatch('toast', message: 'Pelanggaran tercatat.', tone: 'success');
    }

    private function configure(): void
    {
        abort_unless(auth()->user()->can('discipline.configure'), 403);
    }

    public function saveRule(): void
    {
        $this->configure();
        $this->resetErrorBag();
        $this->validate(['rule_name' => 'required|string|max:150', 'rule_category' => 'nullable|string|max:60', 'rule_points' => 'required|integer|min:1|max:1000'], [], ['rule_name' => 'nama aturan', 'rule_points' => 'poin']);
        DisciplineRule::create(['name' => $this->rule_name, 'category' => $this->rule_category ?: null, 'points' => (int) $this->rule_points]);
        $this->reset('rule_name', 'rule_category', 'rule_points');
    }

    public function toggleRule(string $uuid): void
    {
        $this->configure();
        $rule = DisciplineRule::query()->where('uuid', $uuid)->firstOrFail();
        $rule->update(['is_active' => ! $rule->is_active]);
    }

    public function saveSanction(): void
    {
        $this->configure();
        $this->resetErrorBag();
        $this->validate(['sanction_name' => 'required|string|max:100', 'sanction_points' => 'required|integer|min:1|max:100000', 'sanction_action' => 'nullable|string|max:300'], [], ['sanction_name' => 'nama sanksi', 'sanction_points' => 'ambang poin']);
        DisciplineSanction::create(['name' => $this->sanction_name, 'min_points' => (int) $this->sanction_points, 'action_text' => $this->sanction_action ?: null, 'notify_guardian' => $this->sanction_notify]);
        $this->reset('sanction_name', 'sanction_points', 'sanction_action');
    }

    public function removeSanction(string $uuid): void
    {
        $this->configure();
        DisciplineSanction::query()->where('uuid', $uuid)->firstOrFail()->delete();
    }

    public function saveLateRule(): void
    {
        $this->configure();
        $module = Module::query()->where('module_key', 'discipline')->firstOrFail();
        $module->update(['settings' => [...($module->settings ?? []), 'late_rule_id' => $this->late_rule === '' ? null : (int) $this->late_rule]]);
        $this->dispatch('toast', message: 'Pengaturan tersimpan.', tone: 'success');
    }

    public function render(): View
    {
        $user = auth()->user();
        $term = trim($this->search);
        $year = AcademicYear::query()->where('is_active', true)->first();
        $visible = Student::query()->visibleTo($user)->select('students.id');
        [$layout, $params] = $this->affairsLayout('discipline', 'Pelanggaran');

        return view('affairs.discipline', [
            'student' => Str::isUuid($this->studentUuid) ? Student::query()->visibleTo($user)->where('uuid', $this->studentUuid)->first() : null,
            'results' => mb_strlen($term) >= 2 ? Student::query()->visibleTo($user)->where('status', 'active')->where(fn ($query) => $query->where('name', 'ilike', '%'.addcslashes($term, '%_').'%')->orWhere('nis', 'like', addcslashes($term, '%_').'%'))->orderBy('name')->limit(6)->get() : collect(),
            'ruleOptions' => DisciplineRule::query()->where('is_active', true)->orderBy('name')->get()->mapWithKeys(fn (DisciplineRule $rule) => [$rule->id => $rule->name.' ('.$rule->points.' poin)'])->all(),
            'rules' => DisciplineRule::query()->orderBy('name')->get(),
            'sanctions' => DisciplineSanction::query()->orderBy('min_points')->get(),
            'records' => DisciplineRecord::query()->with(['student:id,name,nis', 'rule:id,name'])->whereIn('student_id', $visible)->latest('occurred_on')->latest('id')->paginate(15),
            'ranking' => DB::table('discipline_records')->join('students', 'students.id', '=', 'discipline_records.student_id')->whereIn('students.id', $visible)
                ->when($year, fn ($query) => $query->whereBetween('occurred_on', [$year->start_date->toDateString(), $year->end_date->toDateString()]))
                ->selectRaw('students.name, students.uuid, SUM(discipline_records.points) AS total')->groupBy('students.id', 'students.name', 'students.uuid')->orderByDesc('total')->limit(10)->get(),
            'canRecord' => $user->can('discipline.record'),
            'canConfigure' => $user->can('discipline.configure'),
        ])->layout($layout, $params);
    }
}
