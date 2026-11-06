<?php

namespace App\Support;

use App\Modules\Core\Support\ModuleRegistry;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/**
 * Menyusun menu dari config/sanad.php. Item tampil bila rutenya ada, modulnya aktif, dan pengguna berhak.
 * Pratinjau menampilkan semua item, yang belum ada ditandai "belum dibuat" agar tidak ada tautan mati (R-24).
 */
final class Navigation
{
    public function __construct(private ModuleRegistry $modules) {}

    /**
     * @return list<array{label: string, items: list<array<string, mixed>>}>
     */
    public function staff(?string $homeUrl = null, bool $preview = false): array
    {
        $groups = [];

        foreach (config('sanad.navigation.staff') as $group) {
            $items = $this->resolve($group['items'], $homeUrl, $preview);

            if ($items !== []) {
                $groups[] = ['label' => $group['label'], 'items' => $items];
            }
        }

        return $groups;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function portal(string $role, ?string $homeUrl = null, bool $preview = false): array
    {
        return $this->resolve(config("sanad.navigation.portal.{$role}", []), $homeUrl, $preview);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function resolve(array $items, ?string $homeUrl, bool $preview): array
    {
        $resolved = [];

        foreach ($items as $item) {
            if ($preview) {
                $url = $item['key'] === 'home' ? $homeUrl : null;
                $resolved[] = [...$item, 'url' => $url];

                continue;
            }

            if (Route::has($item['route']) && $this->modules->enabled($item['module']) && $this->allowed($item)) {
                $resolved[] = [...$item, 'url' => route($item['route'])];
            }
        }

        return $resolved;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function allowed(array $item): bool
    {
        return ! isset($item['permission']) || Auth::user()?->canAny((array) $item['permission']);
    }
}
