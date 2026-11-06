<?php

namespace App\Modules\Core\Actions;

use App\Modules\Core\Contracts\OtpSender;
use App\Modules\Core\Models\OtpCode;
use App\Modules\Core\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class RequestLoginOtp
{
    public const PURPOSE = 'login';

    public const MAX_REQUESTS = 3;

    public const WINDOW_SECONDS = 600;

    public const VALID_MINUTES = 5;

    public function __construct(private OtpSender $sender) {}

    /**
     * Mengembalikan false bila terkena batas 3 kali per 10 menit. Nomor tak terdaftar tetap
     * dijawab sama oleh pemanggil agar daftar nomor wali tidak bisa ditebak.
     */
    public function handle(string $phone): bool
    {
        $key = 'otp-request:'.$phone;

        if (RateLimiter::tooManyAttempts($key, self::MAX_REQUESTS)) {
            return false;
        }

        RateLimiter::hit($key, self::WINDOW_SECONDS);

        $guardian = User::query()->where('type', 'guardian')->where('phone', $phone)->where('is_active', true)->exists();

        if (! $guardian) {
            return true;
        }

        OtpCode::query()->where('phone', $phone)->where('purpose', self::PURPOSE)->whereNull('consumed_at')->update(['consumed_at' => now()]);

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        OtpCode::create([
            'phone' => $phone,
            'purpose' => self::PURPOSE,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(self::VALID_MINUTES),
        ]);

        $this->sender->send($phone, $code);

        return true;
    }
}
