<?php

namespace App\Modules\Notification\Jobs;

use App\Modules\Notification\Support\FonnteClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class SendOtpMessage implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 2;

    public int $maxExceptions = 2;

    public function __construct(public string $phone, public string $code) {}

    public function handle(FonnteClient $client): void
    {
        $client->send($this->phone, "Kode masuk Anda: {$this->code}\nBerlaku 5 menit. Jangan berikan kode ini kepada siapa pun.");
    }
}
