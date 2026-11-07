<?php

namespace App\Modules\Wallet\Actions;

use App\Modules\Core\Models\Student;
use App\Modules\Wallet\Models\StudentCard;
use App\Modules\Wallet\Support\CardResolver;
use App\Modules\Wallet\Support\WalletException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class IssueCard
{
    /**
     * UID boleh kosong: santri tetap bisa memakai QR di HP sebelum kartu fisiknya ada.
     */
    public function handle(Student $student, ?string $uid, ?string $pinHash = null): StudentCard
    {
        $uid = filled($uid) ? CardResolver::normalizeUid((string) $uid) : null;

        if ($uid !== null && StudentCard::query()->where('card_uid', $uid)->exists()) {
            throw new WalletException('UID ini sudah dipakai kartu lain.');
        }

        try {
            return DB::transaction(fn () => StudentCard::create([
                'student_id' => $student->id,
                'card_uid' => $uid,
                'qr_secret' => Str::random(64),
                'pin_hash' => $pinHash,
                'status' => 'active',
                'issued_at' => now(),
            ]));
        } catch (UniqueConstraintViolationException) {
            throw new WalletException('Santri ini sudah punya kartu aktif, atau UID sudah dipakai.');
        }
    }
}
