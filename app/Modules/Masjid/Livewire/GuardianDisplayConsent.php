<?php

namespace App\Modules\Masjid\Livewire;

use App\Modules\Masjid\Models\DisplayConsent;
use Illuminate\Contracts\View\View;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Persetujuan nama dan foto anak di layar publik (PRD Masjid 8.3).
 */
#[Layout('components.layouts.portal', ['role' => 'wali', 'active' => 'more', 'title' => 'Nama di Layar Masjid'])]
#[Title('Nama di Layar Masjid')]
class GuardianDisplayConsent extends Component
{
    public function toggle(string $uuid, string $scope): void
    {
        abort_unless(in_array($scope, ['name', 'photo'], true), 404);
        $guardian = auth()->user()->guardian;
        $child = $guardian->students()->where('students.uuid', $uuid)->firstOrFail();
        $active = DisplayConsent::query()->active()->where('student_id', $child->id)->where('scope', $scope)->first();

        if ($active !== null) {
            $active->update(['revoked_at' => now()]);

            return;
        }

        try {
            DB::transaction(fn () => DisplayConsent::create(['student_id' => $child->id, 'guardian_id' => $guardian->id, 'scope' => $scope, 'granted_at' => now(), 'granted_via' => 'portal']));
        } catch (UniqueConstraintViolationException) {
            // Sudah disetujui wali lain.
        }
    }

    public function render(): View
    {
        $children = auth()->user()->guardian?->students()->orderBy('name')->get() ?? collect();

        return view('masjid.guardian-consent', [
            'children' => $children,
            'active' => DisplayConsent::query()->active()->whereIn('student_id', $children->pluck('id'))->get()->groupBy('student_id'),
        ]);
    }
}
