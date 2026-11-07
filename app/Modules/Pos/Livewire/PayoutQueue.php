<?php

namespace App\Modules\Pos\Livewire;

use App\Modules\Finance\Actions\FinanceException;
use App\Modules\Pos\Actions\ManagePayout;
use App\Modules\Pos\Models\MerchantPayout;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.staff', ['active' => 'payouts'])]
#[Title('Pencairan Merchant')]
class PayoutQueue extends Component
{
    use WithPagination;

    #[Url(except: 'aktif')]
    public string $filter = 'aktif';

    #[Locked]
    public ?string $target = null;

    public string $rejectReason = '';

    public function updatingFilter(): void
    {
        $this->resetPage();
    }

    private function payout(string $uuid): MerchantPayout
    {
        return MerchantPayout::query()->where('uuid', $uuid)->firstOrFail();
    }

    public function approve(string $uuid, ManagePayout $payouts): void
    {
        $this->run(fn () => $payouts->approve($this->payout($uuid), auth()->id()), 'Pencairan disetujui.');
    }

    public function pay(string $uuid, ManagePayout $payouts): void
    {
        $this->run(fn () => $payouts->pay($this->payout($uuid), auth()->id()), 'Pencairan dibayar dan tercatat di kas.');
    }

    public function askReject(string $uuid): void
    {
        $this->target = $uuid;
        $this->reset('rejectReason');
        $this->resetErrorBag();
        $this->dispatch('open-modal', 'tolak-pencairan');
    }

    public function reject(ManagePayout $payouts): void
    {
        $this->resetErrorBag();

        try {
            $payouts->reject($this->payout((string) $this->target), $this->rejectReason, auth()->id());
        } catch (FinanceException $exception) {
            $this->addError('rejectReason', $exception->getMessage());

            return;
        }

        $this->dispatch('close-modal', 'tolak-pencairan');
        $this->dispatch('toast', message: 'Pencairan ditolak.', tone: 'success');
    }

    private function run(callable $action, string $message): void
    {
        try {
            $action();
            $this->dispatch('toast', message: $message, tone: 'success');
        } catch (FinanceException $exception) {
            $this->dispatch('toast', message: $exception->getMessage(), tone: 'danger');
        }
    }

    public function render(): View
    {
        return view('pos.payouts', [
            'payouts' => MerchantPayout::query()->with(['merchant:id,name', 'requester:id,name'])
                ->when($this->filter === 'aktif', fn ($query) => $query->whereIn('status', ['requested', 'approved']))
                ->latest('id')->paginate(25),
            'labels' => MerchantPayout::LABELS,
        ]);
    }
}
