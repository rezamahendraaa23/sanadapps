<?php

namespace App\Modules\Affairs\Livewire;

use App\Modules\Affairs\Models\HealthRecord;
use App\Modules\Core\Actions\RecordAudit;
use App\Modules\Core\Models\Student;
use App\Modules\Notification\Actions\QueueNotification;
use App\Support\SchoolDate;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Kesehatan UKS (KS-03). Detail hanya petugas UKS; admin dan musyrif melihat ringkasan tanpa keluhan dan obat.
 */
#[Layout('components.layouts.staff', ['active' => 'health'])]
#[Title('Kesehatan')]
class HealthDesk extends Component
{
    use WithPagination;

    public string $search = '';

    public string $studentUuid = '';

    public string $visit_date = '';

    public string $complaint = '';

    public string $action = '';

    public string $medication = '';

    public string $outcome = 'ditangani';

    #[Locked]
    public ?array $detail = null;

    public function mount(): void
    {
        abort_unless(auth()->user()->canAny(['health.manage', 'health.summary']), 403);
        $this->visit_date = now(config('sanad.display_timezone'))->toDateString();
    }

    public function chooseStudent(string $uuid): void
    {
        abort_unless(auth()->user()->can('health.manage'), 403);
        $this->studentUuid = $uuid;
        $this->reset('search');
    }

    public function save(QueueNotification $notifications): void
    {
        abort_unless(auth()->user()->can('health.manage'), 403);
        $this->resetErrorBag();
        $student = Str::isUuid($this->studentUuid) ? Student::query()->where('uuid', $this->studentUuid)->first() : null;

        if ($student === null) {
            $this->addError('search', 'Pilih santri dulu.');

            return;
        }

        $this->validate([
            'visit_date' => 'required|date|before_or_equal:'.SchoolDate::today()->toDateString(),
            'complaint' => 'required|string|max:1000',
            'action' => 'nullable|string|max:1000',
            'medication' => 'nullable|string|max:500',
            'outcome' => 'required|in:ditangani,rujuk,dipulangkan',
        ], [], ['visit_date' => 'tanggal', 'complaint' => 'keluhan', 'action' => 'tindakan', 'medication' => 'obat']);

        $record = HealthRecord::create(['student_id' => $student->id, 'visit_date' => $this->visit_date, 'complaint' => $this->complaint, 'action' => $this->action ?: null, 'medication' => $this->medication ?: null, 'outcome' => $this->outcome, 'recorded_by' => auth()->id()]);

        // Wali hanya menerima hasil akhirnya, bukan diagnosis (HLT-02).
        if ($this->outcome !== 'ditangani') {
            $notifications->toGuardians('health_referred', [$student], fn ($guardian) => [
                'nama_wali' => $guardian->name,
                'nama_santri' => $student->name,
                'tindakan' => mb_strtolower(HealthRecord::OUTCOMES[$this->outcome]),
                'tanggal' => $record->visit_date->translatedFormat('j F Y'),
            ], 'Kabar kesehatan '.$student->name, null, 'health:'.$record->id);
        }

        $this->reset('studentUuid', 'complaint', 'action', 'medication');
        $this->outcome = 'ditangani';
        $this->dispatch('toast', message: 'Catatan tersimpan.', tone: 'success');
    }

    public function open(string $uuid, RecordAudit $audit): void
    {
        abort_unless(auth()->user()->can('health.manage'), 403);
        $record = HealthRecord::query()->with('student:id,name')->where('uuid', $uuid)->firstOrFail();
        $audit->handle('health.viewed', 'health_record', $record->id);
        $this->detail = ['name' => $record->student->name, 'complaint' => $record->complaint, 'action' => $record->action, 'medication' => $record->medication];
        $this->dispatch('open-modal', 'detail-kesehatan');
    }

    public function render(): View
    {
        $user = auth()->user();
        $term = trim($this->search);

        return view('affairs.health', [
            'canManage' => $user->can('health.manage'),
            'student' => Str::isUuid($this->studentUuid) ? Student::query()->where('uuid', $this->studentUuid)->first() : null,
            'results' => $user->can('health.manage') && mb_strlen($term) >= 2 ? Student::query()->where('status', 'active')->where(fn ($query) => $query->where('name', 'ilike', '%'.addcslashes($term, '%_').'%')->orWhere('nis', 'like', addcslashes($term, '%_').'%'))->orderBy('name')->limit(6)->get() : collect(),
            'records' => HealthRecord::query()->with('student:id,name')->whereIn('student_id', Student::query()->visibleTo($user)->select('students.id'))->latest('visit_date')->latest('id')->paginate(20),
        ]);
    }
}
