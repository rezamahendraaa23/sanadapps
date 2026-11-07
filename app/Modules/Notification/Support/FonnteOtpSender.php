<?php

namespace App\Modules\Notification\Support;

use App\Modules\Core\Contracts\OtpSender;
use App\Modules\Core\Support\LogOtpSender;
use App\Modules\Core\Support\ModuleRegistry;
use App\Modules\Notification\Jobs\SendOtpMessage;

/**
 * OTP lewat Fonnte bila modul aktif. Kode tidak pernah masuk notification_logs.
 */
class FonnteOtpSender implements OtpSender
{
    public function __construct(private ModuleRegistry $modules, private FonnteClient $client, private LogOtpSender $fallback) {}

    public function send(string $phone, string $code): void
    {
        if (! $this->modules->enabled('notification_wa') || blank($this->client->token())) {
            $this->fallback->send($phone, $code);

            return;
        }

        SendOtpMessage::dispatch($phone, $code)->onQueue('otp');
    }
}
