<?php

namespace App\Modules\Finance\Livewire\Invoices;

use App\Modules\Core\Models\Student;
use App\Modules\Finance\Actions\CreateManualInvoice;
use App\Modules\Finance\Models\FeeType;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.staff', ['active' => 'invoices'])]
#[Title('Tagihan Manual')]
class InvoiceCreate extends Component
{
    public string $search = '';

    #[Locked]
    public ?int $studentId = null;

    public string $due_date = '';

    /** @var list<array{description: string, amount: string, fee_type_id: string}> */
    public array $items = [['description' => '', 'amount' => '', 'fee_type_id' => '']];

    public function pickStudent(string $uuid): void
    {
        $this->studentId = Student::query()->where('uuid', $uuid)->value('id');
        $this->reset('search');
    }

    public function clearStudent(): void
    {
        $this->reset('studentId');
    }

    public function addItem(): void
    {
        $this->items[] = ['description' => '', 'amount' => '', 'fee_type_id' => ''];
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function save(CreateManualInvoice $action)
    {
        $this->resetErrorBag();

        foreach ($this->items as $index => $item) {
            $this->items[$index]['amount'] = preg_replace('/\D/', '', (string) $item['amount']) ?? '';
        }

        if ($this->studentId === null) {
            $this->addError('search', 'Pilih santri dulu.');

            return null;
        }

        $this->validate([
            'due_date' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.description' => 'required|string|max:200',
            'items.*.amount' => 'required|integer|min:1|max:1000000000',
            'items.*.fee_type_id' => 'nullable|exists:fee_types,id',
        ], [], ['due_date' => 'jatuh tempo', 'items.*.description' => 'rincian', 'items.*.amount' => 'nominal']);

        $invoice = $action->handle(
            Student::query()->findOrFail($this->studentId),
            array_map(fn (array $item) => ['description' => $item['description'], 'amount' => (int) $item['amount'], 'fee_type_id' => $item['fee_type_id'] === '' ? null : (int) $item['fee_type_id']], $this->items),
            $this->due_date,
            auth()->id(),
        );

        return $this->redirectRoute('staff.invoices.show', $invoice, navigate: true);
    }

    public function render(): View
    {
        $term = trim($this->search);

        return view('finance.invoices.create', [
            'student' => $this->studentId ? Student::query()->with('classroom')->find($this->studentId) : null,
            'results' => mb_strlen($term) >= 2
                ? Student::query()->where('status', 'active')->where(fn ($query) => $query->where('name', 'ilike', '%'.addcslashes($term, '%_').'%')->orWhere('nis', 'like', addcslashes($term, '%_').'%'))->orderBy('name')->limit(8)->get(['uuid', 'name', 'nis'])
                : collect(),
            'feeTypes' => FeeType::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all(),
        ]);
    }
}
