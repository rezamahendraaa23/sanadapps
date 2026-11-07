<?php

namespace App\Modules\Academic\Http;

use App\Modules\Academic\Actions\RecordFaceAttendance;
use App\Modules\Academic\Face\FaceMatcher;
use App\Modules\Academic\Livewire\AttendanceToday;
use App\Modules\Academic\Models\AttendanceRecord;
use App\Modules\Academic\Models\AttendanceSession;
use App\Modules\Core\Actions\RecordAudit;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pos kamera wajah dan foto bukti. Bukan Livewire: gambar dikirim langsung sebagai berkas.
 */
class AttendanceCameraController
{
    /**
     * Foto bukti hanya untuk pengabsen sesi itu, dan setiap akses tercatat (PRD Sistem 8.9b).
     */
    public function evidence(Request $request, AttendanceRecord $record, RecordAudit $audit): Response
    {
        abort_unless($record->session->canBeTakenBy($request->user()), 403);
        abort_unless($record->evidence_path && Storage::disk('local')->exists($record->evidence_path), 404);
        $audit->handle('attendance.evidence_viewed', 'attendance_record', $record->id);

        return Storage::disk('local')->response($record->evidence_path, null, ['Cache-Control' => 'private, no-store']);
    }

    public function kiosk(Request $request, AttendanceSession $session, FaceMatcher $matcher): View
    {
        abort_unless($session->canBeTakenBy($request->user()), 403);
        abort_unless(in_array('face', $session->allowed_modes ?? [], true), 404);

        return view('academic.face-kiosk', [
            'session' => $session,
            'available' => $matcher->available(),
            'config' => [
                'challengeUrl' => route('attendance.face.challenge', $session),
                'scanUrl' => route('attendance.face.scan', $session),
            ],
            'backUrl' => AttendanceToday::sessionUrl($session),
        ]);
    }

    public function faceChallenge(Request $request, AttendanceSession $session, RecordFaceAttendance $faces): JsonResponse
    {
        abort_unless($session->canBeTakenBy($request->user()), 403);

        return response()->json($faces->challenge('user:'.$request->user()->id));
    }

    public function faceScan(Request $request, AttendanceSession $session, RecordFaceAttendance $faces): JsonResponse
    {
        abort_unless($session->canBeTakenBy($request->user()), 403);
        $data = $request->validate(['photo' => 'required|image|max:8192', 'liveness_frame' => 'required|image|max:8192', 'liveness_token' => 'required|string']);

        return response()->json($faces->scan($session, $data['photo'], $data['liveness_frame'], $data['liveness_token'], 'user:'.$request->user()->id));
    }
}
