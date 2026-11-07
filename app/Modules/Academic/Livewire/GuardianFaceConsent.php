<?php

namespace App\Modules\Academic\Livewire;

use App\Modules\Academic\Actions\ManageBiometricConsent;
use App\Modules\Academic\Models\BiometricConsent;
use App\Modules\Academic\Models\FaceEnrollment;
use App\Modules\Core\Models\Student;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Persetujuan data wajah (W-14). Hanya wali yang bisa memutuskan.
 */
#[Layout('components.layouts.portal', ['role' => 'wali', 'active' => 'more', 'title' => 'Persetujuan Data Wajah'])]
#[Title('Persetujuan Data Wajah')]
class GuardianFaceConsent extends Component
{
    private function child(string $uuid): Student
    {
        return auth()->user()->guardian->students()->where('students.uuid', $uuid)->firstOrFail();
    }

    public function agree(string $uuid, ManageBiometricConsent $consents): void
    {
        $consents->grant($this->child($uuid), auth()->user()->guardian, 'digital', null, request()->ip(), auth()->id());
        $this->dispatch('toast', message: 'Persetujuan tersimpan.', tone: 'success');
    }

    public function revoke(string $uuid, ManageBiometricConsent $consents): void
    {
        $consents->revoke($this->child($uuid));
        $this->dispatch('toast', message: 'Persetujuan dicabut. Data wajah sudah dihapus.', tone: 'success');
    }

    public function render(): View
    {
        $children = auth()->user()->guardian?->students()->orderBy('name')->get() ?? collect();

        return view('academic.guardian-face-consent', [
            'children' => $children,
            'history' => BiometricConsent::query()->whereIn('student_id', $children->pluck('id'))->latest('granted_at')->get()->groupBy('student_id'),
            'enrolled' => FaceEnrollment::query()->whereIn('student_id', $children->pluck('id'))->where('status', 'active')->pluck('student_id')->all(),
        ]);
    }
}
