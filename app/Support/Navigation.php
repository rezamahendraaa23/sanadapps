<?php

namespace App\Support;

use Illuminate\Support\Facades\Route;

/**
 * Menyusun menu dari config/sanad.php. Item tanpa rute tidak muncul, kecuali di pratinjau
 * yang menampilkannya sebagai "belum dibuat" agar tidak ada tautan mati (R-24).
 */
final class Navigation
{
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
     * @param  list<array<string, string>>  $items
     * @return list<array<string, mixed>>
     */
    private function resolve(array $items, ?string $homeUrl, bool $preview): array
    {
        $resolved = [];

        foreach ($items as $item) {
            $url = match (true) {
                Route::has($item['route']) => route($item['route']),
                $item['key'] === 'home' && $homeUrl !== null => $homeUrl,
                default => null,
            };

            if ($url === null && ! $preview) {
                continue;
            }

            $resolved[] = [...$item, 'url' => $url];
        }

        return $resolved;
    }
}
