<?php

namespace App\Modules\Payment\Support;

use App\Modules\Core\Models\Integration;

/**
 * Pengaturan Midtrans milik pesantren. Kunci rahasia hanya keluar lewat serverKey(), untuk server.
 */
class MidtransConfig
{
    public const CHANNELS = [
        'va' => ['label' => 'Virtual Account bank', 'codes' => ['bca_va', 'bni_va', 'bri_va', 'permata_va', 'echannel', 'other_va']],
        'qris' => ['label' => 'QRIS', 'codes' => ['other_qris']],
        'ewallet' => ['label' => 'E-wallet (GoPay, ShopeePay)', 'codes' => ['gopay', 'shopeepay']],
        'store' => ['label' => 'Minimarket', 'codes' => ['indomaret', 'alfamart']],
    ];

    private ?Integration $integration = null;

    private bool $loaded = false;

    private function integration(): ?Integration
    {
        if (! $this->loaded) {
            $this->integration = Integration::query()->where('provider', 'midtrans')->first();
            $this->loaded = true;
        }

        return $this->integration;
    }

    public function serverKey(): ?string
    {
        return $this->integration()?->credentials['server_key'] ?? null;
    }

    public function clientKey(): ?string
    {
        return $this->integration()?->credentials['client_key'] ?? null;
    }

    public function mode(): string
    {
        return $this->integration()?->mode ?? 'sandbox';
    }

    public function isReady(): bool
    {
        return filled($this->serverKey()) && filled($this->clientKey());
    }

    public function snapScriptUrl(): string
    {
        return $this->mode() === 'production'
            ? 'https://app.midtrans.com/snap/snap.js'
            : 'https://app.sandbox.midtrans.com/snap/snap.js';
    }

    /**
     * @return list<string>
     */
    public function selectedChannels(): array
    {
        return $this->integration()?->settings['channels'] ?? array_keys(self::CHANNELS);
    }

    /**
     * @return list<string>
     */
    public function enabledPayments(): array
    {
        $codes = [];

        foreach ($this->selectedChannels() as $channel) {
            array_push($codes, ...(self::CHANNELS[$channel]['codes'] ?? []));
        }

        return $codes;
    }

    /**
     * Biaya admin dibebankan ke wali hanya pada mode passed; pada mode absorbed ditanggung pesantren.
     */
    public function adminFee(): int
    {
        $settings = $this->integration()?->settings ?? [];

        return ($settings['fee_mode'] ?? 'absorbed') === 'passed' ? (int) ($settings['admin_fee'] ?? 0) : 0;
    }

    public function forget(): void
    {
        $this->integration = null;
        $this->loaded = false;
    }
}
