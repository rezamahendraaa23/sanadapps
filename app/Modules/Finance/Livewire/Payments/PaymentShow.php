<?php

namespace App\Modules\Finance\Livewire\Payments;

use App\Modules\Finance\Actions\FinanceException;
use App\Modules\Finance\Actions\RefundPayment;
use App\Modules\Finance\Models\Payment;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.staff', ['active' => 'payments'])]
class PaymentShow extends Component
{
    public Payment $payment;

    public string $reason = '';

    public function mount(Payment $payment): void
    {
        $this->authorize('view', $payment);
        $this->payment = $payment;
    }

    public function refund(RefundPayment $action): void
    {
        $this->authorize('refund', $this->payment);
        $this->resetErrorBag();

        try {
            $action->handle($this->payment, $this->reason, auth()->id());
        } catch (FinanceException $exception) {
            $this->addError('reason', $exception->getMessage());

            return;
        }

        $this->reset('reason');
        $this->dispatch('close-modal', 'kembalikan-dana');
        $this->dispatch('toast', message: 'Pengembalian dicatat. Tagihan kembali terbuka.', tone: 'success');
    }

    public function render(): View
    {
        $this->payment->load(['invoices.student:id,name,nis', 'bankAccount', 'verifier:id,name']);

        return view('finance.payments.show')->title($this->payment->payment_no);
    }
}
