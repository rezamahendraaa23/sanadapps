<?php

namespace App\Modules\Core\Actions;

use App\Modules\Core\Models\Module;
use App\Modules\Core\Support\ModuleCatalog;
use App\Modules\Core\Support\ModuleRegistry;
use InvalidArgumentException;

class ToggleModule
{
    public function __construct(private ModuleRegistry $registry) {}

    /**
     * Dependensi tidak pernah diaktifkan diam-diam (PRD Sistem 2.1); data modul mati tetap tersimpan.
     */
    public function handle(string $key, bool $enabled): Module
    {
        if (! isset(ModuleCatalog::MODULES[$key])) {
            throw new InvalidArgumentException("Modul tidak dikenal: {$key}");
        }

        if ($key === 'core' && ! $enabled) {
            throw new ModuleDependencyException('Data inti selalu aktif.');
        }

        $blocking = $enabled
            ? array_values(array_filter(ModuleCatalog::MODULES[$key]['depends'], fn ($dependency) => ! $this->registry->enabled($dependency)))
            : array_values(array_filter(ModuleCatalog::dependents($key), fn ($dependent) => $this->registry->enabled($dependent)));

        if ($blocking !== []) {
            $names = implode(', ', array_map(fn ($module) => ModuleCatalog::MODULES[$module]['name'], $blocking));

            throw new ModuleDependencyException(
                $enabled ? "Aktifkan dulu: {$names}." : "Matikan dulu modul yang memakainya: {$names}.",
                $blocking,
            );
        }

        $module = Module::query()->firstOrNew(['module_key' => $key]);
        $module->is_enabled = $enabled;
        $module->settings ??= [];
        $module->save();

        $this->registry->forget();

        return $module;
    }
}
