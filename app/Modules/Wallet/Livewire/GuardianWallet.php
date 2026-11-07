<?php

namespace App\Modules\Wallet\Livewire;

use App\Modules\Core\Models\Student;
use App\Modules\Core\Support\ModuleRegistry;
use App\Modules\Finance\Actions\FinanceException;
use App\Modules\Finance\Models\Payment;
use App\Modules\Pos\Models\PosOrder;
use App\Modules\Wallet\Actions\ManageCard;
use App\Modules\Wallet\Models\StudentCard;
use App\Modules\Wallet\Models\Wallet;
use App\Modules\Wallet\Models\WalletTopup;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.portal', ['role' => 'wali', 'active' => 'wallet', 'title' => 'Dompet'])]
#[Title('Dompet')]
class GuardianWallet extends Component
{
    use WithPagination;

    #[Url(as: 'anak', except: '')]
    public string $childUuid = '';

    public string $daily_limit = '';

    public string $per_transaction_limit = '';

    public bool $wallet_blocked = false;

    public string $pin = '';

    public string $pin_confirmation = '';

    public string $blockReason = 'Kartu hilang';

    #[Locked]
    public ?int $receiptOrder = null;

    public function mount(): void
    {
        $this->fillLimits();
    }

    /**
     * @return Collection<int, Student>
     */
    private function children(): Collection
    {
        return auth()->user()->guardian?->students()->orderBy('name')->get() ?? collect();
    }

    /**
     * Anak selalu diambil dari relasi wali, jadi uuid milik santri lain tidak pernah cocok.
     */
    private function child(): ?Student
    {
        $children = $this->children();

        return $children->firstWhere('uuid', $this->childUuid) ?? $children->first();
    }

    private function wallet(): Wallet
    {
        $child = $this->child();
        abort_if($child === null, 404);

        return Wallet::forStudent($child);
    }

    private function card(): ?StudentCard
    {
        $child = $this->child();

        return $child ? StudentCard::query()->where('student_id', $child->id)->where('status', 'active')->first() : null;
    }

    private function fillLimits(): void
    {
        if ($this->child() === null) {
            return;
        }

        $wallet = $this->wallet();
        $this->daily_limit = (string) $wallet->daily_limit;
        $this->per_transaction_limit = (string) $wallet->per_transaction_limit;
        $this->wallet_blocked = $wallet->status === 'blocked';
    }

    public function selectChild(string $uuid): void
    {
        abort_unless($this->children()->contains('uuid', $uuid), 404);

        $this->childUuid = $uuid;
        $this->resetPage();
        $this->fillLimits();
    }

    public function saveLimits(): void
    {
        $digits = fn (string $value) => $value === '' ? null : (int) preg_replace('/\D/', '', $value);

        $this->wallet()->update([
            'daily_limit' => $digits($this->daily_limit),
            'per_transaction_limit' => $digits($this->per_transaction_limit),
            'status' => $this->wallet_blocked ? 'blocked' : 'active',
        ]);

        $this->dispatch('close-modal', 'atur-limit');
        $this->dispatch('toast', message: 'Aturan dompet tersimpan dan langsung berlaku.', tone: 'success');
    }

    public function blockCard(ManageCard $action): void
    {
        $card = $this->card();
        abort_if($card === null, 404);

        $action->block($card, 'blocked', 'Diblokir wali: '.mb_substr(trim($this->blockReason) ?: 'tanpa alasan', 0, 100));

        $this->dispatch('close-modal', 'blokir-kartu');
        $this->dispatch('toast', message: 'Kartu diblokir. Saldo tetap aman. Hubungi admin untuk membuka atau mengganti kartu.', tone: 'success');
    }

    public function savePin(ManageCard $action): void
    {
        $card = $this->card();
        abort_if($card === null, 404);
        $this->resetErrorBag();

        if ($this->pin !== $this->pin_confirmation) {
            $this->addError('pin_confirmation', 'Kedua PIN belum sama.');

            return;
        }

        try {
            $action->setPin($card, $this->pin);
        } catch (FinanceException $exception) {
            $this->addError('pin', $exception->getMessage());

            return;
        }

        $this->reset('pin', 'pin_confirmation');
        $this->dispatch('close-modal', 'atur-pin');
        $this->dispatch('toast', message: 'PIN tersimpan.', tone: 'success');
    }

    public function openReceipt(string $transactionUuid): void
    {
        $transaction = $this->wallet()->transactions()->where('uuid', $transactionUuid)->where('ref_type', 'pos_order')->firstOrFail();
        $this->receiptOrder = $transaction->ref_id;
        $this->dispatch('open-modal', 'struk');
    }

    public function render(): View
    {
        $child = $this->child();
        $wallet = $child ? Wallet::forStudent($child) : null;

        return view('wallet.guardian', [
            'children' => $this->children(),
            'posEnabled' => app(ModuleRegistry::class)->enabled('pos'),
            'child' => $child,
            'wallet' => $wallet,
            'card' => $this->card(),
            'hasBlockedCard' => $child && StudentCard::query()->where('student_id', $child->id)->where('status', '!=', 'active')->exists(),
            'spentToday' => $wallet?->spentToday() ?? 0,
            'transactions' => $wallet?->transactions()->with('merchant:id,name')->latest('id')->paginate(20),
            'pending' => $wallet
                ? Payment::query()->whereIn('id', WalletTopup::query()->where('wallet_id', $wallet->id)->where('status', 'pending')->select('payment_id'))->whereIn('status', ['pending', 'review'])->latest('id')->get()
                : collect(),
            'order' => $this->receiptOrder && $wallet
                ? PosOrder::query()->with(['items', 'merchant:id,name', 'walletTransaction'])->where('student_id', $child->id)->find($this->receiptOrder)
                : null,
        ]);
    }
}
