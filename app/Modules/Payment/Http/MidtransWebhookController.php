<?php

namespace App\Modules\Payment\Http;

use App\Modules\Payment\Actions\ProcessMidtransWebhook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MidtransWebhookController
{
    public function __invoke(Request $request, ProcessMidtransWebhook $action): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);

        if (! is_array($payload)) {
            return response()->json(['error' => ['code' => 'INVALID_PAYLOAD', 'message' => 'Isi notifikasi tidak valid.']], 400);
        }

        $status = $action->handle($payload, $request->getContent());

        return response()->json(['ok' => $status === 200], $status);
    }
}
