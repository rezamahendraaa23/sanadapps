<?php

namespace App\Modules\Core\Contracts;

interface OtpSender
{
    public function send(string $phone, string $code): void;
}
