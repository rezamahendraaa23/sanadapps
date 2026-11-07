<?php

namespace App\Modules\Academic\Livewire;

use App\Modules\Academic\Actions\ManageReportCards;
use App\Modules\Academic\Models\ReportCard;
use App\Modules\Academic\Support\AcademicException;
use App\Modules\Core\Models\Classroom;
use App\Modules\Core\Models\Term;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.staff', ['active' => 'grades'])]
#[Title('Rapor')]
class ReportCards extends Component
{
    #[Url(as: 'term', except: '')]
    public string $termId = '';

    #[Url(as: 'kelas', except: '')]
    public string $classroomId = '';

    #[Locked]
    public ?string $noteCard = null;

    public string $note = '';

    public function mount(): void
    {
        $this->termId = (string) (Term::query()->whereKey((int) $this->termId)->value('id') ?? Term::query()->where('is_active', true)->value('id') ?? '');
    }

    public function generate(ManageReportCards $cards): void
    {
        $this->resetErrorBag();

        if ($this->termId === '' || $this->classroomId === '') {
            $this->addError('classroomId', 'Pilih term dan kelas dulu.');

            return;
        }

        try {
            $count = $cards->generate((int) $this->termId, (int) $this->classroomId);
            $this->dispatch('toast', message: "{$count} rapor draf dibuat.", tone: 'success');
        } catch (AcademicException $exception) {
            $this->addError('classroomId', $exception->getMessage());
        }
    }

    public function publish(ManageReportCards $cards): void
    {
        $drafts = ReportCard::query()->where('term_id', (int) $this->termId)->where('classroom_id', (int) $this->classroomId)->where('status', 'draft')->get();
        $count = $cards->publish($drafts);

        $this->dispatch('close-modal', 'terbitkan-rapor');
        $this->dispatch('toast', message: "{$count} rapor terbit. Nilainya sekarang terkunci.", tone: 'success');
    }

    public function editNote(string $uuid): void
    {
        $card = ReportCard::query()->where('uuid', $uuid)->firstOrFail();
        $this->noteCard = $uuid;
        $this->note = (string) $card->homeroom_note;
        $this->dispatch('open-modal', 'catatan-rapor');
    }

    public function saveNote(): void
    {
        ReportCard::query()->where('uuid', $this->noteCard)->firstOrFail()->update(['homeroom_note' => mb_substr(trim($this->note), 0, 1000) ?: null]);
        $this->dispatch('close-modal', 'catatan-rapor');
    }

    public function render(): View
    {
        $cards = $this->termId !== '' && $this->classroomId !== ''
            ? ReportCard::query()->with(['student:id,name,nis'])->withAvg('items', 'final_score')->where('term_id', (int) $this->termId)->where('classroom_id', (int) $this->classroomId)->get()->sortBy('student.name')
            : collect();

        return view('academic.report-cards', [
            'cards' => $cards,
            'terms' => Term::query()->orderByDesc('start_date')->pluck('name', 'id')->all(),
            'classrooms' => Classroom::query()->orderBy('name')->pluck('name', 'id')->all(),
            'draftCount' => $cards->where('status', 'draft')->count(),
        ]);
    }
}
