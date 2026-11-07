<?php

namespace App\Modules\Finance\Livewire\Payments;

use App\Modules\Core\Models\Student;
use App\Modules\Finance\Actions\FinanceException;
use App\Modules\Finance\Actions\RecordCashPayment;
use App\Modules\Finance\Models\Invoice;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.staff', ['active' => 'payments'])]
#[Title('Pembayaran Tunai')]
class CashPayment extends Component
{
    public string $search = '';

    #[Locked]
    public ?int $studentId = null;

    /** @var list<string> */
    public array $selected = [];

    public string $received = '';

    public string $amount = '';

    public function pickStudent(string $uuid): void
    {
        $this->studentId = Student::query()->where('uuid', $uuid)->value('id');
        $this->reset('search', 'received', 'amount');
        $this->selected = $this->openInvoices()->pluck('uuid')->all();
        $this->amount = (string) $this->selectedTotal();
    }

    public function clearStudent(): void
    {
        $this->reset('studentId', 'selected', 'received', 'amount');
        $this->resetErrorBag();
    }

    public function updatedSelected(): void
    {
        $this->amount = (string) $this->selectedTotal();
    }

    public function record(RecordCashPayment $action)
    {
        $this->resetErrorBag();

        $amount = (int) preg_replace('/\D/', '', $this->amount);
        $received = (int) preg_replace('/\D/', '', $this->received);

        if ($this->selected === []) {
            $this->addError('selected', 'Pilih minimal satu tagihan.');

            return null;
        }

        if ($amount <= 0) {
            $this->addError('amount', 'Isi nominal yang dibayarkan.');

            return null;
        }

        if ($received < $amount) {
            $this->addError('received', 'Uang diterima kurang dari nominal yang dibayarkan.');

            return null;
        }

        $ids = $this->openInvoices()->whereIn('uuid', $this->selected)->pluck('id')->all();

        try {
            $payment = $action->handle($ids, $amount, auth()->id());
        } catch (FinanceException $exception) {
            $this->addError('amount', $exception->getMessage());

            return null;
        }

        return $this->redirectRoute('staff.payments.show', $payment);
    }

    private function openInvoices()
    {
        return $this->studentId
            ? Invoice::query()->where('student_id', $this->studentId)->open()->orderBy('due_date')->get()
            : collect();
    }

    private function selectedTotal(): int
    {
        return $this->openInvoices()->whereIn('uuid', $this->selected)->sum(fn (Invoice $invoice) => $invoice->outstanding());
    }

    public function render(): View
    {
        $term = trim($this->search);
        $amount = (int) preg_replace('/\D/', '', $this->amount);
        $received = (int) preg_replace('/\D/', '', $this->received);

        return view('finance.payments.cash', [
            'student' => $this->studentId ? Student::query()->with('classroom')->find($this->studentId) : null,
            'invoices' => $this->openInvoices(),
            'results' => mb_strlen($term) >= 2
                ? Student::query()->where(fn ($query) => $query->where('name', 'ilike', '%'.addcslashes($term, '%_').'%')->orWhere('nis', 'like', addcslashes($term, '%_').'%'))->orderBy('name')->limit(8)->get(['uuid', 'name', 'nis'])
                : collect(),
            'total' => $this->selectedTotal(),
            'change' => max(0, $received - $amount),
        ]);
    }
}
