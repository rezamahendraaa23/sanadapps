<?php

namespace App\Modules\Finance\Livewire\Portal;

use App\Modules\Core\Support\ModuleRegistry;
use App\Modules\Finance\Actions\CreateManualPayment;
use App\Modules\Finance\Actions\FinanceException;
use App\Modules\Finance\Models\BankAccount;
use App\Modules\Finance\Models\Invoice;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.portal', ['role' => 'wali', 'active' => 'invoices', 'title' => 'Pembayaran'])]
#[Title('Pembayaran')]
class GuardianCheckout extends Component
{
    #[Url(as: 'tagihan')]
    #[Locked]
    public string $invoiceUuids = '';

    public string $bank_account = '';

    public function mount(): void
    {
        $this->bank_account = (string) BankAccount::query()->where('is_active', true)->orderBy('sort_order')->value('uuid');
    }

    /**
     * Hanya tagihan terbuka milik anak sendiri yang lolos, apa pun isi alamat URL.
     *
     * @return Collection<int, Invoice>
     */
    private function invoices(): Collection
    {
        return Invoice::query()
            ->visibleTo(auth()->user())
            ->open()
            ->with('student:id,name')
            ->whereIn('uuid', array_filter(explode(',', $this->invoiceUuids)))
            ->orderBy('due_date')
            ->get();
    }

    public function payByTransfer(CreateManualPayment $action)
    {
        $this->resetErrorBag();
        $account = BankAccount::query()->where('is_active', true)->where('uuid', $this->bank_account)->first();

        if ($account === null) {
            $this->addError('bank_account', 'Pilih rekening tujuan.');

            return null;
        }

        $invoices = $this->invoices();

        if ($invoices->isEmpty()) {
            $this->addError('bank_account', 'Tidak ada tagihan yang perlu dibayar.');

            return null;
        }

        try {
            $payment = $action->handle($invoices, $account, auth()->user()->guardian?->id, auth()->id());
        } catch (FinanceException $exception) {
            $this->addError('bank_account', $exception->getMessage());

            return null;
        }

        return $this->redirectRoute('wali.payments.show', $payment);
    }

    public function render(ModuleRegistry $modules): View
    {
        return view('finance.portal.checkout', [
            'invoices' => $this->invoices(),
            'accounts' => BankAccount::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'manualEnabled' => $modules->enabled('payment_manual'),
        ]);
    }
}
