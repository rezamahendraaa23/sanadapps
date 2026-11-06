<?php

namespace App\Modules\Core\Livewire\Settings;

use App\Modules\Core\Actions\ModuleDependencyException;
use App\Modules\Core\Actions\ToggleModule;
use App\Modules\Core\Support\ModuleCatalog;
use App\Modules\Core\Support\ModuleRegistry;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.staff', ['active' => 'modules'])]
#[Title('Modul')]
class ModuleSettings extends Component
{
    #[Url(as: 'cari', except: '')]
    public string $search = '';

    #[Locked]
    public ?string $confirmingDisable = null;

    public ?string $blockedKey = null;

    public ?string $blockedMessage = null;

    public function enable(string $key, ToggleModule $toggle): void
    {
        $this->apply($key, true, $toggle);
    }

    public function askDisable(string $key): void
    {
        abort_unless(isset(ModuleCatalog::MODULES[$key]) && $key !== 'core', 404);

        $this->clearBlock();
        $this->confirmingDisable = $key;
        $this->dispatch('open-modal', 'matikan-modul');
    }

    public function confirmDisable(ToggleModule $toggle): void
    {
        $key = $this->confirmingDisable;
        $this->confirmingDisable = null;
        $this->dispatch('close-modal', 'matikan-modul');

        if ($key !== null) {
            $this->apply($key, false, $toggle);
        }
    }

    private function apply(string $key, bool $enabled, ToggleModule $toggle): void
    {
        abort_unless(isset(ModuleCatalog::MODULES[$key]), 404);
        $this->clearBlock();

        try {
            $toggle->handle($key, $enabled);
        } catch (ModuleDependencyException $exception) {
            $this->blockedKey = $key;
            $this->blockedMessage = $exception->getMessage();

            return;
        }

        $name = ModuleCatalog::MODULES[$key]['name'];
        $this->dispatch('toast', message: $enabled ? "{$name} aktif." : "{$name} dimatikan. Datanya tetap tersimpan.", tone: 'success');
    }

    private function clearBlock(): void
    {
        $this->blockedKey = null;
        $this->blockedMessage = null;
    }

    public function render(ModuleRegistry $registry): View
    {
        $query = mb_strtolower(trim($this->search));

        $modules = collect(ModuleCatalog::MODULES)
            ->map(fn (array $module, string $key) => [
                ...$module,
                'key' => $key,
                'enabled' => $registry->enabled($key),
                'dependency_names' => array_map(fn ($dependency) => ModuleCatalog::MODULES[$dependency]['name'], array_diff($module['depends'], ['core'])),
            ])
            ->filter(fn (array $module) => $query === '' || str_contains(mb_strtolower($module['name'].' '.$module['description']), $query));

        return view('core.settings.modules', [
            'modules' => $modules,
            'confirmingName' => $this->confirmingDisable ? ModuleCatalog::MODULES[$this->confirmingDisable]['name'] : null,
        ]);
    }
}
