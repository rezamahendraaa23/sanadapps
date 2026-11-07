<?php

namespace App\Modules\Leave\Livewire;

use App\Modules\Core\Models\Module;
use App\Modules\Core\Models\Student;
use App\Modules\Leave\Actions\ManageLeave;
use App\Modules\Leave\Models\GateLog;
use App\Modules\Leave\Models\GateLogReview;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Support\LeaveException;
use App\Modules\Leave\Support\LeaveSettings;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Throwable;

/**
 * Persetujuan izin: musyrif untuk asramanya (U-10), pengelola untuk semua (KS-01).
 */
#[Title('Perizinan')]
class LeaveDesk extends Component
{
    use WithPagination;

    #[Url(except: 'pending')]
    public string $status = 'pending';

    #[Locked]
    public ?string $target = null;

    public string $rejectReason = '';

    public string $search = '';

    #[Locked]
    public ?string $newStudent = null;

    public string $type = 'keluar';

    public string $reason = '';

    public string $start_at = '';

    public string $end_at = '';

    public string $return_tolerance = '';

    public string $exit_tolerance = '';

    public bool $two_level = false;

    public function mount(): void
    {
        $this->return_tolerance = (string) LeaveSettings::returnTolerance();
        $this->exit_tolerance = (string) LeaveSettings::exitTolerance();
        $this->two_level = LeaveSettings::twoLevel();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    private function scoped()
    {
        return LeaveRequest::query()->whereIn('student_id', Student::query()->visibleTo(auth()->user())->select('students.id'));
    }

    private function leave(string $uuid): LeaveRequest
    {
        abort_unless(Str::isUuid($uuid), 404);
        $leave = LeaveRequest::query()->with('student.dormitory')->where('uuid', $uuid)->firstOrFail();
        abort_unless($leave->canBeDecidedBy(auth()->user()), 403);

        return $leave;
    }

    public function approve(string $uuid, ManageLeave $leaves): void
    {
        try {
            $leaves->approve($this->leave($uuid), auth()->user());
            $this->dispatch('toast', message: 'Izin disetujui.', tone: 'success');
        } catch (LeaveException $exception) {
            $this->dispatch('toast', message: $exception->getMessage(), tone: 'danger');
        }
    }

    public function askReject(string $uuid): void
    {
        $this->target = $this->leave($uuid)->uuid;
        $this->reset('rejectReason');
        $this->resetErrorBag();
        $this->dispatch('open-modal', 'tolak-izin');
    }

    public function reject(ManageLeave $leaves): void
    {
        $this->resetErrorBag();

        try {
            $leaves->reject($this->leave((string) $this->target), $this->rejectReason, auth()->user());
        } catch (LeaveException $exception) {
            $this->addError('rejectReason', $exception->getMessage());

            return;
        }

        $this->dispatch('close-modal', 'tolak-izin');
        $this->dispatch('toast', message: 'Izin ditolak, wali diberi tahu.', tone: 'success');
    }

    public function pickStudent(string $uuid): void
    {
        abort_unless(Student::query()->visibleTo(auth()->user())->where('uuid', $uuid)->exists(), 403);
        $this->newStudent = $uuid;
        $this->reset('search');
    }

    public function submit(ManageLeave $leaves): void
    {
        $this->resetErrorBag();
        $student = $this->newStudent ? Student::query()->visibleTo(auth()->user())->where('uuid', $this->newStudent)->first() : null;

        if ($student === null) {
            $this->addError('search', 'Pilih santri dulu.');

            return;
        }

        $zone = config('sanad.display_timezone');

        try {
            $start = CarbonImmutable::parse($this->start_at, $zone)->utc();
            $end = CarbonImmutable::parse($this->end_at, $zone)->utc();
        } catch (Throwable) {
            $this->addError('end_at', 'Isi waktu keluar dan kembali.');

            return;
        }

        if ($this->start_at === '' || $this->end_at === '') {
            $this->addError('end_at', 'Isi waktu keluar dan kembali.');

            return;
        }

        try {
            $leaves->request($student, $this->type, $this->reason, $start, $end, auth()->id());
        } catch (LeaveException $exception) {
            $this->addError(str_contains($exception->getMessage(), 'Alasan') ? 'reason' : 'end_at', $exception->getMessage());

            return;
        }

        $this->reset('newStudent', 'type', 'reason', 'start_at', 'end_at');
        $this->dispatch('close-modal', 'buat-izin');
        $this->dispatch('toast', message: 'Izin dibuat.', tone: 'success');
    }

    public function markReviewed(string $uuid): void
    {
        abort_unless(Str::isUuid($uuid), 404);
        $log = GateLog::query()->whereIn('student_id', Student::query()->visibleTo(auth()->user())->select('students.id'))->where('method', 'manual')->where('uuid', $uuid)->firstOrFail();
        abort_unless(auth()->user()->canAny(['leave.decide', 'leave.manage']), 403);

        GateLogReview::query()->firstOrCreate(['gate_log_id' => $log->id], ['reviewed_by' => auth()->id()]);
    }

    public function saveSettings(): void
    {
        abort_unless(auth()->user()->can('leave.manage'), 403);
        $this->validate(['return_tolerance' => 'required|integer|min:0|max:600', 'exit_tolerance' => 'required|integer|min:0|max:600'], [], ['return_tolerance' => 'toleransi kembali', 'exit_tolerance' => 'toleransi keluar']);

        $module = Module::query()->where('module_key', 'leave')->firstOrFail();
        $module->update(['settings' => [...($module->settings ?? []), 'return_tolerance' => (int) $this->return_tolerance, 'exit_tolerance' => (int) $this->exit_tolerance, 'two_level' => $this->two_level]]);

        $this->dispatch('close-modal', 'aturan-izin');
        $this->dispatch('toast', message: 'Aturan izin tersimpan.', tone: 'success');
    }

    public function render(): View
    {
        $user = auth()->user();
        $term = trim($this->search);
        $portal = ! $user->can('leave.manage');

        return view('leave.desk', [
            'leaves' => $this->scoped()->with('student.classroom')
                ->when($this->status !== 'semua', fn ($query) => $query->where('status', $this->status))
                ->when($this->status === 'pending' && LeaveSettings::twoLevel() && $portal, fn ($query) => $query->whereNull('first_approved_by'))
                ->latest('id')->paginate(20),
            'statuses' => ['pending' => 'Menunggu', 'approved' => 'Disetujui', 'out' => 'Sedang di luar', 'returned' => 'Sudah kembali', 'late' => 'Terlambat', 'rejected' => 'Ditolak', 'semua' => 'Semua'],
            'types' => LeaveRequest::TYPES,
            'results' => mb_strlen($term) >= 2 ? Student::query()->visibleTo($user)->where(fn ($query) => $query->where('name', 'ilike', '%'.addcslashes($term, '%_').'%')->orWhere('nis', 'like', addcslashes($term, '%_').'%'))->orderBy('name')->limit(6)->get() : collect(),
            'newStudentModel' => $this->newStudent ? Student::query()->where('uuid', $this->newStudent)->first() : null,
            'toReview' => GateLog::query()->with(['student:id,name', 'recorder:id,name'])->whereIn('student_id', Student::query()->visibleTo($user)->select('students.id'))->where('method', 'manual')->whereDoesntHave('review')->latest('id')->limit(20)->get(),
            'canSettings' => $user->can('leave.manage'),
            'twoLevelActive' => LeaveSettings::twoLevel(),
        ])->layout($portal ? 'components.layouts.portal' : 'components.layouts.staff', $portal ? ['role' => 'musyrif', 'active' => 'leaves', 'title' => 'Izin'] : ['active' => 'leaves']);
    }
}
