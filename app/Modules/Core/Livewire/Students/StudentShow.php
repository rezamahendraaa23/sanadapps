<?php

namespace App\Modules\Core\Livewire\Students;

use App\Modules\Core\Models\CustomFieldDefinition;
use App\Modules\Core\Models\Student;
use App\Modules\Core\Support\ModuleRegistry;
use App\Modules\Core\Support\StudentHistory;
use App\Modules\Wallet\Models\Wallet;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.staff', ['active' => 'students'])]
class StudentShow extends Component
{
    public Student $student;

    public ?string $issuedPassword = null;

    #[Url(except: 'profil')]
    public string $tab = 'profil';

    public function mount(Student $student): void
    {
        $this->authorize('view', $student);
        $this->student = $student;

        if (session()->has('toast')) {
            $this->dispatch('toast', message: session('toast'), tone: 'success');
        }
    }

    public function detachGuardian(string $guardianUuid): void
    {
        $this->authorize('update', $this->student);
        $guardian = $this->student->guardians()->where('guardians.uuid', $guardianUuid)->firstOrFail();
        $this->student->guardians()->detach($guardian->id);
        $this->dispatch('toast', message: "{$guardian->name} tidak lagi terhubung.", tone: 'success');
    }

    public function issuePassword(): void
    {
        $this->authorize('update', $this->student);

        $user = $this->student->user;
        abort_if($user === null, 404);

        $this->issuedPassword = Str::password(10, symbols: false);
        $user->update(['password' => $this->issuedPassword]);
    }

    public function render(): View
    {
        $this->student->load(['classroom', 'dormitory', 'guardians', 'enrollments.classroom']);

        return view('core.students.show', [
            'fields' => CustomFieldDefinition::query()->where('entity', 'student')->where('is_active', true)->orderBy('sort_order')->get(),
            'canManage' => auth()->user()->can('update', $this->student),
            'showFinance' => auth()->user()->can('finance.view') && app(ModuleRegistry::class)->enabled('finance'),
            'showWallet' => $showWallet = auth()->user()->can('wallet.view') && app(ModuleRegistry::class)->enabled('wallet'),
            'wallet' => $showWallet && $this->tab === 'dompet' ? Wallet::forStudent($this->student) : null,
            'studentCard' => $showWallet && $this->tab === 'dompet' ? $this->student->cards()->latest('id')->first() : null,
            'initialPassword' => $this->issuedPassword ?? session('initial_password'),
            'history' => $this->tab === 'lengkap' ? app(StudentHistory::class)->build($this->student, auth()->user()) : [],
        ])->title($this->student->name);
    }
}
