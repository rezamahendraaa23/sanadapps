<?php

namespace App\Modules\Masjid\Livewire;

use App\Modules\Masjid\Actions\ManageScreens;
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
        ], ['code.digits' => 'Kode terdiri dari 6 angka.'], ['code' => 'kode', 'name' => 'nama layar', 'dim_from' => 'redup mulai', 'dim_until' => 'redup selesai']);

        try {
            $screens->claim($this->code, [
                'name' => $this->name,
                'location' => $this->location ?: null,
                'profile' => $this->profile,
                'orientation' => $this->orientation,
                'content_pattern' => $this->content_pattern,
                'brightness_schedule' => $this->dim_from ? ['from' => $this->dim_from, 'until' => $this->dim_until, 'level' => 60] : null,
            ]);
        } catch (MasjidException $exception) {
            $this->addError('code', $exception->getMessage());

            return;
        }

        $this->reset('code', 'name', 'location', 'dim_from', 'dim_until');
        $this->dispatch('toast', message: 'Layar terpasang. Tampilan muncul dalam beberapa detik.', tone: 'success');
    }

    public function command(string $uuid, string $command, ManageScreens $screens): void
    {
        $screens->command(DisplayScreen::query()->where('uuid', $uuid)->firstOrFail(), $command, auth()->id());
        $this->dispatch('toast', message: 'Perintah terkirim.', tone: 'success');
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
            'screens' => DisplayScreen::query()->where('is_active', true)->orderBy('name')->get(),
            'profiles' => DisplayScreen::PROFILES,
        ]);
    }
}
