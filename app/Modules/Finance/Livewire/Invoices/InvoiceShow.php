<?php

namespace App\Modules\Finance\Livewire\Invoices;

use App\Modules\Finance\Actions\FinanceException;
use App\Modules\Finance\Actions\VoidInvoice;
use App\Modules\Finance\Models\Invoice;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.staff', ['active' => 'invoices'])]
class InvoiceShow extends Component
{
    public Invoice $invoice;

    public string $reason = '';

    public function mount(Invoice $invoice): void
    {
        $this->authorize('view', $invoice);
        $this->invoice = $invoice;
    }

    public function void(VoidInvoice $action): void
    {
        $this->authorize('void', $this->invoice);
        $this->resetErrorBag();

        try {
            $action->handle($this->invoice, $this->reason);
        } catch (FinanceException $exception) {
            $this->addError('reason', $exception->getMessage());

            return;
        }

        $this->reset('reason');
        $this->dispatch('close-modal', 'void-tagihan');
        $this->dispatch('toast', message: 'Tagihan dibatalkan.', tone: 'success');
    }

    public function render(): View
    {
        $this->invoice->load(['student.classroom', 'items', 'payments']);

        return view('finance.invoices.show', ['canVoid' => auth()->user()->can('void', $this->invoice) && $this->invoice->paid_total === 0 && $this->invoice->status !== 'void'])
            ->title($this->invoice->invoice_no);
    }
}
