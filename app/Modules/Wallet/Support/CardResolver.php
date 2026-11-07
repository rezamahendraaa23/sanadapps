<?php

namespace App\Modules\Wallet\Support;

use App\Modules\Wallet\Models\StudentCard;
use Illuminate\Support\Str;

/**
 * Menerjemahkan hasil pindai (UID kartu atau isi QR) menjadi kartu. Tidak mengunci apa pun.
 */
final class CardResolver
{
    /**
     * @return array{card: StudentCard|null, method: string, problem: string|null}
     */
    public static function resolve(string $input, bool $forSpending): array
    {
        $input = trim($input);

        if ($input === '') {
            return ['card' => null, 'method' => 'card', 'problem' => 'Kartu tidak terbaca.'];
        }

        if (QrToken::isToken($input)) {
            $uuid = (string) QrToken::cardUuid($input);
            $card = Str::isUuid($uuid) ? StudentCard::query()->where('uuid', $uuid)->first() : null;

            if ($card === null) {
                return ['card' => null, 'method' => 'qr', 'problem' => 'QR tidak dikenali.'];
            }

            if ($forSpending && ! QrToken::isDynamic($input)) {
                return ['card' => $card, 'method' => 'qr', 'problem' => 'QR cetak tidak bisa untuk belanja. Tap kartu atau buka QR di HP santri.'];
            }

            return ['card' => $card, 'method' => 'qr', 'problem' => QrToken::problem($input, $card)];
        }

        $card = StudentCard::query()->where('card_uid', self::normalizeUid($input))->first();

        return ['card' => $card, 'method' => 'card', 'problem' => $card ? null : 'Kartu tidak terdaftar.'];
    }

    public static function normalizeUid(string $uid): string
    {
        return strtoupper((string) preg_replace('/[^0-9a-zA-Z]/', '', $uid));
    }
}
