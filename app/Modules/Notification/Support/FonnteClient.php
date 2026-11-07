<?php

namespace App\Modules\Notification\Support;

use App\Modules\Core\Models\Integration;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * API Fonnte: https://docs.fonnte.com/api-send-message/ dan /api-device-profile/.
 */
class FonnteClient
{
    private const BASE = 'https://api.fonnte.com';

    public function token(): ?string
    {
        return Integration::query()->where('provider', 'fonnte')->first()?->credentials['token'] ?? null;
    }

    /**
     * @return string|null Id pesan dari Fonnte.
     *
     * @throws FonnteException
     */
    public function send(string $target, string $message): ?string
    {
        $token = $this->token();

        if (blank($token)) {
            throw new FonnteException('Token Fonnte belum diisi.');
        }

        try {
            $response = Http::withHeaders(['Authorization' => $token])->asForm()->timeout(20)->post(self::BASE.'/send', [
                'target' => $target,
                'message' => $message,
                'countryCode' => '62',
            ]);
        } catch (ConnectionException $exception) {
            throw new FonnteException('Tidak tersambung ke Fonnte.', previous: $exception);
        }

        $json = $response->json() ?? [];

        if (! $response->successful() || ! ($json['status'] ?? $json['Status'] ?? false)) {
            throw new FonnteException((string) ($json['reason'] ?? $json['detail'] ?? 'Fonnte menolak pesan: HTTP '.$response->status()));
        }

        $id = $json['id'] ?? null;

        return is_array($id) ? (isset($id[0]) ? (string) $id[0] : null) : ($id === null ? null : (string) $id);
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function deviceStatus(?string $token = null): array
    {
        $token ??= $this->token();

        if (blank($token)) {
            return ['ok' => false, 'message' => 'Simpan token dulu.'];
        }

        try {
            $response = Http::withHeaders(['Authorization' => $token])->timeout(15)->post(self::BASE.'/device');
        } catch (ConnectionException) {
            return ['ok' => false, 'message' => 'Fonnte tidak bisa dihubungi.'];
        }

        $json = $response->json() ?? [];

        if (! ($json['status'] ?? false)) {
            return ['ok' => false, 'message' => 'Token ditolak Fonnte: '.($json['reason'] ?? 'tidak diketahui').'.'];
        }

        $connected = ($json['device_status'] ?? '') === 'connect';

        return [
            'ok' => $connected,
            'message' => $connected
                ? 'Tersambung. Perangkat '.($json['device'] ?? '').', sisa kuota '.($json['quota'] ?? '-').'.'
                : 'Token benar, tetapi perangkat WhatsApp belum tersambung di Fonnte.',
        ];
    }
}
