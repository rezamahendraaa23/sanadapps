<?php

namespace App\Modules\Wallet\Livewire;

use App\Modules\Core\Models\Student;
use App\Modules\Core\Support\ModuleRegistry;
use App\Modules\Finance\Actions\FinanceException;
use App\Modules\Finance\Models\BankAccount;
use App\Modules\Payment\Support\MidtransConfig;
use App\Modules\Wallet\Actions\CreateTopup;
use App\Modules\Wallet\Support\WalletSettings;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.portal', ['role' => 'wali', 'active' => 'wallet', 'title' => 'Top-up'])]
#[Title('Top-up')]
class GuardianTopup extends Component
{
    #[Url(as: 'anak', except: '')]
    public string $childUuid = '';

    public string $amount = '';

    public string $bank_account = '';

    private function child(): Student
    {
        $children = auth()->user()->guardian?->students()->orderBy('name')->get() ?? collect();
        $child = $children->firstWhere('uuid', $this->childUuid) ?? $children->first();
        abort_if($child === null, 404);

        return $child;
    }

    public function pickAmount(int $amount): void
    {
        $this->amount = (string) $amount;
        $this->resetErrorBag('amount');
    }

    private function digits(): int
    {
        return (int) preg_replace('/\D/', '', $this->amount);
    }

    public function payOnline(CreateTopup $action)
    {
        $this->resetErrorBag();

        try {
            $payment = $action->viaOnline($this->child(), $this->digits(), auth()->user()->guardian->id, auth()->id(), route('wali.wallet', ['anak' => $this->child()->uuid]));
        } catch (FinanceException $exception) {
            $this->addError('amount', $exception->getMessage());

            return null;
        }

        return $this->redirectRoute('wali.payments.show', $payment, navigate: true);
    }

    public function payByTransfer(CreateTopup $action)
    {
        $this->resetErrorBag();

        $account = BankAccount::query()->where('is_active', true)->where('uuid', $this->bank_account)->first();

        if ($account === null) {
            $this->addError('bank_account', 'Pilih rekening tujuan.');

            return null;
        }

        try {
            $payment = $action->viaTransfer($this->child(), $this->digits(), $account, auth()->user()->guardian->id, auth()->id());
        } catch (FinanceException $exception) {
            $this->addError('amount', $exception->getMessage());

            return null;
        }

        return $this->redirectRoute('wali.payments.show', $payment, navigate: true);
    }

    public function render(MidtransConfig $midtrans, ModuleRegistry $modules): View
    {
        $accounts = BankAccount::query()->where('is_active', true)->orderBy('bank_name')->get();

        if ($this->bank_account === '' && $accounts->count() === 1) {
            $this->bank_account = $accounts->first()->uuid;
        }

        return view('wallet.topup', [
            'child' => $this->child(),
            'accounts' => $accounts,
            'onlineEnabled' => $modules->enabled('payment_midtrans') && $midtrans->isReady(),
            'manualEnabled' => $modules->enabled('payment_manual'),
            'adminFee' => $midtrans->adminFee(),
            'limits' => WalletSettings::all(),
            'value' => $this->digits(),
        ]);
    }
}
