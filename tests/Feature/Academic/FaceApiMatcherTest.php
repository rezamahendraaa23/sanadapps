<?php

use App\Modules\Academic\Actions\EnrollFace;
use App\Modules\Academic\Actions\ManageBiometricConsent;
use App\Modules\Academic\Actions\OpenAttendanceSession;
use App\Modules\Academic\Actions\RecordFaceAttendance;
use App\Modules\Academic\Face\FaceApiMatcher;
use App\Modules\Academic\Face\FaceInput;
use App\Modules\Academic\Face\FaceMatcher;
use App\Modules\Academic\Face\FaceQualityException;
use App\Modules\Academic\Face\FakeFaceMatcher;
use App\Modules\Academic\Models\AttendanceRecord;
use App\Modules\Academic\Support\AcademicSettings;
use App\Modules\Core\Models\Dormitory;
use App\Modules\Core\Models\Guardian;
use App\Modules\Core\Models\Module;
use App\Modules\Core\Models\Student;
use App\Modules\Core\Models\User;
use App\Modules\Core\Support\ModuleRegistry;
use Database\Seeders\ModuleSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Storage;

/**
 * Vektor panjang 1 yang berjarak tepat $distance dari vektor dasar.
 */
function faceVector(float $distance = 0.0, int $axis = 1): array
{
    $vector = array_fill(0, 128, 0.0);
    $vector[0] = 1.0 - $distance * $distance / 2;
    $vector[$axis] = sqrt(max(0, 1 - $vector[0] ** 2));

    return $vector;
}

function faceProbe(array $vector, array $motion = []): FaceInput
{
    return new FaceInput(descriptor: $vector, motion: $motion);
}

describe('perhitungan', function () {
    beforeEach(fn () => $this->matcher = new FaceApiMatcher);

    it('memilih jarak terdekat; skor 1 dikurangi jarak', function () {
        $found = $this->matcher->identify(faceProbe(faceVector()), [7 => faceVector(0.7, 2), 8 => faceVector(0.3, 3)]);

        expect($found->studentId)->toBe(8)->and(round($found->score, 2))->toBe(0.70)->and($found->ambiguous)->toBeFalse();
    });

    it('dua kandidat hampir sama mirip ditandai meragukan', function () {
        $found = $this->matcher->identify(faceProbe(faceVector()), [7 => faceVector(0.30, 2), 8 => faceVector(0.33, 3)]);

        expect($found->studentId)->toBe(7)->and($found->ambiguous)->toBeTrue();
    });

    it('tanpa kandidat null; bukan hasil deteksi wajah ditolak', function () {
        expect($this->matcher->identify(faceProbe(faceVector()), []))->toBeNull()
            ->and(fn () => $this->matcher->identify(faceProbe([1, 2]), []))->toThrow(FaceQualityException::class)
            ->and(fn () => $this->matcher->identify(faceProbe(array_fill(0, 128, 0)), []))->toThrow(FaceQualityException::class)
            ->and(fn () => $this->matcher->identify('gambar', []))->toThrow(FaceQualityException::class)
            ->and(fn () => $this->matcher->identify(faceProbe(array_fill(0, 128, 'x')), []))->toThrow(FaceQualityException::class);
    });

    it('pendaftaran merata-rata; foto orang lain ditolak dengan nomor fotonya', function () {
        $template = $this->matcher->enroll([faceProbe(faceVector()), faceProbe(faceVector(0.2, 2)), faceProbe(faceVector(0.2, 3))]);
        expect($template->data)->toHaveCount(128);

        expect(fn () => $this->matcher->enroll([faceProbe(faceVector()), faceProbe(faceVector(0.9, 2)), faceProbe(faceVector())]))->toThrow(FaceQualityException::class, 'Foto 2 terlihat seperti orang lain')
            ->and(fn () => $this->matcher->enroll([faceProbe(faceVector()), faceProbe([1])]))->toThrow(FaceQualityException::class, 'Foto 2: Wajah tidak terbaca');
    });

    it('gerakan: kedip dan tengok terbaca, foto diam gagal', function () {
        $still = array_fill(0, 6, ['ear' => 0.30, 'yaw' => 0.01]);
        $blink = [...$still, ['ear' => 0.20, 'yaw' => 0.01]];
        $turn = [...$still, ['ear' => 0.30, 'yaw' => 0.20]];

        expect($this->matcher->isLive('Kedipkan mata', faceProbe(faceVector(), $still)))->toBeFalse()
            ->and($this->matcher->isLive('Kedipkan mata', faceProbe(faceVector(), $blink)))->toBeTrue()
            ->and($this->matcher->isLive('Tengok ke kiri', faceProbe(faceVector(), $blink)))->toBeFalse()
            ->and($this->matcher->isLive('Tengok ke kanan', faceProbe(faceVector(), $turn)))->toBeTrue()
            ->and($this->matcher->isLive('Kedipkan mata', faceProbe(faceVector(), [['ear' => 0.1, 'yaw' => 0]])))->toBeFalse();
    });
});

describe('alur absensi', function () {
    beforeEach(function () {
        (new ModuleSeeder)->run();
        (new RoleSeeder)->run();
        Module::whereIn('module_key', ['attendance', 'face_attendance'])->update(['is_enabled' => true]);
        app(ModuleRegistry::class)->forget();
        app()->bind(FaceMatcher::class, FaceApiMatcher::class);
        Storage::fake('local');
        $module = Module::where('module_key', 'attendance')->first();
        $module->update(['settings' => ['verification_modes' => ['manual', 'face']]]);

        $this->musyrif = tap(User::create(['name' => 'Musyrif', 'email' => 'm@uji.test', 'type' => 'staff']))->assignRole('musyrif');
        $this->dormitory = Dormitory::create(['name' => 'A', 'gender' => 'L', 'musyrif_user_id' => $this->musyrif->id]);
        $this->first = Student::create(['nis' => 'A1', 'name' => 'Ahmad', 'gender' => 'L', 'dormitory_id' => $this->dormitory->id]);
        $this->second = Student::create(['nis' => 'A2', 'name' => 'Budi', 'gender' => 'L', 'dormitory_id' => $this->dormitory->id]);
        $this->session = app(OpenAttendanceSession::class)->forDormitory($this->dormitory->id, 'asrama', 'Apel', now(config('sanad.display_timezone'))->toDateString(), '00:00', 'face', $this->musyrif->id);

        $guardian = Guardian::create(['name' => 'Wali', 'phone_wa' => '6281200000001']);
        $register = function (Student $student, array $vector) use ($guardian) {
            app(ManageBiometricConsent::class)->grant($student, $guardian, 'digital', null, null, null);
            app(EnrollFace::class)->handle($student, [faceProbe($vector), faceProbe($vector), faceProbe($vector)], $this->musyrif->id);
        };
        $register($this->first, faceVector());
        $register($this->second, faceVector(0.9, 5));
    });

    function scanFace($context, array $vector, array $motion): array
    {
        $faces = app(RecordFaceAttendance::class);
        $token = $faces->challenge('uji')['token'];

        return $faces->scan($context->session, faceProbe($vector, $motion), null, $token, 'uji');
    }

    it('ambang bawaan mengikuti driver', function () {
        expect(AcademicSettings::faceThresholds())->toBe(['upper' => 0.60, 'lower' => 0.52]);

        app()->bind(FaceMatcher::class, FakeFaceMatcher::class);
        expect(AcademicSettings::faceThresholds())->toBe(['upper' => 0.80, 'lower' => 0.60]);
    });

    it('jarak dekat hadir, jarak batas menunggu, jarak jauh ditolak, foto diam gagal', function () {
        $moving = array_merge(array_fill(0, 4, ['ear' => 0.3, 'yaw' => 0.0]), [['ear' => 0.2, 'yaw' => 0.3], ['ear' => 0.3, 'yaw' => 0.0]]);
        $still = array_fill(0, 6, ['ear' => 0.3, 'yaw' => 0.0]);

        expect(scanFace($this, faceVector(0.2, 7), $still)['outcome'])->toBe('liveness');
        expect(scanFace($this, faceVector(0.6, 7), $moving))->toMatchArray(['ok' => false, 'outcome' => 'rejected', 'fallback' => true]);
        expect(scanFace($this, faceVector(0.45, 7), $moving))->toMatchArray(['ok' => true, 'outcome' => 'pending', 'name' => 'Ahmad']);

        $record = AttendanceRecord::where('session_id', $this->session->id)->where('student_id', $this->first->id)->first();
        expect($record->review_status)->toBe('pending')->and($record->status)->toBe('alpa');

        $record->update(['scanned_at' => null, 'review_status' => 'none']);
        expect(scanFace($this, faceVector(0.2, 7), $moving))->toMatchArray(['ok' => true, 'outcome' => 'present', 'name' => 'Ahmad']);
    });

    it('kakak beradik: walau sangat mirip, dua kandidat berdekatan tidak hadir otomatis', function () {
        $vector = faceVector(0.1, 7);
        $brother = Student::create(['nis' => 'A3', 'name' => 'Adik', 'gender' => 'L', 'dormitory_id' => $this->dormitory->id]);
        $session = app(OpenAttendanceSession::class)->forDormitory($this->dormitory->id, 'asrama', 'Apel 2', now(config('sanad.display_timezone'))->toDateString(), '00:00', 'face', $this->musyrif->id);
        app(ManageBiometricConsent::class)->grant($brother, Guardian::first(), 'digital', null, null, null);
        $similar = faceVector(0.2, 7);
        app(EnrollFace::class)->handle($brother, [faceProbe($similar), faceProbe($similar), faceProbe($similar)], $this->musyrif->id);

        $faces = app(RecordFaceAttendance::class);
        $result = $faces->scan($session, faceProbe($vector, [['ear' => 0.3, 'yaw' => 0], ['ear' => 0.2, 'yaw' => 0.3], ['ear' => 0.3, 'yaw' => 0]]), null, $faces->challenge('uji')['token'], 'uji');

        expect($result['outcome'])->toBe('pending');
    });

    it('rute pos: halaman memuat mesin browser, pindai menerima 128 angka', function () {
        $this->actingAs($this->musyrif);
        $this->get(route('attendance.face', $this->session))->assertOk()->assertSee('browser', false)->assertDontSee('Mulai Pindai');

        $token = $this->postJson(route('attendance.face.challenge', $this->session))->assertOk()->json('token');
        $this->post(route('attendance.face.scan', $this->session), [
            'descriptor' => json_encode(faceVector(0.2, 7)),
            'motion' => json_encode([['ear' => 0.3, 'yaw' => 0], ['ear' => 0.2, 'yaw' => 0.3], ['ear' => 0.3, 'yaw' => 0]]),
            'liveness_token' => $token,
        ], ['Accept' => 'application/json'])->assertOk()->assertJsonFragment(['name' => 'Ahmad']);
    });
});
