<?php

namespace App\Modules\Pos\Livewire;

use App\Modules\Finance\Actions\FinanceException;
use App\Modules\Pos\Actions\ManagePayout;
use App\Modules\Pos\Models\Merchant;
use App\Modules\Pos\Models\MerchantLedgerEntry;
use App\Modules\Pos\Models\MerchantPayout;
use App\Modules\Pos\Support\SalesStats;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.staff', ['active' => 'merchants'])]
class SalesReport extends Component
{
    public ?Merchant $merchant = null;

    #[Url(as: 'dari')]
    public string $from = '';

    #[Url(as: 'sampai')]
    public string $to = '';

    public string $payout_amount = '';

    public string $payout_method = 'cash';

    public string $payout_note = '';

    public function mount(?Merchant $merchant = null): void
    {
        if ($merchant?->exists) {
            abort_unless(Merchant::query()->accessibleBy(auth()->user())->whereKey($merchant->id)->exists(), 403);
            $this->merchant = $merchant;
        } else {
            $this->merchant = null;
        }

        $today = now(config('sanad.display_timezone'));
        $this->from = self::validDate($this->from) ?? $today->copy()->startOfMonth()->toDateString();
        $this->to = self::validDate($this->to) ?? $today->toDateString();
    }

    public static function validDate(?string $value): ?string
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && checkdate((int) substr($value, 5, 2), (int) substr($value, 8, 2), (int) substr($value, 0, 4)) ? $value : null;
    }

    public function requestPayout(ManagePayout $payouts): void
    {
        abort_unless($this->merchant?->canBeManagedBy(auth()->user()), 403);
        $this->resetErrorBag();

        try {
            $payouts->request($this->merchant, (int) preg_replace('/\D/', '', $this->payout_amount), $this->payout_method, $this->payout_note, auth()->id());
        } catch (FinanceException $exception) {
            $this->addError('payout_amount', $exception->getMessage());

            return;
        }

        $this->reset('payout_amount', 'payout_note', 'payout_method');
        $this->dispatch('close-modal', 'ajukan-pencairan');
        $this->dispatch('toast', message: 'Pengajuan pencairan terkirim ke bendahara.', tone: 'success');
    }

    public function render(ManagePayout $payouts): View
    {
        $from = self::validDate($this->from) ?? now(config('sanad.display_timezone'))->toDateString();
        $to = self::validDate($this->to) ?? $from;
        $ids = $this->merchant ? [$this->merchant->id] : Merchant::query()->accessibleBy(auth()->user())->pluck('id')->all();

        return view('pos.report', [
            'summary' => SalesStats::summary($ids, $from, $to),
            'products' => SalesStats::byProduct($ids, $from, $to, 15),
            'categories' => SalesStats::byCategory($ids, $from, $to),
            'hours' => SalesStats::byHour($ids, $from, $to),
            'merchants' => $this->merchant ? null : SalesStats::byMerchant($ids, $from, $to),
            'balance' => $this->merchant?->balance(),
            'available' => $this->merchant ? $payouts->available($this->merchant) : null,
            'ledger' => $this->merchant ? MerchantLedgerEntry::query()->where('merchant_id', $this->merchant->id)->latest('id')->limit(15)->get() : collect(),
            'payouts' => $this->merchant ? MerchantPayout::query()->where('merchant_id', $this->merchant->id)->latest('id')->limit(10)->get() : collect(),
            'canManage' => (bool) $this->merchant?->canBeManagedBy(auth()->user()),
            'exportParams' => ['dari' => $from, 'sampai' => $to, 'merchant' => $this->merchant?->uuid],
        ])->title('Laporan '.($this->merchant?->name ?? 'Mart & Warung'));
    }
}
