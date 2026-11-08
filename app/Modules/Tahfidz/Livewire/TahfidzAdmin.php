<?php

namespace App\Modules\Tahfidz\Livewire;

use App\Modules\Academic\Support\StudentSchedule;
use App\Modules\Core\Models\Classroom;
use App\Modules\Core\Models\Student;
use App\Modules\Tahfidz\Models\MutabaahItem;
use App\Modules\Tahfidz\Models\TahfidzTarget;
use App\Modules\Tahfidz\Support\TahfidzRecap;
use App\Support\SchoolDate;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.staff', ['active' => 'tahfidz'])]
#[Title('Tahfidz')]
class TahfidzAdmin extends Component
{
    #[Url(except: 'rekap')]
    public string $tab = 'rekap';

    #[Url(as: 'kelas', except: '')]
    public string $classroom = '';

    #[Url(as: 'dari')]
    public string $from = '';

    #[Url(as: 'sampai')]
    public string $to = '';

    public string $item_name = '';

    public string $item_category = '';

    public string $target_student = '';

    public string $target_pages = '';

    public string $target_juz = '';

    public string $target_classroom = '';

    public function mount(): void
    {
        $today = now(config('sanad.display_timezone'));
        $this->from = TahfidzRecap::date($this->from) ?? $today->copy()->startOfMonth()->toDateString();
        $this->to = TahfidzRecap::date($this->to) ?? $today->toDateString();
    }

    public function selectTab(string $tab): void
    {
        $this->tab = in_array($tab, ['rekap', 'target', 'amalan'], true) ? $tab : 'rekap';
        $this->resetErrorBag();
    }

    public function saveItem(): void
    {
        $this->resetErrorBag();
        $this->validate(['item_name' => 'required|string|max:100', 'item_category' => 'nullable|string|max:50'], [], ['item_name' => 'nama amalan', 'item_category' => 'kategori']);

        MutabaahItem::create(['name' => $this->item_name, 'category' => $this->item_category ?: null]);
        $this->reset('item_name', 'item_category');
    }

    public function toggleItem(string $uuid): void
    {
        abort_unless(Str::isUuid($uuid), 404);
        $item = MutabaahItem::query()->where('uuid', $uuid)->firstOrFail();
        $item->update(['is_active' => ! $item->is_active]);
    }

    /**
     * Target untuk satu kelas sekaligus atau satu santri (THF-02).
     */
    public function saveTarget(): void
    {
        $this->resetErrorBag();
        $term = StudentSchedule::activeTerm();

        if ($term === null) {
            $this->addError('target_classroom', 'Belum ada periode aktif.');

            return;
        }

        $this->validate(['target_pages' => 'nullable|numeric|min:0|max:604', 'target_juz' => 'nullable|numeric|min:0|max:30'], [], ['target_pages' => 'target halaman', 'target_juz' => 'target juz']);

        if ((float) $this->target_pages <= 0 && (float) $this->target_juz <= 0) {
            $this->addError('target_pages', 'Isi target halaman atau juz.');

            return;
        }

        if ($this->target_classroom === '') {
            $this->addError('target_classroom', 'Pilih kelas.');

            return;
        }

        $students = Student::query()->where('status', 'active')->where('classroom_id', (int) $this->target_classroom)->pluck('id');

        foreach ($students as $studentId) {
            TahfidzTarget::query()->updateOrCreate(['student_id' => $studentId, 'term_id' => $term->id], ['target_pages' => (float) $this->target_pages, 'target_juz' => (float) $this->target_juz]);
        }

        $this->dispatch('toast', message: "Target tersimpan untuk {$students->count()} santri.", tone: 'success');
    }

    public function render(): View
    {
        $from = TahfidzRecap::date($this->from) ?? SchoolDate::today()->toDateString();
        $to = TahfidzRecap::date($this->to) ?? $from;

        return view('tahfidz.admin', [
            'rows' => $this->tab === 'rekap' ? TahfidzRecap::rows($this->classroom !== '' ? (int) $this->classroom : null, $from, $to) : collect(),
            'classrooms' => Classroom::query()->orderBy('name')->pluck('name', 'id')->all(),
            'items' => MutabaahItem::query()->orderBy('name')->get(),
            'params' => array_filter(['kelas' => $this->classroom, 'dari' => $from, 'sampai' => $to]),
            'hasTerm' => StudentSchedule::activeTerm() !== null,
        ]);
    }
}
