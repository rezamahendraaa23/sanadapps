<?php

namespace App\Modules\Academic\Livewire;

use App\Modules\Academic\Actions\ReviewAttendance;
use App\Modules\Academic\Models\AttendanceRecord;
use App\Modules\Academic\Support\AcademicException;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Antrean tinjauan selfie dan wajah (U-14). Skor dan foto hanya untuk peninjau, tidak pernah ke wali.
 */
#[Title('Tinjauan Absensi')]
class AttendanceReview extends Component
{
    /** @var list<string> */
    public array $selected = [];

    #[Locked]
    public ?string $rejecting = null;

    public string $reason = '';

    private function record(string $uuid): AttendanceRecord
    {
        return AttendanceRecord::query()->with('session')->where('uuid', $uuid)->firstOrFail();
    }

    public function approve(string $uuid, ReviewAttendance $review): void
    {
        try {
            $review->decide($this->record($uuid), 'approve', null, auth()->user());
        } catch (AcademicException $exception) {
            $this->dispatch('toast', message: $exception->getMessage(), tone: 'danger');

            return;
        }

        $this->selected = array_values(array_diff($this->selected, [$uuid]));
        $this->dispatch('toast', message: 'Absensi diterima.', tone: 'success');
    }

    public function approveSelected(ReviewAttendance $review): void
    {
        $count = 0;

        foreach ($this->selected as $uuid) {
            try {
                $review->decide($this->record($uuid), 'approve', null, auth()->user());
                $count++;
            } catch (AcademicException) {
                continue;
            }
        }

        $this->selected = [];
        $this->dispatch('toast', message: "{$count} absensi diterima.", tone: 'success');
    }

    public function askReject(string $uuid): void
    {
        $this->rejecting = $this->record($uuid)->uuid;
        $this->reset('reason');
        $this->resetErrorBag();
        $this->dispatch('open-modal', 'tolak-absensi');
    }

    public function reject(ReviewAttendance $review): void
    {
        $this->resetErrorBag();

        if ($this->rejecting === null) {
            return;
        }

        try {
            $review->decide($this->record($this->rejecting), 'reject', $this->reason, auth()->user());
        } catch (AcademicException $exception) {
            $this->addError('reason', $exception->getMessage());

            return;
        }

        $this->selected = array_values(array_diff($this->selected, [$this->rejecting]));
        $this->rejecting = null;
        $this->dispatch('close-modal', 'tolak-absensi');
        $this->dispatch('toast', message: 'Absensi ditolak. Santri bisa mengulang atau pakai kartu.', tone: 'success');
    }

    public function render(ReviewAttendance $review): View
    {
        $user = auth()->user();
        $role = $user->can('academic.manage') ? null : ($user->hasRole('musyrif') ? 'musyrif' : 'ustadz');

        return view('academic.attendance-review', ['records' => $review->queueFor($user)])
            ->layout($role ? 'components.layouts.portal' : 'components.layouts.staff', $role ? ['role' => $role, 'active' => 'attendance', 'title' => 'Tinjauan Absensi'] : ['active' => 'attendance']);
    }
}
