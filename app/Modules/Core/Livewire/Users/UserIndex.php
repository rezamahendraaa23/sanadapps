<?php

namespace App\Modules\Core\Livewire\Users;

use App\Modules\Core\Models\Teacher;
use App\Modules\Core\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.staff', ['active' => 'users'])]
#[Title('Pengguna & Role')]
class UserIndex extends Component
{
    use WithPagination;

    public const STAFF_ROLES = [
        'admin' => 'Admin',
        'bendahara' => 'Bendahara',
        'admin_akademik' => 'Admin akademik',
        'ustadz' => 'Ustadz',
        'musyrif' => 'Musyrif',
        'petugas_uks' => 'Petugas UKS',
        'kasir' => 'Kasir',
        'pengelola_merchant' => 'Pengelola merchant',
        'penjaga_gerbang' => 'Penjaga gerbang',
        'panitia_ppdb' => 'Panitia PPDB',
    ];

    #[Url(as: 'cari', except: '')]
    public string $search = '';

    #[Locked]
    public ?int $editingId = null;

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $nip = '';

    public string $specialization = '';

    public bool $is_active = true;

    /** @var list<string> */
    public array $roles = [];

    public ?string $revealedPassword = null;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->reset('editingId', 'name', 'email', 'phone', 'nip', 'specialization', 'roles', 'revealedPassword');
        $this->is_active = true;
        $this->resetErrorBag();
        $this->dispatch('open-modal', 'form-pengguna');
    }

    public function edit(string $uuid): void
    {
        $user = User::query()->where('type', 'staff')->where('uuid', $uuid)->firstOrFail();
        $this->editingId = $user->id;
        $this->fill([
            'name' => $user->name,
            'email' => (string) $user->email,
            'phone' => (string) $user->phone,
            'nip' => (string) $user->teacher?->nip,
            'specialization' => (string) $user->teacher?->specialization,
            'is_active' => $user->is_active,
            'roles' => $user->getRoleNames()->all(),
            'revealedPassword' => null,
        ]);
        $this->resetErrorBag();
        $this->dispatch('open-modal', 'form-pengguna');
    }

    public function save(): void
    {
        $this->resetErrorBag();

        $this->validate([
            'name' => 'required|string|max:150',
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($this->editingId)],
            'phone' => 'nullable|string|max:30',
            'nip' => 'nullable|string|max:30',
            'specialization' => 'nullable|string|max:100',
            'roles' => 'required|array|min:1',
            'roles.*' => Rule::in(array_keys(self::STAFF_ROLES)),
        ], ['roles.required' => 'Pilih minimal satu role.', 'roles.min' => 'Pilih minimal satu role.']);

        $user = $this->editingId ? User::query()->where('type', 'staff')->findOrFail($this->editingId) : null;

        if ($user?->is(auth()->user()) && (! in_array('admin', $this->roles, true) || ! $this->is_active)) {
            $this->addError('roles', 'Anda tidak bisa mencabut role admin atau menonaktifkan akun sendiri. Minta admin lain melakukannya.');

            return;
        }

        $password = DB::transaction(function () use ($user): ?string {
            $password = $user === null ? Str::password(12, symbols: false) : null;
            $attributes = ['name' => $this->name, 'email' => $this->email, 'phone' => $this->phone ?: null, 'is_active' => $this->is_active];

            $user = $user
                ? tap($user)->update($attributes)
                : User::create([...$attributes, 'type' => 'staff', 'password' => $password]);

            $user->syncRoles($this->roles);
            $this->syncTeacher($user);

            return $password;
        });

        $this->dispatch('close-modal', 'form-pengguna');
        $this->revealedPassword = $password;
        $this->dispatch('toast', message: 'Pengguna tersimpan.', tone: 'success');
    }

    public function resetPassword(string $uuid): void
    {
        $user = User::query()->where('uuid', $uuid)->whereIn('type', ['staff', 'student'])->firstOrFail();
        $password = Str::password(12, symbols: false);
        $user->update(['password' => $password]);
        $this->revealedPassword = $password;
    }

    private function syncTeacher(User $user): void
    {
        if (! in_array('ustadz', $this->roles, true)) {
            return;
        }

        Teacher::withTrashed()->updateOrCreate(
            ['user_id' => $user->id],
            ['name' => $user->name, 'phone' => $user->phone, 'nip' => $this->nip ?: null, 'specialization' => $this->specialization ?: null, 'deleted_at' => null],
        );
    }

    public function render(): View
    {
        $search = trim($this->search);

        return view('core.users.index', [
            'users' => User::query()
                ->where('type', 'staff')
                ->with('roles:id,name')
                ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                    ->where('name', 'ilike', '%'.addcslashes($search, '%_').'%')
                    ->orWhere('email', 'ilike', '%'.addcslashes($search, '%_').'%')))
                ->orderBy('name')
                ->paginate(25),
            'roleOptions' => self::STAFF_ROLES,
        ]);
    }
}
