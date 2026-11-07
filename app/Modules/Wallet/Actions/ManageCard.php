<?php

namespace App\Modules\Wallet\Actions;

use App\Modules\Wallet\Events\CardBlocked;
use App\Modules\Wallet\Models\StudentCard;
use App\Modules\Wallet\Support\WalletException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ManageCard
{
    public function __construct(private IssueCard $issue) {}

    /**
     * Berlaku seketika: pemindaian berikutnya langsung ditolak. Saldo tetap di dompet (CRD-04).
     */
    public function block(StudentCard $card, string $status, string $reason): void
    {
        if (! in_array($status, ['blocked', 'lost'], true)) {
            throw new WalletException('Status blokir tidak dikenal.');
        }

        DB::transaction(function () use ($card, $status, $reason): void {
            $locked = StudentCard::query()->whereKey($card->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'active') {
                return;
            }

            $locked->update(['status' => $status, 'blocked_at' => now(), 'blocked_reason' => $reason]);

            CardBlocked::dispatch($locked);
        });

        $card->refresh();
    }

    public function unblock(StudentCard $card): void
    {
        if ($card->status !== 'blocked') {
            throw new WalletException('Hanya kartu berstatus diblokir yang bisa dibuka. Kartu hilang harus diganti.');
        }

        if (StudentCard::query()->where('student_id', $card->student_id)->where('status', 'active')->exists()) {
            throw new WalletException('Santri ini sudah punya kartu aktif lain.');
        }

        $card->update(['status' => 'active', 'blocked_at' => null, 'blocked_reason' => null, 'pin_failed_count' => 0, 'locked_until' => null]);
    }

    /**
     * Kartu lama berhenti berlaku; PIN dibawa ke kartu baru agar wali tidak perlu mengatur ulang.
     */
    public function replace(StudentCard $card, ?string $newUid): StudentCard
    {
        return DB::transaction(function () use ($card, $newUid): StudentCard {
            $locked = StudentCard::query()->whereKey($card->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === 'active') {
                $locked->update(['status' => 'lost', 'blocked_at' => now(), 'blocked_reason' => 'Diganti kartu baru']);
                CardBlocked::dispatch($locked);
            }

            return $this->issue->handle($locked->student, $newUid, $locked->pin_hash);
        });
    }

    public function setPin(StudentCard $card, string $pin): void
    {
        if (! preg_match('/^\d{4,6}$/', $pin)) {
            throw new WalletException('PIN harus 4 sampai 6 angka.');
        }

        if (count(array_unique(str_split($pin))) === 1 || str_contains('0123456789', $pin) || str_contains('9876543210', $pin)) {
            throw new WalletException('PIN terlalu mudah ditebak. Hindari angka sama atau berurutan.');
        }

        $card->update(['pin_hash' => Hash::make($pin), 'pin_failed_count' => 0, 'locked_until' => null]);
    }

    public function resetPin(StudentCard $card): void
    {
        $card->update(['pin_hash' => null, 'pin_failed_count' => 0, 'locked_until' => null]);
    }
}
