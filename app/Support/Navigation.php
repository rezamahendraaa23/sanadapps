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
                $groups[] = ['label' => $group['label'], 'workspace' => $group['workspace'] ?? null, 'items' => $items];
            }
        }

        return $groups;
    }

    /**
     * Sidebar bertingkat: di halaman ruang kerja (Keuangan, Mart, PPDB, Masjid) hanya menu ruang kerja itu yang tampil;
     * di luar itu tampil menu umum ditambah pintu masuk tiap ruang kerja.
     *
     * @param  list<array{label: string, workspace: array<string, string>|null, items: list<array<string, mixed>>}>  $groups
     * @return array{workspace: array<string, mixed>|null, groups: list<array<string, mixed>>}
     */
    public function sidebar(array $groups, ?string $active): array
    {
        foreach ($groups as $group) {
            if ($group['workspace'] && in_array($active, array_column($group['items'], 'key'), true)) {
                return ['workspace' => [...$group['workspace'], 'label' => $group['label']], 'groups' => [['label' => 'Menu '.$group['label'], 'items' => $group['items']]]];
            }
        }

        $general = [];
        $doors = [];
        foreach ($groups as $group) {
            if (! $group['workspace']) {
                $general[] = $group;

                continue;
            }
            // Pintu masuk menuju menu pertama yang boleh dibuka pengguna di ruang kerja itu.
            $first = $group['items'][0];
            $doors[] = ['key' => 'ruang-'.$group['workspace']['key'], 'label' => $group['label'], 'icon' => $group['workspace']['icon'], 'hint' => $group['workspace']['hint'], 'url' => $first['url'], 'door' => true];
        }

        if ($doors !== []) {
            array_splice($general, min(1, count($general)), 0, [['label' => 'Ruang kerja', 'items' => $doors]]);
        }

        return ['workspace' => null, 'groups' => $general];
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
