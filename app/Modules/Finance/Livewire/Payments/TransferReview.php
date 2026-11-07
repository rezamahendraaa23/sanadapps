<?php

namespace App\Modules\Finance\Livewire\Payments;

use App\Modules\Finance\Actions\ApplySettlement;
use App\Modules\Finance\Actions\FinanceException;
use App\Modules\Finance\Actions\RejectManualPayment;
use App\Modules\Finance\Models\Payment;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.staff', ['active' => 'transfer-review'])]
#[Title('Verifikasi Transfer')]
class TransferReview extends Component
{
    #[Url(as: 'bukti', except: '')]
    public string $current = '';

    public string $reason = '';

    public function select(string $uuid): void
    {
        $this->current = $uuid;
        $this->resetErrorBag();
    }

    public function verify(ApplySettlement $action): void
    {
        $payment = $this->currentPayment();
        abort_if($payment === null, 404);
        $this->authorize('verify', $payment);

        try {
            $action->handle($payment, auth()->id());
            $this->dispatch('toast', message: "{$payment->payment_no} terverifikasi. Tagihan lunas.", tone: 'success');
        } catch (FinanceException $exception) {
            $this->dispatch('toast', message: $exception->getMessage(), tone: 'danger');
        }

        $this->current = '';
    }

    public function reject(RejectManualPayment $action): void
    {
        $payment = $this->currentPayment();
        abort_if($payment === null, 404);
        $this->authorize('verify', $payment);
        $this->resetErrorBag();

        try {
            $action->handle($payment, $this->reason, auth()->id());
        } catch (FinanceException $exception) {
            $this->addError('reason', $exception->getMessage());

            return;
        }

        $this->reset('reason', 'current');
        $this->dispatch('close-modal', 'tolak-bukti');
        $this->dispatch('toast', message: "{$payment->payment_no} ditolak.", tone: 'success');
    }

    private function currentPayment(): ?Payment
    {
        return $this->current === '' ? null : Payment::query()->where('uuid', $this->current)->where('status', 'review')->first();
    }

    public function render(): View
    {
        $queue = Payment::query()
            ->where('method', 'manual_transfer')
            ->where('status', 'review')
            ->with(['invoices.student:id,name,nis', 'topup.wallet.student:id,name,nis', 'bankAccount'])
            ->oldest('updated_at')
            ->get();

        // Setelah satu item selesai, langsung lanjut ke yang berikutnya (PRD UI/UX F-04).
        $current = $queue->firstWhere('uuid', $this->current) ?? $queue->first();
        $this->current = (string) $current?->uuid;

        return view('finance.payments.transfer-review', ['queue' => $queue, 'selected' => $current]);
    }
}
