<?php

namespace App\Modules\Masjid\Actions;

use App\Modules\Core\Actions\RecordAudit;
use App\Modules\Masjid\Models\DisplayCommand;
use App\Modules\Masjid\Models\DisplayEvent;
use App\Modules\Masjid\Models\DisplayPairing;
use App\Modules\Masjid\Models\DisplayScreen;
use App\Modules\Masjid\Support\MasjidException;
use App\Modules\Wallet\Models\Device;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ManageScreens
{
    public const PAIRING_MINUTES = 10;

    public function __construct(private RecordAudit $audit) {}

    /**
     * @return array{code: string, pairing_token: string, expires_at: string}
     */
    public function startPairing(): array
    {
        DisplayPairing::query()->whereNull('claimed_screen_id')->where('expires_at', '<', now())->delete();

        do {
            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } while (DisplayPairing::query()->where('code', $code)->whereNull('claimed_screen_id')->where('expires_at', '>', now())->exists());

        $token = Str::random(48);
        $pairing = DisplayPairing::create(['code' => $code, 'pairing_token_hash' => hash('sha256', $token), 'expires_at' => now()->addMinutes(self::PAIRING_MINUTES)]);

        return ['code' => $code, 'pairing_token' => $token, 'expires_at' => $pairing->expires_at->toIso8601ZuluString()];
    }

    /**
     * Sekali pakai: token layar dikeluarkan sekali, lalu dihapus dari server.
     *
     * @return array{status: string, token?: string}
     */
    public function pairingStatus(string $pairingToken): array
    {
        return DB::transaction(function () use ($pairingToken): array {
            $pairing = DisplayPairing::query()->where('pairing_token_hash', hash('sha256', $pairingToken))->lockForUpdate()->first();

            if ($pairing === null || ($pairing->claimed_screen_id === null && $pairing->expires_at->isPast())) {
                return ['status' => 'expired'];
            }

            if ($pairing->claimed_screen_id === null) {
                return ['status' => 'waiting'];
            }

            $token = $pairing->issued_token;
            $pairing->delete();

            return $token ? ['status' => 'claimed', 'token' => $token] : ['status' => 'expired'];
        });
    }

    /**
     * @param  array{name: string, location?: string|null, profile?: string, orientation?: string, content_pattern?: string}  $data
     */
    public function claim(string $code, array $data): DisplayScreen
    {
        return DB::transaction(function () use ($code, $data): DisplayScreen {
            $pairing = DisplayPairing::query()->where('code', trim($code))->whereNull('claimed_screen_id')->where('expires_at', '>', now())->lockForUpdate()->first();

            if ($pairing === null) {
                throw new MasjidException('Kode salah atau sudah kedaluwarsa. Lihat kode terbaru di layar.');
            }

            $token = Str::random(64);
            $device = Device::create(['name' => $data['name'], 'location' => $data['location'] ?? null, 'type' => 'tv_display', 'scopes' => ['display'], 'api_key_hash' => hash('sha256', $token)]);
            $screen = DisplayScreen::create([...$data, 'device_id' => $device->id]);
            $pairing->update(['claimed_screen_id' => $screen->id, 'issued_token' => $token]);

            return $screen;
        });
    }

    public function revoke(DisplayScreen $screen): void
    {
        DB::transaction(function () use ($screen): void {
            $screen->update(['is_active' => false]);
            $screen->device->update(['is_active' => false, 'api_key_hash' => hash('sha256', Str::random(64))]);
            $this->audit->handle('masjid.screen_revoked', 'display_screen', $screen->id);
        });
    }

    public function command(DisplayScreen $screen, string $command, int $userId): void
    {
        if (! in_array($command, ['reload', 'test', 'dim', 'undim'], true)) {
            throw new MasjidException('Perintah tidak dikenal.');
        }

        DisplayCommand::create(['screen_id' => $screen->id, 'command' => $command, 'sent_by' => $userId]);
        $this->audit->handle('masjid.screen_command', 'display_screen', $screen->id, null, ['command' => $command]);
    }

    /**
     * @param  array{version?: string|null, app_version?: string|null, clock_offset_ms?: int|null}  $status
     */
    public function heartbeat(DisplayScreen $screen, array $status): void
    {
        $screen->update(['last_seen_at' => now(), 'last_manifest_version' => $status['version'] ?? null, 'app_version' => $status['app_version'] ?? null, 'clock_offset_ms' => isset($status['clock_offset_ms']) ? (int) $status['clock_offset_ms'] : null]);
        DisplayEvent::create(['screen_id' => $screen->id, 'type' => 'heartbeat', 'payload' => $status, 'occurred_at' => now()]);
    }

    public function pruneEvents(): int
    {
        return DisplayEvent::query()->where('occurred_at', '<', now()->subDays(30))->delete();
    }
}
