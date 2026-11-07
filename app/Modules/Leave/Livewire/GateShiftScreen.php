<?php

namespace App\Modules\Leave\Livewire;

use App\Modules\Leave\Models\GateShift;
use App\Modules\Leave\Models\LeaveRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.staff', ['active' => 'gate'])]
#[Title('Serah Terima Shift')]
class GateShiftScreen extends Component
{
    public string $note = '';

    private function current(): ?GateShift
    {
        return GateShift::query()->where('user_id', auth()->id())->whereNull('ended_at')->first();
    }

    public function start(): void
    {
        try {
            DB::transaction(fn () => GateShift::create(['user_id' => auth()->id(), 'started_at' => now()]));
            $this->dispatch('toast', message: 'Shift dimulai.', tone: 'success');
        } catch (UniqueConstraintViolationException) {
            $this->dispatch('toast', message: 'Shift Anda sudah berjalan.', tone: 'warning');
        }
    }

    public function end(): void
    {
        $shift = $this->current();
        abort_if($shift === null, 404);

        $shift->update(['ended_at' => now(), 'handover_note' => mb_substr(trim($this->note), 0, 1000) ?: null, 'outside_count_at_end' => LeaveRequest::query()->outside()->count()]);

        $this->reset('note');
        $this->dispatch('toast', message: 'Shift diakhiri. Catatan tersimpan untuk penjaga berikutnya.', tone: 'success');
    }

    public function render(): View
    {
        return view('leave.gate-shift', [
            'current' => $this->current(),
            'previous' => GateShift::query()->with('user:id,name')->whereNotNull('ended_at')->latest('ended_at')->first(),
            'outside' => LeaveRequest::query()->with('student:id,name')->outside()->orderBy('end_at')->get(),
        ]);
    }
}
