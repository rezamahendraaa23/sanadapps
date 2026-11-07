<?php

namespace App\Modules\Finance\Livewire\Portal;

use App\Modules\Finance\Models\Invoice;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.portal', ['role' => 'wali', 'active' => 'invoices', 'title' => 'Detail Tagihan'])]
class GuardianInvoiceShow extends Component
{
    public Invoice $invoice;

    public function mount(Invoice $invoice): void
    {
        $this->authorize('view', $invoice);
        $this->invoice = $invoice;
    }

    public function render(): View
    {
        $this->invoice->load(['student:id,name', 'items', 'payments']);

        return view('finance.portal.invoice-show')->title($this->invoice->invoice_no);
    }
}
