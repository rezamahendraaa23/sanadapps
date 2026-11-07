<?php

namespace App\Modules\Finance\Livewire\Settings;

use App\Modules\Core\Models\Module;
use App\Modules\Finance\Models\BankAccount;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class BankAccounts extends Component
{
    #[Locked]
    public ?int $editingId = null;

    public string $bank_name = '';

    public string $account_no = '';

    public string $account_holder = '';

    public bool $is_active = true;

    public bool $uniqueCode = false;

    public function mount(): void
    {
        $this->uniqueCode = (bool) (Module::query()->where('module_key', 'payment_manual')->value('settings')['unique_code'] ?? false);
    }

    public function updatedUniqueCode(): void
    {
        $module = Module::query()->firstOrCreate(['module_key' => 'payment_manual'], ['is_enabled' => true, 'settings' => []]);
        $module->update(['settings' => [...($module->settings ?? []), 'unique_code' => $this->uniqueCode]]);
        $this->dispatch('toast', message: $this->uniqueCode ? 'Kode unik aktif.' : 'Kode unik dimatikan.', tone: 'success');
    }

    public function create(): void
    {
        $this->reset('editingId', 'bank_name', 'account_no', 'account_holder', 'is_active');
        $this->resetErrorBag();
        $this->dispatch('open-modal', 'form-rekening');
    }

    public function edit(string $uuid): void
    {
        $account = BankAccount::query()->where('uuid', $uuid)->firstOrFail();
        $this->editingId = $account->id;
        $this->fill($account->only(['bank_name', 'account_no', 'account_holder', 'is_active']));
        $this->resetErrorBag();
        $this->dispatch('open-modal', 'form-rekening');
    }

    public function save(): void
    {
        $data = $this->validate([
            'bank_name' => 'required|string|max:50',
            'account_no' => ['required', 'string', 'max:30', 'regex:/^[0-9 .-]+$/'],
            'account_holder' => 'required|string|max:100',
            'is_active' => 'boolean',
        ], ['account_no.regex' => 'Nomor rekening hanya berisi angka.'], ['bank_name' => 'nama bank', 'account_no' => 'nomor rekening', 'account_holder' => 'atas nama']);

        $this->editingId
            ? BankAccount::query()->findOrFail($this->editingId)->update($data)
            : BankAccount::create([...$data, 'sort_order' => (int) BankAccount::query()->max('sort_order') + 1]);

        $this->dispatch('close-modal', 'form-rekening');
        $this->dispatch('toast', message: 'Rekening tersimpan.', tone: 'success');
    }

    public function render(): View
    {
        return view('finance.settings.bank-accounts', ['accounts' => BankAccount::query()->orderBy('sort_order')->get()]);
    }
}
