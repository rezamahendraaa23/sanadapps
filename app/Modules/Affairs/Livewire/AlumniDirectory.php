<?php

namespace App\Modules\Affairs\Livewire;

use App\Modules\Affairs\Actions\SyncAlumni;
use App\Modules\Affairs\Models\AlumniProfile;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Alumni (AL-01): otomatis dari kelulusan.
 */
#[Layout('components.layouts.staff', ['active' => 'alumni'])]
#[Title('Alumni')]
class AlumniDirectory extends Component
{
    use WithPagination;

    #[Url(as: 'cari', except: '')]
    public string $search = '';

    #[Locked]
    public ?int $editing = null;

    public string $current_activity = '';

    public string $contact_phone = '';

    public string $contact_email = '';

    public string $notes = '';

    public function mount(SyncAlumni $sync): void
    {
        $sync->handle();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function edit(string $uuid): void
    {
        $profile = AlumniProfile::query()->where('uuid', $uuid)->firstOrFail();
        $this->editing = $profile->id;
        $this->fill(['current_activity' => (string) $profile->current_activity, 'contact_phone' => (string) $profile->contact_phone, 'contact_email' => (string) $profile->contact_email, 'notes' => (string) $profile->notes]);
        $this->resetErrorBag();
        $this->dispatch('open-modal', 'ubah-alumni');
    }

    public function save(): void
    {
        $data = $this->validate(['current_activity' => 'nullable|string|max:200', 'contact_phone' => 'nullable|string|max:30', 'contact_email' => 'nullable|email|max:150', 'notes' => 'nullable|string|max:1000'], [], ['current_activity' => 'kegiatan', 'contact_phone' => 'telepon', 'contact_email' => 'email']);
        AlumniProfile::query()->findOrFail($this->editing)->update(array_map(fn ($value) => $value === '' ? null : $value, $data));
        $this->dispatch('close-modal', 'ubah-alumni');
    }

    public function render(): View
    {
        $term = trim($this->search);

        return view('affairs.alumni', [
            'profiles' => AlumniProfile::query()->with('student:id,name,nis')
                ->whereHas('student', fn ($query) => $query->where('status', 'graduated')->when($term !== '', fn ($inner) => $inner->where(fn ($match) => $match->where('name', 'ilike', '%'.addcslashes($term, '%_').'%')->orWhere('nis', 'like', addcslashes($term, '%_').'%'))))
                ->latest('graduated_on')->paginate(25),
        ]);
    }
}
