<?php

namespace App\Modules\Leave\Livewire;

use App\Modules\Core\Models\Student;
use App\Modules\Leave\Actions\GateScan;
use App\Modules\Leave\Actions\ManageLeave;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Support\LeaveException;
use App\Support\Qr;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Throwable;

/**
 * Izin bagi wali (W-11): mengajukan dan membatalkan. Santri hanya melihat izin dan QR-nya sendiri.
 */
#[Title('Perizinan')]
class GuardianLeaves extends Component
{
    #[Url(as: 'anak', except: '')]
    public string $childUuid = '';

    public string $type = 'keluar';

    public string $reason = '';

    public string $start_at = '';

    public string $end_at = '';

    #[Locked]
    public ?string $detail = null;

    /**
     * @return Collection<int, Student>
     */
    private function children(): Collection
    {
        $user = auth()->user();

        return $user->type === 'guardian' ? ($user->guardian?->students()->orderBy('name')->get() ?? collect()) : collect(array_filter([$user->student]));
    }

    private function child(): ?Student
    {
        $children = $this->children();

        return $children->firstWhere('uuid', $this->childUuid) ?? $children->first();
    }

    public function submit(ManageLeave $leaves): void
    {
        abort_unless(auth()->user()->type === 'guardian', 403);
        $child = $this->child();
        abort_if($child === null, 404);
        $this->resetErrorBag();
        $zone = config('sanad.display_timezone');

        try {
            $start = CarbonImmutable::parse($this->start_at, $zone);
            $end = CarbonImmutable::parse($this->end_at, $zone);
        } catch (Throwable) {
            $this->addError('start_at', 'Isi waktu keluar dan kembali.');

            return;
        }

        if ($this->start_at === '' || $this->end_at === '') {
            $this->addError('start_at', 'Isi waktu keluar dan kembali.');

            return;
        }

        try {
            $leaves->request($child, $this->type, $this->reason, $start->utc(), $end->utc(), auth()->id());
        } catch (LeaveException $exception) {
            $this->addError(str_contains($exception->getMessage(), 'Alasan') ? 'reason' : 'end_at', $exception->getMessage());

            return;
        }

        $this->reset('type', 'reason', 'start_at', 'end_at');
        $this->dispatch('close-modal', 'ajukan-izin');
        $this->dispatch('toast', message: 'Izin terkirim. Menunggu persetujuan musyrif.', tone: 'success');
    }

    private function ownLeave(string $uuid): LeaveRequest
    {
        abort_unless(Str::isUuid($uuid), 404);

        return LeaveRequest::query()->whereIn('student_id', $this->children()->pluck('id'))->where('uuid', $uuid)->firstOrFail();
    }

    public function open(string $uuid): void
    {
        $this->detail = $this->ownLeave($uuid)->uuid;
        $this->dispatch('open-modal', 'detail-izin');
    }

    public function cancel(string $uuid, ManageLeave $leaves): void
    {
        abort_unless(auth()->user()->type === 'guardian', 403);

        try {
            $leaves->cancel($this->ownLeave($uuid));
            $this->dispatch('close-modal', 'detail-izin');
            $this->dispatch('toast', message: 'Izin dibatalkan.', tone: 'success');
        } catch (LeaveException $exception) {
            $this->dispatch('toast', message: $exception->getMessage(), tone: 'danger');
        }
    }

    public function render(): View
    {
        $user = auth()->user();
        $isGuardian = $user->type === 'guardian';
        $child = $this->child();
        $detail = $this->detail ? LeaveRequest::query()->with('logs')->whereIn('student_id', $this->children()->pluck('id'))->where('uuid', $this->detail)->first() : null;

        return view('leave.guardian', [
            'isGuardian' => $isGuardian,
            'children' => $this->children(),
            'child' => $child,
            'leaves' => $child ? LeaveRequest::query()->where('student_id', $child->id)->latest('id')->limit(30)->get() : collect(),
            'types' => LeaveRequest::TYPES,
            'detailLeave' => $detail,
            'qr' => $detail?->status === 'approved' ? Qr::svg(GateScan::leaveQr($detail), 240) : null,
        ])->layout('components.layouts.portal', $isGuardian ? ['role' => 'wali', 'active' => 'more', 'title' => 'Perizinan'] : ['role' => 'santri', 'active' => 'home', 'title' => 'Perizinan']);
    }
}
