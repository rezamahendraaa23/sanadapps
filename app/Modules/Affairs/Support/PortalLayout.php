<?php

namespace App\Modules\Affairs\Support;

/**
 * Musyrif dan ustadz bekerja di portal; peran lain di panel.
 */
trait PortalLayout
{
    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function affairsLayout(string $active, string $title): array
    {
        $user = auth()->user();

        foreach (['musyrif', 'ustadz'] as $role) {
            if ($user->hasRole($role) && ! $user->hasAnyRole(['admin', 'admin_akademik'])) {
                return ['components.layouts.portal', ['role' => $role, 'active' => $active, 'title' => $title]];
            }
        }

        return ['components.layouts.staff', ['active' => $active]];
    }
}
