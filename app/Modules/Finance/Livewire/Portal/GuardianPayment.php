<?php

namespace App\Modules\Finance\Livewire\Portal;

use App\Modules\Finance\Actions\FinanceException;
use App\Modules\Finance\Actions\SubmitPaymentProof;
use App\Modules\Finance\Models\Payment;
use App\Modules\Payment\Actions\CancelOnlinePayment;
use App\Modules\Payment\Support\MidtransConfig;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.portal', ['role' => 'wali', 'active' => 'invoices', 'title' => 'Status Pembayaran'])]
#[Title('Status Pembayaran')]
class GuardianPayment extends Component
{
    use WithFileUploads;

    public Payment $payment;

    public $proof = null;

    public function mount(Payment $payment): void
    {
        $this->authorize('view', $payment);
        $this->payment = $payment;
    }

    public function submit(SubmitPaymentProof $action): void
    {
        $this->authorize('view', $this->payment);
        $this->resetErrorBag();

        $this->validateProof();

        try {
            $action->handle($this->payment, $this->proof);
        } catch (FinanceException $exception) {
            $this->addError('proof', $exception->getMessage());

            return;
        }

        $this->reset('proof');
        $this->dispatch('toast', message: 'Bukti terkirim. Bendahara akan memeriksanya.', tone: 'success');
    }

    /**
     * Berkas yang salah langsung ditolak saat dipilih, sebelum wali menekan kirim.
     */
    public function updatedProof(): void
    {
        $this->resetErrorBag();
        $this->validateProof();
    }

    private function validateProof(): void
    {
        $this->validate(['proof' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120'], [
            'proof.required' => 'Pilih foto atau PDF bukti transfer dulu.',
            'proof.mimes' => 'Bukti harus berformat JPG, PNG, atau PDF.',
            'proof.max' => 'Bukti terlalu besar. Maksimal 5 MB, coba foto ulang dengan resolusi lebih rendah.',
        ]);
    }

    public function cancelOnline(CancelOnlinePayment $action): void
    {
        $this->authorize('view', $this->payment);

        try {
            $action->handle($this->payment);
        } catch (FinanceException $exception) {
            $this->dispatch('toast', message: $exception->getMessage(), tone: 'danger');
        }
    }

    public function render(MidtransConfig $midtrans): View
    {
        $this->payment->refresh()->load(['invoices.student:id,name', 'bankAccount']);

        return view('finance.portal.payment', [
            'snapUrl' => $midtrans->snapScriptUrl(),
            'clientKey' => $midtrans->clientKey(),
        ]);
    }
}
