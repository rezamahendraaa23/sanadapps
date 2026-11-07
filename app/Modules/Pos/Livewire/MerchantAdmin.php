<?php

namespace App\Modules\Pos\Livewire;

use App\Modules\Core\Models\User;
use App\Modules\Pos\Models\Merchant;
use App\Modules\Pos\Models\MerchantProduct;
use App\Modules\Pos\Support\SalesStats;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.staff', ['active' => 'merchants'])]
#[Title('Mart & Warung')]
class MerchantAdmin extends Component
{
    #[Locked]
    public ?int $editingMerchant = null;

    public string $name = '';

    public string $type = 'warung';

    public string $location = '';

    public string $commission_percent = '0';

    public string $pin_threshold = '';

    public string $pic_user_id = '';

    /** @var list<string> */
    public array $cashiers = [];

    public bool $is_active = true;

    /** Terima tunai untuk pembeli bukan santri (ustadz, tamu, wali berkunjung). */
    public bool $accepts_cash = false;

    public function createMerchant(): void
    {
        abort_unless(auth()->user()->can('merchants.manage'), 403);
        $this->reset('editingMerchant', 'name', 'type', 'location', 'commission_percent', 'pin_threshold', 'pic_user_id', 'cashiers', 'is_active', 'accepts_cash');
        $this->resetErrorBag();
        $this->dispatch('open-modal', 'form-merchant');
    }

    public function editMerchant(string $uuid): void
    {
        abort_unless(auth()->user()->can('merchants.manage'), 403);
        $merchant = Merchant::query()->with('cashiers:id')->where('uuid', $uuid)->firstOrFail();

        $this->editingMerchant = $merchant->id;
        $this->fill([
            'name' => $merchant->name,
            'type' => $merchant->type,
            'location' => (string) $merchant->location,
            'commission_percent' => (string) (float) $merchant->commission_percent,
            'pin_threshold' => (string) $merchant->pin_threshold,
            'pic_user_id' => (string) $merchant->pic_user_id,
            'cashiers' => $merchant->cashiers->pluck('id')->map(fn ($id) => (string) $id)->all(),
            'is_active' => $merchant->is_active,
            'accepts_cash' => in_array('cash', $merchant->payment_modes ?? [], true),
        ]);
        $this->resetErrorBag();
        $this->dispatch('open-modal', 'form-merchant');
    }

    public function saveMerchant(): void
    {
        abort_unless(auth()->user()->can('merchants.manage'), 403);
        $this->resetErrorBag();
        $this->validate([
            'name' => 'required|string|max:100',
            'type' => 'required|in:mart,warung,kantin',
            'location' => 'nullable|string|max:150',
            'commission_percent' => 'required|numeric|min:0|max:50',
            'pic_user_id' => 'nullable|integer|exists:users,id',
            'cashiers.*' => 'integer|exists:users,id',
        ], [], ['name' => 'nama', 'commission_percent' => 'komisi']);

        $threshold = preg_replace('/\D/', '', $this->pin_threshold);
        $data = [
            'name' => $this->name,
            'type' => $this->type,
            'location' => $this->location ?: null,
            'commission_percent' => $this->commission_percent,
            'pin_threshold' => $threshold === '' ? null : (int) $threshold,
            'pic_user_id' => $this->pic_user_id ?: null,
            'is_active' => $this->is_active,
        ];

        $existing = $this->editingMerchant ? Merchant::query()->findOrFail($this->editingMerchant) : null;
        $modes = array_values(array_diff($existing?->payment_modes ?? ['card', 'qr'], ['cash']));
        $data['payment_modes'] = $this->accepts_cash ? [...$modes, 'cash'] : $modes;

        $merchant = $existing ? tap($existing)->update($data) : Merchant::create($data);

        $merchant->cashiers()->sync($this->cashiers);

        $this->dispatch('close-modal', 'form-merchant');
        $this->dispatch('toast', message: 'Merchant tersimpan.', tone: 'success');
    }

    public function render(): View
    {
        $today = now(config('sanad.display_timezone'))->toDateString();
        $merchants = Merchant::query()->accessibleBy(auth()->user())->orderBy('name')->get();
        $ids = $merchants->pluck('id')->all();
        $summary = SalesStats::summary($ids, $today, $today);
        $perMerchant = collect($ids)->mapWithKeys(fn (int $id) => [$id => SalesStats::summary([$id], $today, $today)]);

        return view('pos.merchants', [
            'merchants' => $merchants,
            'perMerchant' => $perMerchant,
            'lowStock' => MerchantProduct::query()->whereIn('merchant_id', $ids)->where('is_active', true)->whereColumn('stock', '<=', 'min_stock')->selectRaw('merchant_id, COUNT(*) AS total')->groupBy('merchant_id')->pluck('total', 'merchant_id'),
            'summary' => $summary,
            'canManageAll' => auth()->user()->can('merchants.manage'),
            'staffUsers' => User::query()->where('type', 'staff')->where('is_active', true)->whereHas('roles', fn ($roles) => $roles->whereIn('name', ['kasir', 'pengelola_merchant']))->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
