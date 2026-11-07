<?php

namespace App\Modules\Wallet\Http;

use App\Modules\Academic\Actions\RecordAttendance;
use App\Modules\Academic\Models\AttendanceSession;
use App\Modules\Core\Support\ModuleRegistry;
use App\Modules\Leave\Actions\GateScan;
use App\Modules\Pos\Actions\CheckoutOrder;
use App\Modules\Pos\Models\Merchant;
use App\Modules\Wallet\Actions\RecordScan;
use App\Modules\Wallet\Models\Device;
use App\Modules\Wallet\Support\CardResolver;
use App\Modules\Wallet\Support\QrToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceScanController
{
    /** Scope kartu dan modul yang harus aktif (CRD-02). Perpustakaan dan makan belum punya modul di PRD 2.1 (A54). */
    private const SCOPE_MODULES = ['attendance' => 'attendance', 'gate' => 'gate', 'canteen' => 'pos', 'library' => null, 'meal' => null];

    public function scan(Request $request, ModuleRegistry $modules, RecordScan $record): JsonResponse
    {
        $data = $request->validate([
            'scope' => 'required|in:attendance,gate,canteen,library,meal',
            'card_uid' => 'required_without:qr|nullable|string|max:255',
            'qr' => 'required_without:card_uid|nullable|string|max:255',
        ]);

        /** @var Device $device */
        $device = $request->attributes->get('device');

        if (! in_array($data['scope'], $device->scopes, true)) {
            return response()->json(['ok' => false, 'message' => 'Perangkat ini tidak berhak untuk akses tersebut.'], 403);
        }

        $module = self::SCOPE_MODULES[$data['scope']];
        $input = (string) ($data['qr'] ?? $data['card_uid']);
        $resolved = CardResolver::resolve($input, false);
        $card = $resolved['card'];

        if ($module === null || ! $modules->enabled($module)) {
            $message = 'Akses ini belum aktif di pesantren.';
            $record->handle($card, $device->id, $data['scope'], 'denied', $message);

            return response()->json(['ok' => false, 'result' => 'denied', 'message' => $message]);
        }

        if ($card === null || $resolved['problem'] !== null) {
            $result = $card === null ? 'not_found' : 'denied';
            $record->handle($card, $device->id, $data['scope'], $result, $resolved['problem']);

            return response()->json(['ok' => false, 'result' => $result, 'message' => $resolved['problem']]);
        }

        if ($card->status !== 'active') {
            $message = 'Kartu diblokir.';
            $record->handle($card, $device->id, $data['scope'], 'blocked', $message);

            return response()->json(['ok' => false, 'result' => 'blocked', 'message' => $message]);
        }

        QrToken::consume($input);
        $record->handle($card, $device->id, $data['scope'], 'ok');
        $student = $card->student()->with('classroom:id,name')->first();

        return response()->json(['ok' => true, 'result' => 'ok', 'student' => ['uuid' => $student->uuid, 'name' => $student->name, 'nis' => $student->nis, 'classroom' => $student->classroom?->name]]);
    }

    public function attendance(Request $request, ModuleRegistry $modules, RecordAttendance $attendance): JsonResponse
    {
        $data = $request->validate([
            'session_id' => 'required|uuid',
            'card_uid' => 'required_without:qr|nullable|string|max:255',
            'qr' => 'required_without:card_uid|nullable|string|max:255',
        ]);

        /** @var Device $device */
        $device = $request->attributes->get('device');

        if (! $modules->enabled('attendance') || ! in_array('attendance', $device->scopes, true)) {
            return response()->json(['ok' => false, 'message' => 'Perangkat ini tidak berhak untuk absensi.'], 403);
        }

        $session = AttendanceSession::query()->where('uuid', $data['session_id'])->firstOrFail();

        return response()->json($attendance->scan($session, (string) ($data['qr'] ?? $data['card_uid']), $device->id));
    }

    public function gate(Request $request, ModuleRegistry $modules, GateScan $gate): JsonResponse
    {
        $data = $request->validate([
            'card_uid' => 'required_without:qr|nullable|string|max:255',
            'qr' => 'required_without:card_uid|nullable|string|max:255',
        ]);

        /** @var Device $device */
        $device = $request->attributes->get('device');

        if (! $modules->enabled('gate') || ! in_array('gate', $device->scopes, true)) {
            return response()->json(['ok' => false, 'message' => 'Perangkat ini tidak berhak untuk gerbang.'], 403);
        }

        $result = $gate->handle((string) ($data['qr'] ?? $data['card_uid']), null, $device->id);

        return response()->json([
            'ok' => in_array($result['outcome'], ['out', 'in'], true),
            'outcome' => $result['outcome'],
            'message' => $result['message'],
            'name' => $result['student']?->name,
        ]);
    }

    public function charge(Request $request, ModuleRegistry $modules, CheckoutOrder $checkout): JsonResponse
    {
        $data = $request->validate([
            'card_uid' => 'required_without:qr|nullable|string|max:255',
            'qr' => 'required_without:card_uid|nullable|string|max:255',
            'amount' => 'required|integer|min:1',
            'pin' => 'nullable|string|max:6',
            'merchant_id' => 'required|uuid',
            'idempotency_key' => 'required|string|max:100',
        ]);

        /** @var Device $device */
        $device = $request->attributes->get('device');

        if (! $modules->enabled('pos') || ! in_array('canteen', $device->scopes, true)) {
            return response()->json(['ok' => false, 'message' => 'Perangkat ini tidak berhak untuk belanja.'], 403);
        }

        $merchant = Merchant::query()->where('uuid', $data['merchant_id'])->firstOrFail();

        $result = $checkout->handle($merchant, [], (int) $data['amount'], (string) ($data['qr'] ?? $data['card_uid']), $data['pin'] ?? null, 'device:'.$device->id.':'.$data['idempotency_key'], null, $device->id);

        return response()->json([
            'ok' => $result->ok,
            'code' => $result->code,
            'message' => $result->message,
            'receipt_no' => $result->order?->receipt_no,
            'balance' => $result->balance,
        ]);
    }
}
