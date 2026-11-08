<?php

namespace App\Modules\Masjid\Livewire;

use App\Modules\Core\Models\OrgChart;
use App\Modules\Masjid\Actions\ManageScreens;
use App\Modules\Masjid\Models\DisplayCommand;
use App\Modules\Masjid\Models\DisplayScreen;
use App\Modules\Masjid\Support\MasjidException;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Layar dan pairing (MS-06, MS-07).
 */
#[Layout('components.layouts.staff', ['active' => 'masjid-screens'])]
#[Title('Layar Masjid')]
class DisplayScreens extends Component
{
    public string $code = '';

    public string $name = '';

    public string $location = '';

    public string $profile = 'masjid';

    public string $orientation = 'landscape';

    public string $content_pattern = 'rotate';

    public string $dim_from = '';

    public string $dim_until = '';

    /** @var list<string> */
    public array $org_charts = [];

    public string $org_mode = 'rotate';

    #[Locked]
    public ?string $editing = null;

    public function claim(ManageScreens $screens): void
    {
        $this->resetErrorBag();
        $this->validate([
            'code' => 'required|digits:6',
            'name' => 'required|string|max:100',
            'location' => 'nullable|string|max:100',
            'profile' => 'required|in:masjid,canteen,lobby,announcement_only',
            'orientation' => 'required|in:landscape,portrait',
            'content_pattern' => 'required|in:rotate,list',
            'dim_from' => 'nullable|date_format:H:i|required_with:dim_until',
            'dim_until' => 'nullable|date_format:H:i|required_with:dim_from',
            'org_charts' => 'array',
            'org_charts.*' => 'in:'.implode(',', array_keys(OrgChart::DEFAULTS)),
            'org_mode' => 'required|in:rotate,always',
        ], ['code.digits' => 'Kode terdiri dari 6 angka.'], ['code' => 'kode', 'name' => 'nama layar', 'dim_from' => 'redup mulai', 'dim_until' => 'redup selesai']);

        try {
            $screens->claim($this->code, [
                'name' => $this->name,
                'location' => $this->location ?: null,
                'profile' => $this->profile,
                'orientation' => $this->orientation,
                'content_pattern' => $this->content_pattern,
                'brightness_schedule' => $this->dim_from ? ['from' => $this->dim_from, 'until' => $this->dim_until, 'level' => 60] : null,
                'org_charts' => array_values($this->org_charts),
                'org_mode' => $this->org_mode,
            ]);
        } catch (MasjidException $exception) {
            $this->addError('code', $exception->getMessage());

            return;
        }

        $this->reset('code', 'name', 'location', 'dim_from', 'dim_until', 'org_charts', 'org_mode');
        $this->dispatch('close-modal', 'pasang-layar');
        $this->dispatch('toast', message: 'Layar terpasang. Tampilan muncul dalam beberapa detik.', tone: 'success');
    }

    public function startClaim(): void
    {
        $this->reset('editing', 'code', 'name', 'location', 'profile', 'orientation', 'content_pattern', 'dim_from', 'dim_until', 'org_charts', 'org_mode');
        $this->resetErrorBag();
        $this->dispatch('open-modal', 'pasang-layar');
    }

    public function editScreen(string $uuid): void
    {
        $screen = DisplayScreen::query()->where('uuid', $uuid)->where('is_active', true)->firstOrFail();
        $this->editing = $screen->uuid;
        $this->fill([
            'name' => $screen->name,
            'location' => (string) $screen->location,
            'profile' => $screen->profile,
            'orientation' => $screen->orientation,
            'content_pattern' => $screen->content_pattern,
            'dim_from' => (string) ($screen->brightness_schedule['from'] ?? ''),
            'dim_until' => (string) ($screen->brightness_schedule['until'] ?? ''),
            'org_charts' => array_values($screen->org_charts ?? []),
            'org_mode' => $screen->org_mode,
        ]);
        $this->resetErrorBag();
        $this->dispatch('open-modal', 'ubah-layar');
    }

    /**
     * Mengubah pengaturan layar terpasang; TV mengambil perubahan dalam beberapa detik tanpa dipasangkan ulang.
     */
    public function updateScreen(): void
    {
        $screen = DisplayScreen::query()->where('uuid', $this->editing)->where('is_active', true)->firstOrFail();
        $this->validate([
            'name' => 'required|string|max:100',
            'location' => 'nullable|string|max:100',
            'profile' => 'required|in:masjid,canteen,lobby,announcement_only',
            'orientation' => 'required|in:landscape,portrait',
            'content_pattern' => 'required|in:rotate,list',
            'dim_from' => 'nullable|date_format:H:i|required_with:dim_until',
            'dim_until' => 'nullable|date_format:H:i|required_with:dim_from',
            'org_charts' => 'array',
            'org_charts.*' => 'in:'.implode(',', array_keys(OrgChart::DEFAULTS)),
            'org_mode' => 'required|in:rotate,always',
        ], [], ['name' => 'nama layar', 'dim_from' => 'redup mulai', 'dim_until' => 'redup selesai']);

        $screen->update([
            'name' => $this->name,
            'location' => $this->location ?: null,
            'profile' => $this->profile,
            'orientation' => $this->orientation,
            'content_pattern' => $this->content_pattern,
            'brightness_schedule' => $this->dim_from ? ['from' => $this->dim_from, 'until' => $this->dim_until, 'level' => 60] : null,
            'org_charts' => array_values($this->org_charts),
            'org_mode' => $this->org_mode,
        ]);
        $screen->device?->update(['name' => $this->name, 'location' => $this->location ?: null]);

        $this->reset('editing', 'name', 'location', 'dim_from', 'dim_until', 'org_charts', 'org_mode');
        $this->dispatch('close-modal', 'ubah-layar');
        $this->dispatch('toast', message: 'Layar diperbarui. TV menyesuaikan dalam beberapa detik.', tone: 'success');
    }

    public function command(string $uuid, string $command, ManageScreens $screens): void
    {
        $screen = DisplayScreen::query()->where('uuid', $uuid)->firstOrFail();
        $screens->command($screen, $command, auth()->id());
        $this->dispatch('toast', ...($screen->isOnline()
            ? ['message' => 'Perintah terkirim. Layar menjalankannya dalam 8 detik.', 'tone' => 'success']
            : ['message' => 'Layar sedang offline. Perintah dijalankan saat layar tersambung lagi.', 'tone' => 'warning']));
    }

    public function revoke(string $uuid, ManageScreens $screens): void
    {
        abort_unless(auth()->user()->can('masjid.settings'), 403);
        $screens->revoke(DisplayScreen::query()->where('uuid', $uuid)->firstOrFail());
        $this->dispatch('toast', message: 'Token layar dicabut. Layar kembali ke kode pasang.', tone: 'success');
    }

    public function render(): View
    {
        return view('masjid.screens', [
            'screens' => $list = DisplayScreen::query()->where('is_active', true)->orderBy('name')->get(),
            // Perintah terakhir per layar, agar terlihat sudah diterima TV atau belum.
            'lastCommands' => DisplayCommand::query()->whereIn('screen_id', $list->pluck('id'))->orderBy('id')->get()->keyBy('screen_id'),
            'dimmed' => DisplayCommand::query()->whereIn('screen_id', $list->pluck('id'))->whereIn('command', ['dim', 'undim'])->orderBy('id')->get()->keyBy('screen_id')->map(fn (DisplayCommand $row) => $row->command === 'dim'),
            'profiles' => DisplayScreen::PROFILES,
            'charts' => OrgChart::query()->orderBy('id')->pluck('name', 'key')->all(),
        ]);
    }
}
