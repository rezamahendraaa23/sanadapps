<?php

namespace App\Modules\Affairs\Livewire;

use App\Modules\Affairs\Actions\ManageDuty;
use App\Modules\Affairs\Models\DutyAssignment;
use App\Modules\Affairs\Models\DutyRoster;
use App\Modules\Affairs\Support\AffairsException;
use App\Modules\Core\Models\Student;
use App\Modules\Core\Models\Teacher;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Jadwal piket (PKT-01): pola mingguan, penugasan, tukar.
 */
#[Layout('components.layouts.staff', ['active' => 'duty'])]
#[Title('Piket')]
class DutyBoard extends Component
{
    public string $name = '';

    public string $day_of_week = '1';

    public string $start_time = '';

    public string $end_time = '';

    public string $location = '';

    public string $roster_id = '';

    public string $duty_date = '';

    public string $person = '';

    public string $search = '';

    /** @var list<string> */
    public array $swap = [];

    public function saveRoster(): void
    {
        $this->resetErrorBag();
        $this->validate(['name' => 'required|string|max:100', 'day_of_week' => 'required|integer|between:1,7', 'start_time' => 'required|date_format:H:i', 'end_time' => 'required|date_format:H:i|after:start_time', 'location' => 'nullable|string|max:100'], ['end_time.after' => 'Jam selesai harus setelah jam mulai.'], ['name' => 'nama', 'start_time' => 'jam mulai', 'end_time' => 'jam selesai']);
        DutyRoster::create(['name' => $this->name, 'day_of_week' => (int) $this->day_of_week, 'start_time' => $this->start_time, 'end_time' => $this->end_time, 'location' => $this->location ?: null]);
        $this->reset('name', 'start_time', 'end_time', 'location');
        $this->dispatch('close-modal', 'tambah-piket');
    }

    public function choosePerson(string $value): void
    {
        $this->person = $value;
        $this->reset('search');
    }

    public function assign(ManageDuty $duty): void
    {
        $this->resetErrorBag();
        $this->validate(['roster_id' => 'required|exists:duty_rosters,id', 'duty_date' => 'required|date', 'person' => 'required'], ['person.required' => 'Pilih orangnya.', 'roster_id.required' => 'Pilih piket.'], ['duty_date' => 'tanggal']);
        [$type, $id] = array_pad(explode(':', $this->person), 2, 0);
        abort_unless(in_array($type, ['student', 'teacher'], true), 403);

        try {
            $duty->assign(DutyRoster::query()->findOrFail((int) $this->roster_id), $type, (int) $id, $this->duty_date);
        } catch (AffairsException $exception) {
            $this->addError('duty_date', $exception->getMessage());

            return;
        }

        $this->reset('person');
    }

    public function swapSelected(ManageDuty $duty): void
    {
        $this->resetErrorBag();
        $items = DutyAssignment::query()->whereIn('uuid', $this->swap)->get();

        if ($items->count() !== 2) {
            $this->addError('swap', 'Pilih tepat dua penugasan.');

            return;
        }

        try {
            $duty->swap($items[0], $items[1]);
        } catch (AffairsException $exception) {
            $this->addError('swap', $exception->getMessage());

            return;
        }

        $this->swap = [];
        $this->dispatch('toast', message: 'Piket ditukar.', tone: 'success');
    }

    public function markDone(string $uuid): void
    {
        DutyAssignment::query()->where('uuid', $uuid)->firstOrFail()->update(['status' => 'done']);
    }

    public function render(): View
    {
        $term = trim($this->search);
        $today = now(config('sanad.display_timezone'))->toDateString();
        $like = '%'.addcslashes($term, '%_').'%';

        return view('affairs.duty', [
            'rosters' => DutyRoster::query()->orderBy('day_of_week')->orderBy('start_time')->get(),
            'rosterOptions' => DutyRoster::query()->orderBy('day_of_week')->get()->mapWithKeys(fn (DutyRoster $roster) => [$roster->id => DutyRoster::DAYS[$roster->day_of_week].', '.$roster->name])->all(),
            'assignments' => DutyAssignment::query()->with('roster')->whereDate('duty_date', '>=', $today)->orderBy('duty_date')->limit(50)->get(),
            'people' => mb_strlen($term) >= 2 ? [
                ...Student::query()->where('status', 'active')->where('name', 'ilike', $like)->limit(5)->get()->map(fn ($student) => ['value' => 'student:'.$student->id, 'name' => $student->name, 'kind' => 'Santri'])->all(),
                ...Teacher::query()->where('name', 'ilike', $like)->limit(5)->get()->map(fn ($teacher) => ['value' => 'teacher:'.$teacher->id, 'name' => $teacher->name, 'kind' => 'Ustadz'])->all(),
            ] : [],
            'personName' => $this->personName(),
            'days' => DutyRoster::DAYS,
        ]);
    }

    private function personName(): ?string
    {
        if ($this->person === '') {
            return null;
        }

        [$type, $id] = array_pad(explode(':', $this->person), 2, 0);

        return $type === 'student' ? Student::query()->whereKey((int) $id)->value('name') : Teacher::query()->whereKey((int) $id)->value('name');
    }
}
