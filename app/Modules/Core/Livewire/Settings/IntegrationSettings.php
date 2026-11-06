<?php

namespace App\Modules\Core\Livewire\Settings;

use App\Modules\Core\Actions\SaveIntegration;
use App\Modules\Core\Models\Integration;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.staff', ['active' => 'integrations'])]
#[Title('Integrasi')]
class IntegrationSettings extends Component
{
    public string $midtrans_mode = 'sandbox';

    public string $midtrans_server_key = '';

    public string $midtrans_client_key = '';

    public string $fonnte_token = '';

    public string $fonnte_sender = '';

    public string $fonnte_hourly_limit = '60';

    public function mount(): void
    {
        $midtrans = Integration::query()->where('provider', 'midtrans')->first();
        $fonnte = Integration::query()->where('provider', 'fonnte')->first();

        $this->midtrans_mode = $midtrans?->mode ?? 'sandbox';
        $this->fonnte_sender = (string) ($fonnte?->settings['sender'] ?? '');
        $this->fonnte_hourly_limit = (string) ($fonnte?->settings['hourly_limit'] ?? '60');
    }

    public function saveMidtrans(SaveIntegration $action): void
    {
        $this->validate([
            'midtrans_mode' => 'required|in:sandbox,production',
            'midtrans_server_key' => 'nullable|string|max:255',
            'midtrans_client_key' => 'nullable|string|max:255',
        ]);

        $action->handle('midtrans', $this->midtrans_mode, ['server_key' => $this->midtrans_server_key, 'client_key' => $this->midtrans_client_key], [], false);
        $this->reset('midtrans_server_key', 'midtrans_client_key');
        $this->dispatch('toast', message: 'Pengaturan Midtrans tersimpan.', tone: 'success');
    }

    public function saveFonnte(SaveIntegration $action): void
    {
        $this->validate([
            'fonnte_token' => 'nullable|string|max:255',
            'fonnte_sender' => 'nullable|string|max:30',
            'fonnte_hourly_limit' => 'required|integer|min:1|max:1000',
        ], ['fonnte_hourly_limit.required' => 'Isi batas pesan per jam.']);

        $action->handle('fonnte', null, ['token' => $this->fonnte_token], ['sender' => $this->fonnte_sender, 'hourly_limit' => (int) $this->fonnte_hourly_limit], false);
        $this->reset('fonnte_token');
        $this->dispatch('toast', message: 'Pengaturan Fonnte tersimpan.', tone: 'success');
    }

    public function render(): View
    {
        $midtrans = Integration::query()->where('provider', 'midtrans')->first()?->credentials ?? [];
        $fonnte = Integration::query()->where('provider', 'fonnte')->first()?->credentials ?? [];

        return view('core.settings.integrations', [
            'serverKeyMask' => SaveIntegration::mask($midtrans['server_key'] ?? null),
            'clientKeyMask' => SaveIntegration::mask($midtrans['client_key'] ?? null),
            'tokenMask' => SaveIntegration::mask($fonnte['token'] ?? null),
        ]);
    }
}
