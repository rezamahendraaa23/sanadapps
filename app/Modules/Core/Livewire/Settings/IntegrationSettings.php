<?php

namespace App\Modules\Core\Livewire\Settings;

use App\Modules\Core\Actions\SaveIntegration;
use App\Modules\Core\Models\Integration;
use App\Modules\Core\Support\PhoneNumber;
use App\Modules\Notification\Support\FonnteClient;
use App\Modules\Notification\Support\FonnteException;
use App\Modules\Payment\Contracts\PaymentGateway;
use App\Modules\Payment\Support\MidtransConfig;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.staff', ['active' => 'integrations'])]
#[Title('Integrasi')]
class IntegrationSettings extends Component
{
    public string $midtrans_mode = 'sandbox';

    /** @var array{ok: bool, message: string}|null */
    #[Locked]
    public ?array $midtransTest = null;

    public string $midtrans_server_key = '';

    public string $midtrans_client_key = '';

    /** @var list<string> */
    public array $midtrans_channels = [];

    public string $midtrans_fee_mode = 'absorbed';

    public string $midtrans_admin_fee = '';

    public string $fonnte_token = '';

    public string $fonnte_sender = '';

    public string $fonnte_hourly_limit = '60';

    public string $fonnte_test_phone = '';

    /** @var array{ok: bool, message: string}|null */
    #[Locked]
    public ?array $fonnteTest = null;

    public function mount(): void
    {
        $midtrans = Integration::query()->where('provider', 'midtrans')->first();
        $fonnte = Integration::query()->where('provider', 'fonnte')->first();

        $this->midtrans_mode = $midtrans?->mode ?? 'sandbox';
        $this->midtrans_channels = $midtrans?->settings['channels'] ?? array_keys(MidtransConfig::CHANNELS);
        $this->midtrans_fee_mode = $midtrans?->settings['fee_mode'] ?? 'absorbed';
        $this->midtrans_admin_fee = (string) ($midtrans?->settings['admin_fee'] ?? '');
        $this->fonnte_sender = (string) ($fonnte?->settings['sender'] ?? '');
        $this->fonnte_hourly_limit = (string) ($fonnte?->settings['hourly_limit'] ?? '60');
    }

    public function saveMidtrans(SaveIntegration $action): void
    {
        $this->validate([
            'midtrans_mode' => 'required|in:sandbox,production',
            'midtrans_server_key' => 'nullable|string|max:255',
            'midtrans_client_key' => 'nullable|string|max:255',
            'midtrans_channels' => 'required|array|min:1',
            'midtrans_channels.*' => 'in:'.implode(',', array_keys(MidtransConfig::CHANNELS)),
            'midtrans_fee_mode' => 'required|in:absorbed,passed',
        ], ['midtrans_channels.required' => 'Pilih minimal satu kanal.', 'midtrans_channels.min' => 'Pilih minimal satu kanal.']);

        $fee = (int) preg_replace('/\D/', '', $this->midtrans_admin_fee);

        $action->handle('midtrans', $this->midtrans_mode, ['server_key' => $this->midtrans_server_key, 'client_key' => $this->midtrans_client_key], [
            'channels' => array_values($this->midtrans_channels),
            'fee_mode' => $this->midtrans_fee_mode,
            'admin_fee' => $fee,
        ], true);
        app(MidtransConfig::class)->forget();
        $this->reset('midtrans_server_key', 'midtrans_client_key');
        $this->dispatch('toast', message: 'Pengaturan Midtrans tersimpan.', tone: 'success');
    }

    public function testMidtrans(PaymentGateway $gateway, MidtransConfig $config): void
    {
        $key = $config->serverKey();

        if (blank($key)) {
            $this->midtransTest = ['ok' => false, 'message' => 'Simpan server key dulu.'];

            return;
        }

        $this->midtransTest = $gateway->credentialsAreValid($key, $config->mode())
            ? ['ok' => true, 'message' => 'Tersambung ke Midtrans mode '.$config->mode().'.']
            : ['ok' => false, 'message' => 'Kunci ditolak atau Midtrans tidak bisa dihubungi. Periksa server key dan mode.'];
    }

    public function testFonnte(FonnteClient $client): void
    {
        $this->fonnteTest = $client->deviceStatus();
    }

    public function testFonnteSend(FonnteClient $client): void
    {
        $this->resetErrorBag();
        $phone = PhoneNumber::normalize($this->fonnte_test_phone);

        if ($phone === null) {
            $this->addError('fonnte_test_phone', 'Nomor belum benar. Contoh: 081234567890.');

            return;
        }

        try {
            $client->send($phone, 'Pesan uji dari SANAD. Bila ini sampai, WhatsApp sudah tersambung.');
            $this->fonnteTest = ['ok' => true, 'message' => 'Pesan uji terkirim ke antrean Fonnte.'];
        } catch (FonnteException $exception) {
            $this->fonnteTest = ['ok' => false, 'message' => 'Gagal mengirim: '.$exception->getMessage()];
        }
    }

    public function saveFonnte(SaveIntegration $action): void
    {
        $this->validate([
            'fonnte_token' => 'nullable|string|max:255',
            'fonnte_sender' => 'nullable|string|max:30',
            'fonnte_hourly_limit' => 'required|integer|min:1|max:1000',
        ], ['fonnte_hourly_limit.required' => 'Isi batas pesan per jam.']);

        $action->handle('fonnte', null, ['token' => $this->fonnte_token], [
            'sender' => $this->fonnte_sender,
            'hourly_limit' => (int) $this->fonnte_hourly_limit,
            'webhook_secret' => Integration::query()->where('provider', 'fonnte')->first()?->settings['webhook_secret'] ?? Str::random(40),
        ], true);
        $this->reset('fonnte_token');
        $this->dispatch('toast', message: 'Pengaturan Fonnte tersimpan.', tone: 'success');
    }

    public function render(): View
    {
        $midtrans = Integration::query()->where('provider', 'midtrans')->first()?->credentials ?? [];
        $fonnte = Integration::query()->where('provider', 'fonnte')->first()?->credentials ?? [];

        return view('core.settings.integrations', [
            'channels' => MidtransConfig::CHANNELS,
            'webhookUrl' => route('webhooks.midtrans'),
            'fonnteWebhookUrl' => ($secret = Integration::query()->where('provider', 'fonnte')->first()?->settings['webhook_secret'] ?? null) ? route('webhooks.fonnte', $secret) : null,
            'serverKeyMask' => SaveIntegration::mask($midtrans['server_key'] ?? null),
            'clientKeyMask' => SaveIntegration::mask($midtrans['client_key'] ?? null),
            'tokenMask' => SaveIntegration::mask($fonnte['token'] ?? null),
        ]);
    }
}
