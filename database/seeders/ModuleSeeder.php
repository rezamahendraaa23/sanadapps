<?php

namespace Database\Seeders;

use App\Modules\Core\Models\Module;
use App\Modules\Core\Support\ModuleCatalog;
use Illuminate\Database\Seeder;

class ModuleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (ModuleCatalog::MODULES as $key => $module) {
            Module::query()->firstOrCreate(['module_key' => $key], ['is_enabled' => $module['default'], 'settings' => []]);
        }
    }
}
