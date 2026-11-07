<?php

namespace App\Modules\Wallet\Livewire;

use App\Modules\Wallet\Models\Device;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.staff', ['active' => 'cards'])]
#[Title('Perangkat')]
class DeviceAdmin extends Component
{
    public string $name = '';

    public string $location = '';

    public string $type = 'reader_usb';

    /** @var list<string> */
    public array $scopes = [];

    /** Kunci hanya ditampilkan sekali, setelah itu yang tersimpan hanya hash-nya. */
    #[Locked]
    public ?string $newKey = null;

    public function save(): void
    {
        $this->resetErrorBag();
        $this->validate([
            'name' => 'required|string|max:100',
            'location' => 'nullable|string|max:100',
            'type' => 'required|in:reader_usb,camera_qr',
            'scopes' => 'required|array|min:1',
            'scopes.*' => 'in:'.implode(',', array_keys(Device::SCOPES)),
        ], ['scopes.required' => 'Pilih minimal satu akses.'], ['name' => 'nama', 'location' => 'lokasi']);

        $this->newKey = 'dev_'.Str::random(48);

        Device::create(['name' => $this->name, 'location' => $this->location ?: null, 'type' => $this->type, 'scopes' => array_values($this->scopes), 'api_key_hash' => hash('sha256', $this->newKey)]);

        $this->reset('name', 'location', 'type', 'scopes');
        $this->dispatch('close-modal', 'tambah-perangkat');
    }

    public function rotate(string $uuid): void
    {
        $this->newKey = 'dev_'.Str::random(48);

        Device::query()->where('uuid', $uuid)->firstOrFail()->update(['api_key_hash' => hash('sha256', $this->newKey)]);
    }

    public function toggle(string $uuid): void
    {
        $device = Device::query()->where('uuid', $uuid)->firstOrFail();
        $device->update(['is_active' => ! $device->is_active]);
    }

    public function dismissKey(): void
    {
        $this->newKey = null;
    }

    public function render(): View
    {
        return view('wallet.devices', ['devices' => Device::query()->orderBy('name')->get(), 'scopeLabels' => Device::SCOPES]);
    }
}
