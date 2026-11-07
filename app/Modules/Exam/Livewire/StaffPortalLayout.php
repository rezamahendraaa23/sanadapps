<?php

namespace App\Modules\Exam\Livewire;

/**
 * Ustadz bekerja di portal; pengelola akademik di panel.
 */
trait StaffPortalLayout
{
    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function examLayout(string $title): array
    {
        return auth()->user()->can('exams.manage')
            ? ['components.layouts.staff', ['active' => 'exams']]
            : ['components.layouts.portal', ['role' => 'ustadz', 'active' => 'exams', 'title' => $title]];
    }

    private function examRoute(string $name, mixed $parameters = []): string
    {
        return route((auth()->user()->can('exams.manage') ? 'staff.exams.' : 'ustadz.exams.').$name, $parameters);
    }
}
