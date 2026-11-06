<?php

namespace App\Modules\Core\Actions;

use App\Modules\Core\Models\OtpCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class VerifyLoginOtp
{
    public const MAX_ATTEMPTS = 3;

    /**
     * @return array{OtpResult, int} Hasil dan sisa percobaan.
     */
    public function handle(string $phone, string $code): array
    {
        return DB::transaction(function () use ($phone, $code): array {
            $otp = OtpCode::query()
                ->where('phone', $phone)
                ->where('purpose', RequestLoginOtp::PURPOSE)
                ->whereNull('consumed_at')
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if ($otp === null || $otp->expires_at->isPast()) {
                return [OtpResult::Expired, 0];
            }

            if ($otp->attempts >= self::MAX_ATTEMPTS) {
                return [OtpResult::Locked, 0];
            }

            if (! Hash::check($code, $otp->code_hash)) {
                $otp->increment('attempts');
                $left = self::MAX_ATTEMPTS - $otp->attempts;

                return [$left === 0 ? OtpResult::Locked : OtpResult::Wrong, $left];
            }

            $otp->update(['consumed_at' => now()]);

            return [OtpResult::Valid, self::MAX_ATTEMPTS - $otp->attempts];
        });
    }
}
