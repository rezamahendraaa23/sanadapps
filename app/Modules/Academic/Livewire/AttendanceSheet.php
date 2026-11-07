<?php

namespace App\Modules\Academic\Livewire;

use App\Modules\Academic\Actions\RecordAttendance;
use App\Modules\Academic\Models\AttendanceRecord;
use App\Modules\Academic\Models\AttendanceSession;
use App\Modules\Academic\Support\AcademicException;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class AttendanceSheet extends Component
{
    public AttendanceSession $session;

    /** @var array<string, string> uuid catatan ke status */
    public array $statuses = [];

    public string $scan = '';

    /** @var array{ok: bool, message: string, name: string|null, status: string|null}|null */
    #[Locked]
    public ?array $lastScan = null;

    public function mount(AttendanceSession $session): void
    {
        abort_unless($session->canBeTakenBy(auth()->user()), 403);
        $this->session = $session;
        $this->statuses = $session->records()->pluck('status', 'uuid')->all();
    }

    public function save(RecordAttendance $action): void
    {
        abort_unless($this->session->canBeTakenBy(auth()->user()), 403);

        try {
            $action->save($this->session, $this->statuses, auth()->id());
        } catch (AcademicException $exception) {
            $this->dispatch('toast', message: $exception->getMessage(), tone: 'danger');

            return;
        }

        $this->dispatch('attendance-saved');
        $this->dispatch('toast', message: 'Absensi tersimpan.', tone: 'success');
    }

    public function submitScan(RecordAttendance $action): void
    {
        abort_unless($this->session->canBeTakenBy(auth()->user()), 403);
        $value = trim($this->scan);
        $this->scan = '';

        if ($value === '') {
            return;
        }

        $this->lastScan = $action->scan($this->session, $value);
        $this->statuses = $this->session->records()->pluck('status', 'uuid')->all();
    }

    public function render(): View
    {
        $user = auth()->user();
        $role = $user->can('academic.manage') ? null : ($this->session->dormitory_id ? 'musyrif' : 'ustadz');

        return view('academic.attendance-sheet', [
            'records' => $this->session->records()->with('student:id,name,nis')->get()->sortBy('student.name'),
            'labels' => AttendanceRecord::STATUSES,
            'scanning' => $this->session->source !== 'manual' && ! $this->session->isClosed(),
            'reviewCount' => $this->session->records()->awaitingReview()->count(),
            'reviewUrl' => route($role ? $role.'.attendance.review' : 'staff.attendance.review'),
        ])->layout($role ? 'components.layouts.portal' : 'components.layouts.staff', $role ? ['role' => $role, 'active' => 'attendance', 'title' => $this->session->title] : ['active' => 'attendance'])->title($this->session->title);
    }
}
