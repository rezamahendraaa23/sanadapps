<?php

namespace App\Modules\Core\Livewire\Guardians;

use App\Modules\Core\Actions\SaveGuardian;
use App\Modules\Core\Models\Guardian;
use App\Modules\Core\Support\PhoneNumber;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.staff', ['active' => 'guardians'])]
#[Title('Wali')]
class GuardianIndex extends Component
{
    use WithPagination;

    #[Url(as: 'cari', except: '')]
    public string $search = '';

    #[Locked]
    public ?int $editingId = null;

    public string $name = '';

    public string $phone_wa = '';

    public string $email = '';

    public string $occupation = '';

    public string $address = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Guardian::class);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->authorize('create', Guardian::class);
        $this->reset('editingId', 'name', 'phone_wa', 'email', 'occupation', 'address');
        $this->resetErrorBag();
        $this->dispatch('open-modal', 'form-wali');
    }

    public function edit(string $uuid): void
    {
        $guardian = Guardian::query()->where('uuid', $uuid)->firstOrFail();
        $this->authorize('update', $guardian);
        $this->editingId = $guardian->id;
        $this->fill([
            'name' => $guardian->name,
            'phone_wa' => $guardian->phone_wa,
            'email' => (string) $guardian->email,
            'occupation' => (string) $guardian->occupation,
            'address' => (string) $guardian->address,
        ]);
        $this->resetErrorBag();
        $this->dispatch('open-modal', 'form-wali');
    }

    public function save(SaveGuardian $action): void
    {
        $this->resetErrorBag();
        $guardian = $this->editingId ? Guardian::query()->findOrFail($this->editingId) : null;
        $guardian ? $this->authorize('update', $guardian) : $this->authorize('create', Guardian::class);

        $this->phone_wa = PhoneNumber::normalize($this->phone_wa) ?? $this->phone_wa;

        $data = $this->validate([
            'name' => 'required|string|max:150',
            'phone_wa' => ['required', 'regex:/^628\d{7,12}$/', Rule::unique('guardians', 'phone_wa')->ignore($this->editingId)->whereNull('deleted_at')],
            'email' => 'nullable|email|max:150',
            'occupation' => 'nullable|string|max:100',
            'address' => 'nullable|string|max:500',
        ], ['phone_wa.regex' => 'Nomor WhatsApp belum benar. Contoh: 081234567890.'], ['phone_wa' => 'nomor WhatsApp']);

        $action->handle(array_map(fn ($value) => $value === '' ? null : $value, $data), $guardian);

        $this->dispatch('close-modal', 'form-wali');
        $this->dispatch('toast', message: 'Data wali tersimpan.', tone: 'success');
    }

    public function render(): View
    {
        $search = trim($this->search);

        return view('core.guardians.index', [
            'guardians' => Guardian::query()
                ->withCount('students')
                ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                    ->where('name', 'ilike', '%'.addcslashes($search, '%_').'%')
                    ->orWhere('phone_wa', 'like', '%'.addcslashes(preg_replace('/\D/', '', $search) ?: $search, '%_').'%')))
                ->orderBy('name')
                ->paginate(25),
        ]);
    }
}
