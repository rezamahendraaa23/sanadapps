<?php

namespace App\Modules\Core\Support;

use App\Modules\Core\Models\Module;
use Illuminate\Support\Collection;

/**
 * Status modul per request. Core selalu aktif, modul yang belum tercatat memakai default PRD.
 */
class ModuleRegistry
{
    /** @var Collection<string, bool>|null */
    private ?Collection $states = null;

    public function enabled(string $key): bool
    {
        if ($key === 'core') {
            return true;
        }

        $this->states ??= Module::query()->pluck('is_enabled', 'module_key');

        return (bool) ($this->states[$key] ?? ModuleCatalog::MODULES[$key]['default'] ?? false);
    }

    public function forget(): void
    {
        $this->states = null;
    }
}
