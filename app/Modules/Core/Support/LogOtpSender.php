<?php

namespace App\Modules\Core\Support;

use App\Modules\Core\Contracts\OtpSender;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Pengganti saat WhatsApp belum tersambung, untuk pengembangan. Di produksi menolak, agar kode tidak masuk log.
 */
class LogOtpSender implements OtpSender
{
    public function send(string $phone, string $code): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Pengiriman WhatsApp belum tersambung.');
        }

        Log::info("OTP {$phone}: {$code}");
    }
}
