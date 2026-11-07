<?php

namespace App\Modules\Academic\Http;

use App\Modules\Academic\Actions\RecordFaceAttendance;
use App\Modules\Academic\Actions\SubmitSelfie;
use App\Modules\Academic\Face\FaceMatcher;
use App\Modules\Academic\Livewire\AttendanceToday;
use App\Modules\Academic\Models\AttendanceRecord;
use App\Modules\Academic\Models\AttendanceSession;
use App\Modules\Academic\Support\AcademicException;
use App\Modules\Academic\Support\AcademicSettings;
use App\Modules\Core\Actions\RecordAudit;
use App\Modules\Core\Models\Student;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Halaman kamera (S-10, pos wajah). Bukan Livewire: foto dikirim langsung dan bisa antre saat putus koneksi.
 */
class AttendanceCameraController
{
    private function student(Request $request): Student
    {
        $student = $request->user()->student;
        abort_if($student === null, 403);

        return $student;
    }

    public function selfie(Request $request, AttendanceSession $session, SubmitSelfie $selfies): View
    {
        $record = AttendanceRecord::query()->where('session_id', $session->id)->where('student_id', $this->student($request)->id)->first();
        abort_if($record === null || ! in_array('selfie', $session->allowed_modes ?? [], true), 403);

        $fence = AcademicSettings::geofence();
        $done = $record->scanned_at !== null && $record->review_status !== 'rejected';

        return view('academic.selfie', [
            'session' => $session,
            'record' => $record,
            'done' => $done,
            'config' => [
                'session' => $session->uuid,
                'code' => $done ? '' : $selfies->challengeFor($record),
                'closed' => $session->isClosed(),
                'geofence' => $fence['enabled'] && $fence['lat'] !== null,
                'url' => route('santri.selfie.store', $session),
                'home' => route('santri.home'),
            ],
        ]);
    }

    public function storeSelfie(Request $request, AttendanceSession $session, SubmitSelfie $selfies): JsonResponse
    {
        $data = $request->validate([
            'photo' => 'required|image|max:8192',
            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',
        ], ['photo.required' => 'Foto belum ada.', 'photo.image' => 'Berkas harus berupa foto.', 'photo.max' => 'Foto terlalu besar.']);

        try {
            $record = $selfies->handle($session, $this->student($request), $data['photo'], [
                'lat' => $data['lat'] ?? null,
                'lng' => $data['lng'] ?? null,
                'delayed' => $request->boolean('delayed'),
                'from_file' => $request->boolean('from_file'),
            ]);
        } catch (AcademicException $exception) {
            return response()->json(['ok' => false, 'message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'review' => $record->review_status === 'flagged',
            'status' => AttendanceRecord::STATUSES[$record->status],
        ]);
    }

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
