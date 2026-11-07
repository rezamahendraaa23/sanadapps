<?php

namespace App\Modules\Wallet\Livewire;

use App\Modules\Core\Models\Module;
use App\Modules\Core\Models\Student;
use App\Modules\Finance\Actions\FinanceException;
use App\Modules\Wallet\Actions\AdjustWallet;
use App\Modules\Wallet\Actions\RecordCashTopup;
use App\Modules\Wallet\Actions\WithdrawBalance;
use App\Modules\Wallet\Models\StudentCard;
use App\Modules\Wallet\Models\Wallet;
use App\Modules\Wallet\Support\WalletSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.staff', ['active' => 'wallets'])]
#[Title('Dompet')]
class WalletAdmin extends Component
{
    use WithPagination;

    #[Url(as: 'santri', except: '')]
    public string $studentUuid = '';

    public string $search = '';

    public string $topup_amount = '';

    public string $adjust_direction = 'credit';

    public string $adjust_amount = '';

    public string $adjust_reason = '';

    public string $withdraw_receiver = '';

    public string $daily_limit = '';

    public string $per_transaction_limit = '';

    public string $pin_threshold = '';

    public string $low_balance = '';

    public string $topup_min = '';

    public string $topup_max = '';

    public function mount(): void
    {
        foreach (WalletSettings::all() as $key => $value) {
            $this->{$key} = (string) $value;
        }

        $this->fillLimits();
    }

    public function pick(string $uuid): void
    {
        $this->studentUuid = $uuid;
        $this->reset('search');
        $this->resetPage();
        $this->resetErrorBag();
        $this->fillLimits();
    }

    public function clearStudent(): void
    {
        $this->reset('studentUuid');
    }

    private function student(): ?Student
    {
        return ! Str::isUuid($this->studentUuid) ? null : Student::query()->visibleTo(auth()->user())->where('uuid', $this->studentUuid)->first();
    }

    private function wallet(): Wallet
    {
        $student = $this->student();
        abort_if($student === null, 404);

        return Wallet::forStudent($student);
    }

    private function fillLimits(): void
    {
        $student = $this->student();

        if ($student !== null) {
            $wallet = Wallet::forStudent($student);
            $this->daily_limit = (string) $wallet->daily_limit;
            $this->per_transaction_limit = (string) $wallet->per_transaction_limit;
        }
    }

    private function digits(string $value): int
    {
        return (int) preg_replace('/\D/', '', $value);
    }

    public function topup(RecordCashTopup $action): void
    {
        abort_unless(auth()->user()->can('wallet.manage'), 403);
        $this->resetErrorBag();

        try {
            $action->handle($this->wallet()->student, $this->digits($this->topup_amount), auth()->id());
        } catch (FinanceException $exception) {
            $this->addError('topup_amount', $exception->getMessage());

            return;
        }

        $this->reset('topup_amount');
        $this->dispatch('close-modal', 'topup-tunai');
        $this->dispatch('toast', message: 'Top-up tunai tercatat.', tone: 'success');
    }

    public function adjust(AdjustWallet $action): void
    {
        abort_unless(auth()->user()->can('wallet.manage'), 403);
        $this->resetErrorBag();

        if ($this->digits($this->adjust_amount) <= 0) {
            $this->addError('adjust_amount', 'Isi nominal penyesuaian.');

            return;
        }

        try {
            $action->handle($this->wallet(), $this->adjust_direction === 'debit' ? 'debit' : 'credit', $this->digits($this->adjust_amount), $this->adjust_reason, auth()->id());
        } catch (FinanceException $exception) {
            $this->addError('adjust_reason', $exception->getMessage());

            return;
        }

        $this->reset('adjust_amount', 'adjust_reason', 'adjust_direction');
        $this->dispatch('close-modal', 'penyesuaian');
        $this->dispatch('toast', message: 'Penyesuaian tercatat.', tone: 'success');
    }

    public function withdraw(WithdrawBalance $action): void
    {
        abort_unless(auth()->user()->can('wallet.manage'), 403);
        $this->resetErrorBag();

        try {
            $action->handle($this->wallet(), $this->withdraw_receiver, auth()->id());
        } catch (FinanceException $exception) {
            $this->addError('withdraw_receiver', $exception->getMessage());

            return;
        }

        $this->reset('withdraw_receiver');
        $this->dispatch('close-modal', 'tarik-saldo');
        $this->dispatch('toast', message: 'Penarikan saldo tercatat.', tone: 'success');
    }

    public function saveLimits(): void
    {
        abort_unless(auth()->user()->can('wallet.manage'), 403);

        $this->wallet()->update([
            'daily_limit' => $this->daily_limit === '' ? null : $this->digits($this->daily_limit),
            'per_transaction_limit' => $this->per_transaction_limit === '' ? null : $this->digits($this->per_transaction_limit),
        ]);

        $this->dispatch('toast', message: 'Limit tersimpan.', tone: 'success');
    }

    public function toggleWallet(): void
    {
        abort_unless(auth()->user()->can('wallet.manage'), 403);

        $wallet = $this->wallet();
        $wallet->update(['status' => $wallet->status === 'active' ? 'blocked' : 'active']);
    }

    public function saveSettings(): void
    {
        abort_unless(auth()->user()->can('wallet.manage'), 403);
        $this->resetErrorBag();

        $values = array_map(fn ($key) => $this->digits($this->{$key}), array_combine(array_keys(WalletSettings::DEFAULTS), array_keys(WalletSettings::DEFAULTS)));

        if ($values['topup_min'] < 1 || $values['topup_max'] <= $values['topup_min']) {
            $this->addError('topup_max', 'Maksimum harus lebih besar dari minimum.');

            return;
        }

        $module = Module::query()->where('module_key', 'wallet')->firstOrFail();
        $module->update(['settings' => [...($module->settings ?? []), ...$values]]);

        $this->dispatch('close-modal', 'aturan-dompet');
        $this->dispatch('toast', message: 'Aturan dompet tersimpan.', tone: 'success');
    }

    public function render(): View
    {
        $student = $this->student();
        $wallet = $student ? Wallet::forStudent($student) : null;
        $term = trim($this->search);

        return view('wallet.admin', [
            'student' => $student?->load('classroom'),
            'wallet' => $wallet,
            'card' => $student ? StudentCard::query()->where('student_id', $student->id)->latest('id')->first() : null,
            'transactions' => $wallet?->transactions()->with('merchant:id,name')->latest('id')->paginate(20),
            'spentToday' => $wallet?->spentToday() ?? 0,
            'results' => mb_strlen($term) >= 2
                ? Student::query()->visibleTo(auth()->user())
                    ->where(fn ($query) => $query->where('name', 'ilike', '%'.addcslashes($term, '%_').'%')->orWhere('nis', 'like', addcslashes($term, '%_').'%'))
                    ->orderBy('name')->limit(8)->get()
                : collect(),
            'totalBalance' => auth()->user()->can('wallet.manage') ? (int) Wallet::query()->sum('balance') : null,
            'walletCount' => auth()->user()->can('wallet.manage') ? Wallet::query()->where('balance', '>', 0)->count() : 0,
        ]);
    }
}
