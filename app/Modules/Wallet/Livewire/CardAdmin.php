<?php

namespace App\Modules\Wallet\Livewire;

use App\Modules\Core\Models\Student;
use App\Modules\Finance\Actions\FinanceException;
use App\Modules\Wallet\Actions\IssueCard;
use App\Modules\Wallet\Actions\ManageCard;
use App\Modules\Wallet\Models\StudentCard;
use App\Modules\Wallet\Support\CardFields;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.staff', ['active' => 'cards'])]
#[Title('Kartu Santri')]
class CardAdmin extends Component
{
    use WithPagination;

    #[Url(as: 'cari', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $status = '';

    /** @var list<string> */
    public array $selected = [];

    public string $issueSearch = '';

    #[Locked]
    public ?string $issueStudent = null;

    public string $issueUid = '';

    #[Locked]
    public ?string $targetCard = null;

    public string $blockKind = 'blocked';

    public string $blockReason = '';

    public string $replaceUid = '';

    /** @var list<string> */
    public array $cardFields = [];

    public function mount(): void
    {
        $this->cardFields = CardFields::selected();
    }

    public function saveCardFields(): void
    {
        abort_unless(auth()->user()->can('cards.manage'), 403);
        CardFields::save($this->cardFields);
        $this->cardFields = CardFields::selected();
        $this->dispatch('close-modal', 'isi-kartu');
        $this->dispatch('toast', message: 'Isi kartu tersimpan. Berlaku untuk cetakan berikutnya.', tone: 'success');
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    private function card(string $uuid): StudentCard
    {
        abort_unless(auth()->user()->can('cards.manage'), 403);

        return StudentCard::query()->where('uuid', $uuid)->firstOrFail();
    }

    public function pickIssueStudent(string $uuid): void
    {
        $this->issueStudent = $uuid;
        $this->reset('issueSearch');
        $this->dispatch('focus-uid');
    }

    public function issue(IssueCard $action): void
    {
        abort_unless(auth()->user()->can('cards.manage'), 403);
        $this->resetErrorBag();

        $student = Student::query()->where('uuid', $this->issueStudent)->first();

        if ($student === null) {
            $this->addError('issueSearch', 'Pilih santri dulu.');

            return;
        }

        try {
            $action->handle($student, $this->issueUid);
        } catch (FinanceException $exception) {
            $this->addError('issueUid', $exception->getMessage());

            return;
        }

        $this->reset('issueStudent', 'issueUid', 'issueSearch');
        $this->dispatch('close-modal', 'terbitkan-kartu');
        $this->dispatch('toast', message: 'Kartu diterbitkan.', tone: 'success');
    }

    public function openBlock(string $uuid): void
    {
        $this->targetCard = $this->card($uuid)->uuid;
        $this->reset('blockKind', 'blockReason');
        $this->resetErrorBag();
        $this->dispatch('open-modal', 'blokir-kartu');
    }

    public function block(ManageCard $action): void
    {
        $this->resetErrorBag();

        if (trim($this->blockReason) === '') {
            $this->addError('blockReason', 'Tulis alasannya.');

            return;
        }

        $action->block($this->card((string) $this->targetCard), $this->blockKind === 'lost' ? 'lost' : 'blocked', trim($this->blockReason));

        $this->dispatch('close-modal', 'blokir-kartu');
        $this->dispatch('toast', message: 'Kartu diblokir. Saldo tetap di dompet.', tone: 'success');
    }

    public function unblock(string $uuid, ManageCard $action): void
    {
        try {
            $action->unblock($this->card($uuid));
            $this->dispatch('toast', message: 'Blokir kartu dibuka.', tone: 'success');
        } catch (FinanceException $exception) {
            $this->dispatch('toast', message: $exception->getMessage(), tone: 'danger');
        }
    }

    public function openReplace(string $uuid): void
    {
        $this->targetCard = $this->card($uuid)->uuid;
        $this->reset('replaceUid');
        $this->resetErrorBag();
        $this->dispatch('open-modal', 'ganti-kartu');
    }

    public function replace(ManageCard $action): void
    {
        $this->resetErrorBag();

        try {
            $action->replace($this->card((string) $this->targetCard), $this->replaceUid);
        } catch (FinanceException $exception) {
            $this->addError('replaceUid', $exception->getMessage());

            return;
        }

        $this->dispatch('close-modal', 'ganti-kartu');
        $this->dispatch('toast', message: 'Kartu pengganti aktif. Kartu lama tidak berlaku lagi.', tone: 'success');
    }

    public function resetPin(string $uuid, ManageCard $action): void
    {
        $action->resetPin($this->card($uuid));
        $this->dispatch('toast', message: 'PIN dihapus. Wali perlu membuat PIN baru.', tone: 'success');
    }

    public function render(): View
    {
        $term = trim($this->search);
        $issueTerm = trim($this->issueSearch);
        $visible = Student::query()->visibleTo(auth()->user())->select('students.id');

        return view('wallet.cards', [
            'cards' => StudentCard::query()
                ->with('student.classroom')
                ->whereIn('student_id', $visible)
                ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
                ->when($term !== '', fn ($query) => $query->whereHas('student', fn ($student) => $student
                    ->where('name', 'ilike', '%'.addcslashes($term, '%_').'%')->orWhere('nis', 'like', addcslashes($term, '%_').'%')))
                ->latest('id')
                ->paginate(25),
            'issueResults' => mb_strlen($issueTerm) >= 2
                ? Student::query()
                    ->where(fn ($query) => $query->where('name', 'ilike', '%'.addcslashes($issueTerm, '%_').'%')->orWhere('nis', 'like', addcslashes($issueTerm, '%_').'%'))
                    ->whereDoesntHave('cards', fn ($cards) => $cards->where('status', 'active'))
                    ->orderBy('name')->limit(6)->get()
                : collect(),
            'issueStudentModel' => $this->issueStudent ? Student::query()->where('uuid', $this->issueStudent)->first() : null,
        ]);
    }
}
