<?php

namespace App\Modules\Admission\Actions;

use App\Modules\Core\Actions\OtpResult;
use App\Modules\Core\Actions\RequestLoginOtp;
use App\Modules\Core\Actions\VerifyLoginOtp;
use App\Modules\Core\Contracts\OtpSender;
use App\Modules\Core\Models\OtpCode;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class AdmissionOtp
{
    public const PURPOSE = 'ppdb';

    public function __construct(private OtpSender $sender, private VerifyLoginOtp $verifier) {}

    /**
     * @return bool false bila terkena batas 3 kali per 10 menit.
     */
    public function send(string $phone): bool
    {
        $key = 'otp-ppdb:'.$phone;

        if (RateLimiter::tooManyAttempts($key, RequestLoginOtp::MAX_REQUESTS)) {
            return false;
        }

        RateLimiter::hit($key, RequestLoginOtp::WINDOW_SECONDS);
        OtpCode::query()->where('phone', $phone)->where('purpose', self::PURPOSE)->whereNull('consumed_at')->update(['consumed_at' => now()]);

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        OtpCode::create(['phone' => $phone, 'purpose' => self::PURPOSE, 'code_hash' => Hash::make($code), 'expires_at' => now()->addMinutes(RequestLoginOtp::VALID_MINUTES)]);
        $this->sender->send($phone, $code);

        return true;
    }

    public function verify(string $phone, string $code): OtpResult
    {
        return $this->verifier->handle($phone, $code, self::PURPOSE)[0];
    }
}
