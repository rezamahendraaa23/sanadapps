<?php

namespace App\Modules\Leave\Livewire;

use App\Modules\Core\Models\InAppNotification;
use App\Modules\Core\Models\Student;
use App\Modules\Leave\Actions\GateScan;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Layar pemindai gerbang (K-02).
 */
#[Layout('components.layouts.staff', ['active' => 'gate'])]
#[Title('Pindai Gerbang')]
class GateScanner extends Component
{
    public string $scan = '';

    public string $pickup_name = '';

    public string $pickup_relation = '';

    #[Locked]
    public ?string $pending = null;

    /** @var array{outcome: string, message: string, name: string|null, classroom: string|null, student: string|null}|null */
    #[Locked]
    public ?array $result = null;

    public function submitScan(GateScan $gate): void
    {
        $value = trim($this->scan);
        $this->scan = '';

        if ($value === '') {
            return;
        }

        $this->show($gate->handle($value, auth()->id()), $value);
    }

    public function submitPickup(GateScan $gate): void
    {
        $this->resetErrorBag();

        if (trim($this->pickup_name) === '') {
            $this->addError('pickup_name', 'Nama penjemput wajib diisi.');

            return;
        }

        abort_if($this->pending === null, 404);

        $this->show($gate->handle($this->pending, auth()->id(), null, $this->pickup_name, $this->pickup_relation), $this->pending);
    }

    private function show(array $outcome, string $credential): void
    {
        if ($outcome['outcome'] === 'repeat') {
            return;
        }

        $this->pending = $outcome['outcome'] === 'needs_pickup' ? $credential : null;

        if ($outcome['outcome'] !== 'needs_pickup') {
            $this->reset('pickup_name', 'pickup_relation');
        }

        $student = $outcome['student']?->loadMissing('classroom:id,name');

        $this->result = [
            'outcome' => $outcome['outcome'],
            'message' => $outcome['message'],
            'name' => $student?->name,
            'classroom' => $student?->classroom?->name,
            'student' => $student?->uuid,
        ];

        $this->dispatch('gate-beep', ok: in_array($outcome['outcome'], ['out', 'in'], true));
    }

    public function clear(): void
    {
        $this->reset('result', 'pending', 'pickup_name', 'pickup_relation');
    }

    /**
     * Tombol "Panggil musyrif" di layar penolakan.
     */
    public function callMusyrif(): void
    {
        $uuid = (string) ($this->result['student'] ?? '');
        abort_unless(Str::isUuid($uuid), 404);
        $student = Student::query()->with('dormitory.musyrif')->where('uuid', $uuid)->firstOrFail();
        $musyrif = $student->dormitory?->musyrif;

        if ($musyrif === null) {
            $this->dispatch('toast', message: 'Santri ini belum punya musyrif. Hubungi admin.', tone: 'warning');

            return;
        }

        InAppNotification::create(['user_id' => $musyrif->id, 'title' => 'Dipanggil ke gerbang', 'body' => $student->name.' tertahan di gerbang.', 'link' => route('musyrif.leaves')]);
        $this->dispatch('toast', message: "{$musyrif->name} sudah dipanggil.", tone: 'success');
    }

    public function render(): View
    {
        return view('leave.gate-scanner', [
            'studentModel' => filled($this->result['student'] ?? null) ? Student::query()->where('uuid', $this->result['student'])->first() : null,
        ]);
    }
}
