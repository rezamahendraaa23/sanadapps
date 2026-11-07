<?php

namespace App\Modules\Affairs\Livewire;

use App\Modules\Affairs\Actions\MoveInventory;
use App\Modules\Affairs\Models\InventoryItem;
use App\Modules\Affairs\Support\AffairsException;
use App\Modules\Core\Models\Dormitory;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Inventaris asrama (IV-01).
 */
#[Layout('components.layouts.staff', ['active' => 'inventory'])]
#[Title('Inventaris')]
class InventoryBoard extends Component
{
    #[Url(as: 'asrama', except: '')]
    public string $dormitory = '';

    public string $name = '';

    public string $category = '';

    public string $quantity = '';

    public string $location = '';

    public string $new_dormitory = '';

    #[Locked]
    public ?string $moving = null;

    public string $move_type = 'out';

    public string $move_quantity = '';

    public string $move_note = '';

    private function dormitories()
    {
        $user = auth()->user();

        return Dormitory::query()->when(! $user->hasRole(['admin', 'admin_akademik']), fn ($query) => $query->where('musyrif_user_id', $user->id))->orderBy('name')->pluck('name', 'id')->all();
    }

    public function save(MoveInventory $moves): void
    {
        $this->resetErrorBag();
        $this->validate(['name' => 'required|string|max:150', 'category' => 'nullable|string|max:60', 'quantity' => 'required|integer|min:1|max:1000000', 'location' => 'nullable|string|max:100', 'new_dormitory' => 'required'], ['new_dormitory.required' => 'Pilih asrama.'], ['name' => 'nama barang', 'quantity' => 'jumlah', 'location' => 'lokasi']);
        abort_unless(array_key_exists((int) $this->new_dormitory, $this->dormitories()), 403);

        DB::transaction(function () use ($moves): void {
            $item = InventoryItem::create(['dormitory_id' => (int) $this->new_dormitory, 'name' => $this->name, 'category' => $this->category ?: null, 'location' => $this->location ?: null]);
            $moves->handle($item, 'in', (int) $this->quantity, 'Stok awal', auth()->id());
        });

        $this->reset('name', 'category', 'quantity', 'location');
        $this->dispatch('close-modal', 'tambah-barang');
    }

    public function askMove(string $uuid): void
    {
        $this->moving = InventoryItem::query()->managedBy(auth()->user())->where('uuid', $uuid)->firstOrFail()->uuid;
        $this->reset('move_quantity', 'move_note');
        $this->move_type = 'out';
        $this->resetErrorBag();
        $this->dispatch('open-modal', 'mutasi-barang');
    }

    public function move(MoveInventory $moves): void
    {
        $this->resetErrorBag();
        $item = InventoryItem::query()->managedBy(auth()->user())->where('uuid', (string) $this->moving)->firstOrFail();

        if (filter_var($this->move_quantity, FILTER_VALIDATE_INT) === false) {
            $this->addError('move_quantity', 'Jumlah harus angka.');

            return;
        }

        try {
            $moves->handle($item, $this->move_type, (int) $this->move_quantity, $this->move_note ?: null, auth()->id());
        } catch (AffairsException $exception) {
            $this->addError('move_quantity', $exception->getMessage());

            return;
        }

        $this->dispatch('close-modal', 'mutasi-barang');
    }

    public function render(): View
    {
        return view('affairs.inventory', [
            'items' => InventoryItem::query()->managedBy(auth()->user())->with(['dormitory:id,name', 'movements' => fn ($query) => $query->latest('id')->limit(3)])->when($this->dormitory !== '', fn ($query) => $query->where('dormitory_id', (int) $this->dormitory))->orderBy('name')->get(),
            'dormitories' => $this->dormitories(),
            'types' => InventoryItem::MOVEMENTS,
        ]);
    }
}
