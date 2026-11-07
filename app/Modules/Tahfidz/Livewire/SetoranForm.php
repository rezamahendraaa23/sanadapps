<?php

namespace App\Modules\Tahfidz\Livewire;

use App\Modules\Core\Models\Student;
use App\Modules\Tahfidz\Actions\RecordSetoran;
use App\Modules\Tahfidz\Models\QuranSurah;
use App\Modules\Tahfidz\Models\TahfidzRecord;
use App\Modules\Tahfidz\Support\TahfidzException;
use App\Modules\Tahfidz\Support\TahfidzProgress;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Setoran (U-05): dipakai ustadz, musyrif, dan pengelola; santri dibatasi visibleTo.
 */
#[Title('Tahfidz')]
class SetoranForm extends Component
{
    #[Url(as: 'santri', except: '')]
    public string $studentUuid = '';

    public string $search = '';

    public string $type = 'ziyadah';

    public string $surah_from = '';

    public string $ayah_from = '1';

    public string $surah_to = '';

    public string $ayah_to = '';

    public string $pages = '';

    public string $grade = 'jayyid';

    public string $notes = '';

    public string $record_date = '';

    public function mount(): void
    {
        $this->record_date = now(config('sanad.display_timezone'))->toDateString();
    }

    private function student(): ?Student
    {
        return Str::isUuid($this->studentUuid) ? Student::query()->visibleTo(auth()->user())->where('uuid', $this->studentUuid)->first() : null;
    }

    public function pick(string $uuid): void
    {
        $this->studentUuid = $uuid;
        $this->reset('search', 'surah_from', 'ayah_from', 'surah_to', 'ayah_to', 'pages', 'notes');
        $this->resetErrorBag();
    }

    public function updatedSurahFrom(): void
    {
        if ($this->surah_to === '') {
            $this->surah_to = $this->surah_from;
        }
    }

    /**
     * @param  bool  $next  Simpan lalu lanjut ke santri berikutnya.
     */
    public function save(RecordSetoran $action, bool $next = false): void
    {
        $student = $this->student();
        abort_if($student === null, 403);
        $this->resetErrorBag();

        try {
            $action->handle($student, [
                'type' => $this->type,
                'surah_from' => $this->surah_from,
                'ayah_from' => $this->ayah_from,
                'surah_to' => $this->surah_to,
                'ayah_to' => $this->ayah_to,
                'pages' => str_replace(',', '.', $this->pages),
                'grade' => $this->grade,
                'notes' => $this->notes,
                'record_date' => $this->record_date ?: now(config('sanad.display_timezone'))->toDateString(),
            ], auth()->id());
        } catch (TahfidzException $exception) {
            $this->addError(str_contains($exception->getMessage(), 'halaman') ? 'pages' : 'ayah_to', $exception->getMessage());

            return;
        }

        $this->reset('surah_from', 'ayah_from', 'surah_to', 'ayah_to', 'pages', 'notes');

        if ($next) {
            $this->reset('studentUuid');
        }

        $this->dispatch('toast', message: 'Setoran tersimpan.', tone: 'success');
    }

    public function render(): View
    {
        $user = auth()->user();
        $student = $this->student();
        $term = trim($this->search);
        $portal = ! $user->can('tahfidz.manage');
        $role = $user->hasRole('ustadz') ? 'ustadz' : 'musyrif';

        return view('tahfidz.setoran', [
            'student' => $student,
            'results' => mb_strlen($term) >= 2 ? Student::query()->visibleTo($user)->where(fn ($query) => $query->where('name', 'ilike', '%'.addcslashes($term, '%_').'%')->orWhere('nis', 'like', addcslashes($term, '%_').'%'))->orderBy('name')->limit(8)->get() : collect(),
            'roster' => $term === '' && ! $student && $portal ? Student::query()->visibleTo($user)->where('status', 'active')->orderBy('name')->limit(30)->get() : collect(),
            'surahs' => QuranSurah::query()->orderBy('number')->get(),
            'types' => TahfidzRecord::TYPES,
            'grades' => TahfidzRecord::GRADES,
            'history' => $student ? TahfidzRecord::query()->with(['surahFrom', 'surahTo'])->where('student_id', $student->id)->latest('record_date')->latest('id')->limit(10)->get() : collect(),
            'progress' => $student ? TahfidzProgress::forStudent($student) : null,
            'fromSurah' => $this->surah_from !== '' ? QuranSurah::query()->find((int) $this->surah_from) : null,
            'toSurah' => $this->surah_to !== '' ? QuranSurah::query()->find((int) $this->surah_to) : null,
        ])->layout($portal ? 'components.layouts.portal' : 'components.layouts.staff', $portal ? ['role' => $role, 'active' => 'tahfidz', 'title' => 'Setoran'] : ['active' => 'tahfidz']);
    }
}
