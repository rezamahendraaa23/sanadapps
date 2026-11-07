<?php

use App\Http\Middleware\EnsureModuleEnabled;
use App\Modules\Core\Actions\ModuleDependencyException;
use App\Modules\Core\Actions\ToggleModule;
use App\Modules\Core\Models\Module;
use App\Modules\Core\Support\ModuleCatalog;
use App\Modules\Core\Support\ModuleRegistry;
use Database\Seeders\ModuleSeeder;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    (new ModuleSeeder)->run();

    Route::middleware(EnsureModuleEnabled::class.':wallet')->get('/_uji/dompet', fn () => 'dompet');
});

it('menanam semua modul PRD dengan nilai default', function () {
    expect(Module::count())->toBe(29)
        ->and(Module::where('module_key', 'finance')->value('is_enabled'))->toBeTrue()
        ->and(Module::where('module_key', 'face_attendance')->value('is_enabled'))->toBeFalse();
});

it('memakai dependensi yang valid di katalog', function () {
    foreach (ModuleCatalog::MODULES as $module) {
        foreach ($module['depends'] as $dependency) {
            expect(ModuleCatalog::MODULES)->toHaveKey($dependency);
        }
    }
});

it('menolak mengaktifkan modul bila dependensinya mati tanpa mengaktifkannya diam-diam', function () {
    expect(fn () => app(ToggleModule::class)->handle('pos', true))->toThrow(ModuleDependencyException::class, 'Dompet uang saku');
    expect(Module::where('module_key', 'pos')->value('is_enabled'))->toBeFalse()
        ->and(Module::where('module_key', 'wallet')->value('is_enabled'))->toBeFalse();
});

it('menolak mematikan modul yang masih dipakai modul aktif', function () {
    app(ToggleModule::class)->handle('wallet', true);
    app(ToggleModule::class)->handle('pos', true);

    expect(fn () => app(ToggleModule::class)->handle('wallet', false))->toThrow(ModuleDependencyException::class, 'Mart & Warung');
});

it('tidak mengizinkan data inti dimatikan', function () {
    expect(fn () => app(ToggleModule::class)->handle('core', false))->toThrow(ModuleDependencyException::class);
    expect(app(ModuleRegistry::class)->enabled('core'))->toBeTrue();
});

it('menjawab MODULE_DISABLED untuk JSON dan halaman penjelasan untuk browser', function () {
    $this->getJson('/_uji/dompet')->assertForbidden()->assertJsonPath('error.code', 'MODULE_DISABLED');
    $this->get('/_uji/dompet')->assertForbidden()->assertSee('MODULE_DISABLED');
});

it('membuka rute setelah modul aktif dan efektif tanpa deploy', function () {
    app(ToggleModule::class)->handle('wallet', true);

    $this->get('/_uji/dompet')->assertOk();
});
