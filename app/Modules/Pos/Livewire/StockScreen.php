<?php

namespace App\Modules\Pos\Livewire;

use App\Modules\Finance\Actions\FinanceException;
use App\Modules\Pos\Actions\StockLedger;
use App\Modules\Pos\Models\Merchant;
use App\Modules\Pos\Models\MerchantProduct;
use App\Modules\Pos\Models\StockMovement;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.staff', ['active' => 'merchants'])]
class StockScreen extends Component
{
    use WithPagination;

    public Merchant $merchant;

    #[Url(as: 'menipis', except: false)]
    public bool $lowOnly = false;

    #[Locked]
    public ?string $target = null;

    #[Locked]
    public string $action = 'in';

    public string $quantity = '';

    public string $note = '';

    /** @var array{name: string, system: int, counted: int, difference: int}|null */
    #[Locked]
    public ?array $opnameResult = null;

    public function mount(Merchant $merchant): void
    {
        abort_unless(Merchant::query()->accessibleBy(auth()->user())->whereKey($merchant->id)->exists(), 403);
        $this->merchant = $merchant;
    }

    private function product(): MerchantProduct
    {
        abort_unless($this->merchant->canBeManagedBy(auth()->user()), 403);

        return $this->merchant->products()->where('uuid', $this->target)->whereNotNull('stock')->firstOrFail();
    }

    public function open(string $uuid, string $action): void
    {
        abort_unless(in_array($action, ['in', 'out', 'opname'], true), 404);
        $this->target = $uuid;
        $this->action = $action;
        $this->reset('quantity', 'note', 'opnameResult');
        $this->resetErrorBag();
        $this->product();
        $this->dispatch('open-modal', 'gerak-stok');
    }

    public function submit(StockLedger $stock): void
    {
        $this->resetErrorBag();
        $product = $this->product();

        if (! ctype_digit($this->quantity)) {
            $this->addError('quantity', 'Isi jumlah berupa angka.');

            return;
        }

        $quantity = (int) $this->quantity;

        try {
            match ($this->action) {
                'in' => $stock->stockIn($product, $quantity, $this->note ?: null, auth()->id()),
                'out' => $stock->stockOut($product, $quantity, $this->note, auth()->id()),
                'opname' => $this->opnameResult = ['name' => $product->name, 'system' => (int) $product->stock, 'counted' => $quantity, 'difference' => $stock->opname($product, $quantity, $this->note ?: null, auth()->id())],
            };
        } catch (FinanceException $exception) {
            $this->addError($this->action === 'out' && trim($this->note) === '' ? 'note' : 'quantity', $exception->getMessage());

            return;
        }

        $this->dispatch('close-modal', 'gerak-stok');
        $this->dispatch('toast', message: 'Stok tersimpan.', tone: 'success');
    }

    public function render(): View
    {
        $products = $this->merchant->products()->whereNotNull('stock')
            ->when($this->lowOnly, fn ($query) => $query->whereColumn('stock', '<=', 'min_stock'))
            ->orderBy('name')->paginate(25, pageName: 'produk');

        return view('pos.stock', [
            'products' => $products,
            'movements' => StockMovement::query()
                ->with(['product:id,name', 'creator:id,name'])
                ->whereIn('product_id', $this->merchant->products()->select('id'))
                ->latest('id')->paginate(20, pageName: 'mutasi'),
            'labels' => StockMovement::LABELS,
            'canManage' => $this->merchant->canBeManagedBy(auth()->user()),
            'targetProduct' => $this->target ? $this->merchant->products()->where('uuid', $this->target)->first() : null,
        ])->title('Stok '.$this->merchant->name);
    }
}
