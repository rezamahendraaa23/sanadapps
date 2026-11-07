<?php

namespace App\Modules\Leave\Livewire;

use App\Modules\Core\Models\Student;
use App\Modules\Leave\Actions\GateScan;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Support\LeaveException;
use App\Modules\Leave\Support\LeaveSettings;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Throwable;

/**
 * Papan "sedang di luar" (K-03) dan pencatatan manual serta override darurat (K-04).
 */
#[Layout('components.layouts.staff', ['active' => 'gate'])]
#[Title('Pos Gerbang')]
class GateBoard extends Component
{
    #[Url(as: 'tampil', except: 'semua')]
    public string $filter = 'semua';

    public string $search = '';

    #[Locked]
    public ?string $selected = null;

    public string $reason = '';

    public string $pickup_name = '';

    public string $pickup_relation = '';

    public string $override_return = '';

    public function select(string $uuid): void
    {
        abort_unless(Str::isUuid($uuid), 404);
        $this->selected = Student::query()->where('uuid', $uuid)->value('uuid');
        $this->reset('search', 'reason', 'pickup_name', 'pickup_relation', 'override_return');
        $this->resetErrorBag();
        $this->dispatch('open-modal', 'catat-manual');
    }

    private function selectedStudent(): ?Student
    {
        return $this->selected ? Student::query()->where('uuid', $this->selected)->first() : null;
    }

    private function activeLeave(Student $student): ?LeaveRequest
    {
        return LeaveRequest::query()->where('student_id', $student->id)->where(fn ($query) => $query->outside()->orWhere('status', 'approved'))->orderByRaw("CASE WHEN status IN ('out', 'late') THEN 0 ELSE 1 END")->orderBy('start_at')->first();
    }

    public function record(GateScan $gate): void
    {
        $student = $this->selectedStudent();
        abort_if($student === null, 404);
        $this->resetErrorBag();
        $leave = $this->activeLeave($student);

        if ($leave === null) {
            $this->addError('reason', 'Santri ini tidak punya izin aktif.');

            return;
        }

        try {
            $result = $gate->manual($leave, $this->reason, auth()->id(), $this->pickup_name ?: null, $this->pickup_relation ?: null);
        } catch (LeaveException $exception) {
            $this->addError('reason', $exception->getMessage());

            return;
        }

        $this->dispatch('close-modal', 'catat-manual');
        $this->dispatch('toast', message: ($result['outcome'] === 'out' ? 'Keluar' : 'Masuk').' dicatat manual, masuk tinjauan musyrif.', tone: 'success');
    }

    public function override(GateScan $gate): void
    {
        abort_unless(auth()->user()->can('gate.override'), 403);
        $student = $this->selectedStudent();
        abort_if($student === null, 404);
        $this->resetErrorBag();

        try {
            $return = CarbonImmutable::parse($this->override_return, config('sanad.display_timezone'))->utc();
        } catch (Throwable) {
            $this->addError('override_return', 'Isi batas kembali.');

            return;
        }

        if ($this->override_return === '') {
            $this->addError('override_return', 'Isi batas kembali.');

            return;
        }

        try {
            $gate->override($student, $this->reason, $return, auth()->id());
        } catch (LeaveException $exception) {
            $this->addError('reason', $exception->getMessage());

            return;
        }

        $this->dispatch('close-modal', 'catat-manual');
        $this->dispatch('toast', message: 'Keluar darurat tercatat. Wali diberi tahu.', tone: 'success');
    }

    public function render(): View
    {
        $now = now();
        $tolerance = LeaveSettings::returnTolerance();
        $outside = LeaveRequest::query()->with('student.classroom')->outside()->get()
            ->map(function (LeaveRequest $leave) use ($now, $tolerance) {
                $leave->setAttribute('is_late', $now->greaterThan($leave->end_at->addMinutes($tolerance)));

                return $leave;
            })
            ->sortBy(fn (LeaveRequest $leave) => [$leave->is_late ? 0 : 1, $leave->end_at->getTimestamp()])
            ->values();
        $term = trim($this->search);
        $student = $this->selectedStudent();

        return view('leave.gate-board', [
            'total' => $outside->count(),
            'lateCount' => $outside->where('is_late', true)->count(),
            'rows' => $outside->filter(fn (LeaveRequest $leave) => match ($this->filter) {
                'terlambat' => $leave->is_late,
                'segera' => ! $leave->is_late && $leave->end_at->lessThanOrEqualTo($now->copy()->addHour()),
                'pulang' => $leave->type === 'pulang',
                default => true,
            }),
            'results' => mb_strlen($term) >= 2 ? Student::query()->where('status', 'active')->where(fn ($query) => $query->where('name', 'ilike', '%'.addcslashes($term, '%_').'%')->orWhere('nis', 'like', addcslashes($term, '%_').'%'))->with('classroom:id,name')->orderBy('name')->limit(6)->get() : collect(),
            'student' => $student,
            'activeLeave' => $student ? $this->activeLeave($student) : null,
            'types' => LeaveRequest::TYPES,
            'updatedAt' => $now->setTimezone(config('sanad.display_timezone'))->format('H.i.s'),
        ]);
    }
}
