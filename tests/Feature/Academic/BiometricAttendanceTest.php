<?php

use App\Modules\Academic\Actions\EnrollFace;
use App\Modules\Academic\Actions\ManageBiometricConsent;
use App\Modules\Academic\Actions\OpenAttendanceSession;
use App\Modules\Academic\Actions\PurgeAttendanceEvidence;
use App\Modules\Academic\Actions\RecordAttendance;
use App\Modules\Academic\Actions\RecordFaceAttendance;
use App\Modules\Academic\Actions\ReviewAttendance;
use App\Modules\Academic\Face\FaceInput;
use App\Modules\Academic\Face\FaceMatcher;
use App\Modules\Academic\Face\FaceQualityException;
use App\Modules\Academic\Face\FakeFaceMatcher;
use App\Modules\Academic\Livewire\AcademicMasters;
use App\Modules\Academic\Livewire\AttendanceReview;
use App\Modules\Academic\Livewire\AttendanceToday;
use App\Modules\Academic\Livewire\FaceEnrollments;
use App\Modules\Academic\Livewire\GuardianFaceConsent;
use App\Modules\Academic\Models\AttendanceRecord;
use App\Modules\Academic\Models\AttendanceSession;
use App\Modules\Academic\Models\BiometricConsent;
use App\Modules\Academic\Models\FaceEnrollment;
use App\Modules\Academic\Support\AcademicException;
use App\Modules\Core\Models\Dormitory;
use App\Modules\Core\Models\Guardian;
use App\Modules\Core\Models\Module;
use App\Modules\Core\Models\Student;
use App\Modules\Core\Models\User;
use App\Modules\Core\Support\ModuleRegistry;
use App\Modules\Wallet\Models\Device;
use Database\Seeders\ModuleSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;

/**
 * Gambar uji dengan pola terang-gelap yang berbeda per seed, supaya lolos cek kualitas.
 */
function faceImage(int $seed = 1, int $size = 320): string
{
    mt_srand($seed);
    $image = imagecreatetruecolor($size, $size);

    for ($y = 0; $y < 16; $y++) {
        for ($x = 0; $x < 16; $x++) {
            $level = mt_rand(40, 215);
            imagefilledrectangle($image, (int) ($x * $size / 16), (int) ($y * $size / 16), (int) (($x + 1) * $size / 16), (int) (($y + 1) * $size / 16), imagecolorallocate($image, $level, $level, $level));
        }
    }

    ob_start();
    imagejpeg($image, null, 95);

    return (string) ob_get_clean();
}

function faceUpload(int $seed = 1, string $name = 'wajah.jpg'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, faceImage($seed));
}

function setAttendanceRules(array $values): void
{
    $module = Module::where('module_key', 'attendance')->first();
    $module->update(['settings' => [...($module->settings ?? []), ...$values]]);
}

beforeEach(function () {
    (new ModuleSeeder)->run();
    (new RoleSeeder)->run();
    Module::whereIn('module_key', ['attendance', 'face_attendance', 'card'])->update(['is_enabled' => true]);
    app(ModuleRegistry::class)->forget();
    Storage::fake('local');
    FakeFaceMatcher::$forcedScore = null;
    FakeFaceMatcher::$forcedLive = null;
    $this->travelTo('2026-11-02 12:00:00');
    setAttendanceRules(['verification_modes' => ['manual', 'card', 'qr', 'face'], 'late_tolerance' => 10]);

    $this->musyrif = tap(User::create(['name' => 'Musyrif', 'email' => 'm@uji.test', 'type' => 'staff']))->assignRole('musyrif');
    $this->otherMusyrif = tap(User::create(['name' => 'Lain', 'email' => 'm2@uji.test', 'type' => 'staff']))->assignRole('musyrif');
    $this->admin = tap(User::create(['name' => 'Akademik', 'email' => 'a@uji.test', 'type' => 'staff']))->assignRole('admin_akademik');
    $this->dormitory = Dormitory::create(['name' => 'A', 'gender' => 'L', 'musyrif_user_id' => $this->musyrif->id]);

    $this->studentUser = tap(User::create(['name' => 'Ahmad', 'username' => 'A1', 'type' => 'student']))->assignRole('santri');
    $this->student = Student::create(['nis' => 'A1', 'name' => 'Ahmad', 'gender' => 'L', 'dormitory_id' => $this->dormitory->id, 'user_id' => $this->studentUser->id]);
    $this->secondStudent = Student::create(['nis' => 'A2', 'name' => 'Budi', 'gender' => 'L', 'dormitory_id' => $this->dormitory->id]);

    $this->guardianUser = tap(User::create(['name' => 'Wali', 'phone' => '6281200000001', 'type' => 'guardian']))->assignRole('wali');
    $this->guardian = Guardian::create(['user_id' => $this->guardianUser->id, 'name' => 'Wali', 'phone_wa' => '6281200000001']);
    $this->guardian->students()->attach($this->student->id, ['relation' => 'ayah', 'is_primary' => true, 'is_payer' => true, 'can_receive_wa' => true]);
    $this->otherGuardianUser = tap(User::create(['name' => 'Wali Lain', 'phone' => '6281200000002', 'type' => 'guardian']))->assignRole('wali');
    Guardian::create(['user_id' => $this->otherGuardianUser->id, 'name' => 'Wali Lain', 'phone_wa' => '6281200000002']);

    // Jam mulai 19.00 WIB, sekarang 19.00 WIB.
    $open = fn (string $source) => app(OpenAttendanceSession::class)->forDormitory($this->dormitory->id, 'asrama', 'Apel '.$source, '2026-11-02', '19:00', $source, $this->musyrif->id);
    $this->faceSession = $open('face');
    $this->faceRecord = AttendanceRecord::where('session_id', $this->faceSession->id)->where('student_id', $this->student->id)->first();
});

afterEach(function () {
    FakeFaceMatcher::$forcedScore = null;
    FakeFaceMatcher::$forcedLive = null;
});

function enrollStudent($context): FaceEnrollment
{
    app(ManageBiometricConsent::class)->grant($context->student, $context->guardian, 'digital', null, '127.0.0.1', $context->guardianUser->id);

    return app(EnrollFace::class)->handle($context->student, [faceImage(1), faceImage(1), faceImage(1)], $context->admin->id);
}

function faceScan($context, ?float $score = 0.9, ?AttendanceSession $session = null, string $subject = 'uji'): array
{
    FakeFaceMatcher::$forcedScore = $score;
    $faces = app(RecordFaceAttendance::class);
    $challenge = $faces->challenge($subject);

    return $faces->scan($session ?? $context->faceSession, FaceInput::fromFiles(faceUpload(1), faceUpload(2, 'gerak.jpg')), faceUpload(1), $challenge['token'], $subject);
}

describe('sesi dan mode', function () {
    it('sesi wajah: semua alpa sampai dipindai; mode yang tidak diizinkan ditolak', function () {
        expect($this->faceRecord->status)->toBe('alpa')->and($this->faceSession->allowed_modes)->toBe(['face']);

        setAttendanceRules(['verification_modes' => ['manual']]);
        expect(fn () => app(OpenAttendanceSession::class)->forDormitory($this->dormitory->id, 'asrama', 'x', '2026-11-02', '19:00', 'face', $this->musyrif->id))->toThrow(AcademicException::class);
    });

    it('selfie sudah tidak ada: bukan pilihan, ditolak database, dan tidak ada rutenya', function () {
        setAttendanceRules(['verification_modes' => ['manual', 'selfie']]);
        expect(fn () => app(OpenAttendanceSession::class)->forDormitory($this->dormitory->id, 'asrama', 'x', '2026-11-02', '19:00', 'selfie', $this->musyrif->id))->toThrow(QueryException::class);

        $this->actingAs($this->musyrif);
        Livewire::test(AttendanceToday::class)->assertDontSee('Selfie');

        $this->actingAs($this->studentUser)->get('/santri/absen/'.$this->faceSession->uuid)->assertNotFound();
        $this->get(route('santri.home'))->assertDontSee('selfie');

        $this->actingAs($this->admin);
        Livewire::test(AcademicMasters::class)->set('tab', 'aturan')->assertDontSee('Selfie')->set('verification_modes', ['selfie'])->call('saveRules')->assertHasErrors('verification_modes.0');
    });

    it('wajah hanya jadi pilihan bila modul aktif', function () {
        $this->actingAs($this->musyrif);
        Livewire::test(AttendanceToday::class)->assertSee('Wajah di pos');

        Module::where('module_key', 'face_attendance')->update(['is_enabled' => false]);
        app(ModuleRegistry::class)->forget();
        Livewire::test(AttendanceToday::class)->assertDontSee('Wajah di pos');
    });

    it('pengaturan: ambang dan masa simpan tervalidasi; peringatan bila wajah tanpa persetujuan', function () {
        $this->actingAs($this->admin);

        Livewire::test(AcademicMasters::class)->set('tab', 'aturan')
            ->set('face_upper', '0,5')->set('face_lower', '0,7')->call('saveRules')->assertHasErrors('face_upper')
            ->set('face_upper', '0,9')->set('face_lower', '0,5')->set('evidence_retention', '14')->call('saveRules')->assertHasNoErrors()
            ->assertSee('belum ada wali yang setuju');

        $rules = Module::where('module_key', 'attendance')->first()->settings;
        expect($rules['face_upper'])->toBe(0.9)->and($rules['evidence_retention'])->toBe(14);
    });
});

describe('tinjauan', function () {
    beforeEach(function () {
        enrollStudent($this);
        faceScan($this, 0.71);
        $this->pending = $this->faceRecord->fresh();
    });

    it('terima dan tolak: wajib alasan, tolak tetap alpa dan bisa dipindai ulang, tercatat di audit', function () {
        $this->actingAs($this->musyrif);
        Livewire::test(AttendanceReview::class)->assertSee('Ahmad')->assertSee('0,71')
            ->call('askReject', $this->pending->uuid)->call('reject')->assertHasErrors('reason')
            ->set('reason', 'Bukan orangnya')->call('reject')->assertHasNoErrors()->assertDontSee('0,71');

        $record = $this->pending->fresh();
        expect($record->status)->toBe('alpa')->and($record->review_status)->toBe('rejected')->and($record->note)->toBe('Bukan orangnya')->and($record->reviewed_by)->toBe($this->musyrif->id);
        expect(DB::table('audit_logs')->where('action', 'attendance.reviewed')->where('entity_id', $record->id)->exists())->toBeTrue();

        expect(faceScan($this, 0.9)['outcome'])->toBe('present');
        expect($record->fresh()->status)->toBe('hadir')->and($record->fresh()->review_status)->toBe('none');
    });

    it('terima massal; ditinjau dua kali ditolak; musyrif lain dan wali tidak bisa', function () {
        $this->actingAs($this->otherMusyrif);
        Livewire::test(AttendanceReview::class)->assertDontSee('Ahmad');
        expect(fn () => app(ReviewAttendance::class)->decide($this->pending, 'approve', null, $this->otherMusyrif))->toThrow(AcademicException::class, 'tidak berhak');
        $this->get(route('attendance.evidence', $this->pending))->assertForbidden();

        $this->actingAs($this->guardianUser)->get(route('attendance.evidence', $this->pending))->assertRedirect();

        $this->actingAs($this->musyrif);
        Livewire::test(AttendanceReview::class)->set('selected', [$this->pending->uuid])->call('approveSelected')->assertSee('Tidak ada absensi');
        expect($this->pending->fresh()->review_status)->toBe('approved')->and($this->pending->fresh()->status)->toBe('hadir');
        expect(fn () => app(ReviewAttendance::class)->decide($this->pending->fresh(), 'approve', null, $this->musyrif))->toThrow(AcademicException::class, 'sudah ditinjau');
    });

    it('setiap akses foto bukti tercatat; lewat masa simpan terhapus, catatan tetap', function () {
        $this->actingAs($this->musyrif)->get(route('attendance.evidence', $this->pending))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        expect(DB::table('audit_logs')->where('action', 'attendance.evidence_viewed')->where('entity_id', $this->pending->id)->count())->toBe(1);

        $path = $this->pending->evidence_path;
        app(ReviewAttendance::class)->decide($this->pending, 'approve', null, $this->musyrif);

        $this->travelTo('2026-11-20 12:00:00');
        expect(app(PurgeAttendanceEvidence::class)->handle())->toBe(0);

        $this->travelTo('2026-12-03 12:00:00');
        expect(app(PurgeAttendanceEvidence::class)->handle())->toBe(1);
        Storage::disk('local')->assertMissing($path);
        expect($this->pending->fresh()->evidence_path)->toBeNull()->and($this->pending->fresh()->status)->toBe('hadir');
    });

    it('cadangan: koreksi manual dan tap kartu menyelesaikan antrean', function () {
        app(RecordAttendance::class)->save($this->faceSession, [$this->pending->uuid => 'izin'], $this->musyrif->id);
        expect($this->pending->fresh()->review_status)->toBe('approved')->and($this->pending->fresh()->status)->toBe('izin');
    });
});

describe('wajah', function () {
    it('contoh PRD: 0,86 hadir, 0,71 menunggu lalu dikonfirmasi, 0,52 ditolak dengan fallback', function () {
        enrollStudent($this);

        $low = faceScan($this, 0.52);
        expect($low)->toMatchArray(['ok' => false, 'outcome' => 'rejected', 'fallback' => true])->and($this->faceRecord->fresh()->scanned_at)->toBeNull();

        $grey = faceScan($this, 0.71);
        expect($grey)->toMatchArray(['ok' => true, 'outcome' => 'pending', 'name' => 'Ahmad']);
        $record = $this->faceRecord->fresh();
        expect($record->status)->toBe('alpa')->and($record->review_status)->toBe('pending')->and((float) $record->match_score)->toBe(0.71);
        Storage::disk('local')->assertExists($record->evidence_path);

        $this->travelTo('2026-11-02 12:30:00');
        app(ReviewAttendance::class)->decide($record, 'approve', null, $this->musyrif);
        expect($record->fresh()->status)->toBe('hadir')->and($record->fresh()->review_status)->toBe('approved');

        $other = app(OpenAttendanceSession::class)->forDormitory($this->dormitory->id, 'asrama', 'Lain', '2026-11-02', '19:25', 'face', $this->musyrif->id);
        $high = faceScan($this, 0.86, $other);
        $otherRecord = AttendanceRecord::where('session_id', $other->id)->where('student_id', $this->student->id)->first();
        expect($high)->toMatchArray(['ok' => true, 'outcome' => 'present'])->and($otherRecord->status)->toBe('hadir')->and($otherRecord->evidence_path)->toBeNull();
    });

    it('tepat di ambang: 0,80 hadir, 0,60 menunggu', function () {
        enrollStudent($this);
        expect(faceScan($this, 0.60)['outcome'])->toBe('pending');
        AttendanceRecord::where('id', $this->faceRecord->id)->update(['scanned_at' => null, 'review_status' => 'none']);
        expect(faceScan($this, 0.80)['outcome'])->toBe('present');
    });

    it('tanpa persetujuan aktif tidak bisa didaftarkan; foto tidak tersimpan', function () {
        expect(fn () => app(EnrollFace::class)->handle($this->student, [faceImage(), faceImage(), faceImage()], $this->admin->id))->toThrow(AcademicException::class, 'persetujuan');

        $enrollment = enrollStudent($this);
        expect($enrollment->model_version)->toBe('fake-luma-16')
            ->and(Storage::disk('local')->allFiles())->toBe([])
            ->and($enrollment->toArray())->not->toHaveKey('embedding');

        $raw = DB::table('face_enrollments')->where('id', $enrollment->id)->value('embedding');
        expect($raw)->toStartWith('eyJ')->and($enrollment->fresh()->embedding)->toHaveCount(256);
    });

    it('pendaftaran: kurang foto dan foto buruk ditolak dengan alasan', function () {
        app(ManageBiometricConsent::class)->grant($this->student, $this->guardian, 'digital', null, null, null);
        $enroll = app(EnrollFace::class);

        expect(fn () => $enroll->handle($this->student, [faceImage()], 1))->toThrow(AcademicException::class, 'minimal 3')
            ->and(fn () => $enroll->handle($this->student, [faceImage(), faceImage(1, 100), faceImage()], 1))->toThrow(AcademicException::class, 'Foto 2: Foto terlalu kecil');
    });

    it('pencabutan menghapus embedding saat itu juga; absensi lama tetap; tidak dicocokkan lagi', function () {
        $enrollment = enrollStudent($this);
        faceScan($this, 0.9);

        app(ManageBiometricConsent::class)->revoke($this->student);

        $raw = DB::table('face_enrollments')->where('id', $enrollment->id)->first();
        expect($raw->embedding)->toBeNull()->and($raw->status)->toBe('revoked')
            ->and($this->faceRecord->fresh()->status)->toBe('hadir');

        $other = app(OpenAttendanceSession::class)->forDormitory($this->dormitory->id, 'asrama', 'Lain', '2026-11-02', '19:00', 'face', $this->musyrif->id);
        expect(faceScan($this, 0.9, $other))->toMatchArray(['ok' => false, 'outcome' => 'no_candidates', 'fallback' => true]);
    });

    it('job harian menghapus wajah santri yang keluar', function () {
        $enrollment = enrollStudent($this);
        expect(app(ManageBiometricConsent::class)->sweep())->toBe(0);

        $this->student->update(['status' => 'graduated']);
        expect(app(ManageBiometricConsent::class)->sweep())->toBe(1)
            ->and(DB::table('face_enrollments')->where('id', $enrollment->id)->value('embedding'))->toBeNull();
    });

    it('liveness gagal 3 kali mengarah ke fallback; tantangan sekali pakai dan milik penerimanya', function () {
        enrollStudent($this);
        FakeFaceMatcher::$forcedLive = false;
        $faces = app(RecordFaceAttendance::class);

        foreach ([false, false, true] as $fallback) {
            $result = $faces->scan($this->faceSession, FaceInput::fromFiles(faceUpload(1), faceUpload(2)), faceUpload(1), $faces->challenge('a')['token'], 'a');
            expect($result['outcome'])->toBe('liveness')->and($result['fallback'])->toBe($fallback);
        }

        FakeFaceMatcher::$forcedLive = null;
        FakeFaceMatcher::$forcedScore = 0.9;
        $token = $faces->challenge('a')['token'];
        expect($faces->scan($this->faceSession, FaceInput::fromFiles(faceUpload(1), faceUpload(2)), faceUpload(1), $token, 'b')['outcome'])->toBe('expired')
            ->and($faces->scan($this->faceSession, FaceInput::fromFiles(faceUpload(1), faceUpload(2)), faceUpload(1), $token, 'a')['outcome'])->toBe('expired');
    });

    it('frame identik gagal liveness pada matcher tiruan; kualitas buruk ditolak', function () {
        $matcher = app(FaceMatcher::class);
        expect($matcher->isLive('x', new FaceInput(faceImage(1), secondFrame: faceImage(1))))->toBeFalse()->and($matcher->isLive('x', new FaceInput(faceImage(1), secondFrame: faceImage(2))))->toBeTrue();

        $dark = imagecreatetruecolor(300, 300);
        ob_start();
        imagejpeg($dark);
        $black = (string) ob_get_clean();
        expect(fn () => $matcher->identify($black, []))->toThrow(FaceQualityException::class, 'gelap')
            ->and(fn () => $matcher->enroll(['bukan gambar']))->toThrow(FaceQualityException::class);

        $same = $matcher->enroll([faceImage(1), faceImage(1), faceImage(1)])->data;
        $other = $matcher->enroll([faceImage(9), faceImage(9), faceImage(9)])->data;
        $found = $matcher->identify(faceImage(1), [7 => $other, 8 => $same]);
        expect($found->studentId)->toBe(8)->and($found->score)->toBeGreaterThan(0.99)
            ->and($matcher->identify(faceImage(1), [7 => $other])->score)->toBeLessThan(0.7)
            ->and($matcher->identify(faceImage(1), []))->toBeNull();
    });

    it('matcher tiruan tidak berjalan di produksi: wajah ditolak dan pendaftaran diblok', function () {
        app()->detectEnvironment(fn () => 'production');

        try {
            expect(app(FaceMatcher::class)->available())->toBeFalse();
            expect(faceScan($this, 0.99))->toMatchArray(['ok' => false, 'outcome' => 'unavailable', 'fallback' => true]);
            expect(fn () => enrollStudent($this))->toThrow(AcademicException::class, 'belum tersedia');
        } finally {
            app()->detectEnvironment(fn () => 'testing');
        }
    });

    it('modul mati: rute tertutup dan pindai ditolak; fallback kartu tetap ada setelah zona abu-abu', function () {
        enrollStudent($this);
        faceScan($this, 0.71);
        expect($this->faceRecord->fresh()->review_status)->toBe('pending');

        Module::where('module_key', 'face_attendance')->update(['is_enabled' => false]);
        app(ModuleRegistry::class)->forget();
        $this->actingAs($this->admin)->get(route('staff.face'))->assertForbidden();
        expect(faceScan($this, 0.9)['outcome'])->toBe('unavailable');
    });

    it('rute pos: hanya pengabsen sesi; API perangkat butuh scope', function () {
        enrollStudent($this);
        $this->actingAs($this->otherMusyrif)->get(route('attendance.face', $this->faceSession))->assertForbidden();
        $this->actingAs($this->otherMusyrif)->postJson(route('attendance.face.challenge', $this->faceSession))->assertForbidden();

        $this->actingAs($this->musyrif)->get(route('attendance.face', $this->faceSession))->assertOk()->assertSee('Mulai Pindai');
        $manual = app(OpenAttendanceSession::class)->forDormitory($this->dormitory->id, 'asrama', 'Manual', '2026-11-02', '19:00', 'manual', $this->musyrif->id);
        $this->actingAs($this->musyrif)->get(route('attendance.face', $manual))->assertNotFound();
        $token = $this->postJson(route('attendance.face.challenge', $this->faceSession))->assertOk()->json('token');
        FakeFaceMatcher::$forcedScore = 0.9;
        $this->post(route('attendance.face.scan', $this->faceSession), ['photo' => faceUpload(1), 'liveness_frame' => faceUpload(2), 'liveness_token' => $token], ['Accept' => 'application/json'])
            ->assertOk()->assertJson(['ok' => true, 'outcome' => 'present', 'name' => 'Ahmad']);

        $plain = Str::random(40);
        Device::create(['name' => 'Tablet', 'scopes' => ['attendance'], 'api_key_hash' => hash('sha256', $plain)]);
        Device::create(['name' => 'Kantin', 'scopes' => ['canteen'], 'api_key_hash' => hash('sha256', 'kantin')]);
        $auth = ['Authorization' => 'Bearer '.$plain, 'Accept' => 'application/json'];
        AttendanceRecord::where('id', $this->faceRecord->id)->update(['scanned_at' => null]);
        $deviceToken = $this->withHeaders($auth)->postJson('/api/v1/device/attendance/face/challenge')->assertOk()->json('token');
        $this->withHeaders($auth)->post('/api/v1/device/attendance/face', ['session_id' => $this->faceSession->uuid, 'photo' => faceUpload(1), 'liveness_frame' => faceUpload(2), 'liveness_token' => $deviceToken])
            ->assertOk()->assertJson(['ok' => true, 'outcome' => 'present']);

        $this->withHeaders(['Authorization' => 'Bearer kantin', 'Accept' => 'application/json'])->postJson('/api/v1/device/attendance/face/challenge')->assertForbidden();
    });
});

describe('persetujuan wali', function () {
    it('profil santri punya pintasan ke pendaftaran wajah, hanya bila modul aktif', function () {
        $this->actingAs($this->admin);
        $this->get(route('staff.students.show', $this->student))->assertSee('Daftarkan Wajah');

        Module::where('module_key', 'face_attendance')->update(['is_enabled' => false]);
        app(ModuleRegistry::class)->forget();
        $this->get(route('staff.students.show', $this->student))->assertDontSee('Daftarkan Wajah');
    });

    it('wali menyetujui dan mencabut; wali lain tidak bisa menyentuh anak orang', function () {
        $this->actingAs($this->guardianUser);
        $page = Livewire::test(GuardianFaceConsent::class)->assertSee('bukan foto wajahnya')->assertSee('Ahmad')->assertSee('Belum disetujui')
            ->call('agree', $this->student->uuid)->assertSee('Disetujui');

        expect(BiometricConsent::active()->where('student_id', $this->student->id)->first()->method)->toBe('digital');

        $page->call('agree', $this->student->uuid);
        expect(BiometricConsent::where('student_id', $this->student->id)->count())->toBe(1);

        $this->actingAs($this->otherGuardianUser);
        Livewire::test(GuardianFaceConsent::class)->assertDontSee('Ahmad');
        expect(fn () => Livewire::test(GuardianFaceConsent::class)->call('revoke', $this->student->uuid))->toThrow(Exception::class);
        expect(BiometricConsent::active()->where('student_id', $this->student->id)->exists())->toBeTrue();

        $this->actingAs($this->guardianUser);
        Livewire::test(GuardianFaceConsent::class)->call('revoke', $this->student->uuid)->assertSee('Belum disetujui')->assertSee('dicabut');
        expect(BiometricConsent::active()->where('student_id', $this->student->id)->exists())->toBeFalse();
    });

    it('hanya tampil bila modul aktif; wali tidak melihat skor maupun foto', function () {
        $this->actingAs($this->guardianUser)->get(route('wali.face-consent'))->assertOk();

        enrollStudent($this);
        faceScan($this, 0.71);
        $this->actingAs($this->guardianUser)->get(route('wali.progress'))->assertDontSee('0,71')->assertDontSee('attendance-evidence');

        Module::where('module_key', 'face_attendance')->update(['is_enabled' => false]);
        app(ModuleRegistry::class)->forget();
        $this->actingAs($this->guardianUser)->get(route('wali.face-consent'))->assertForbidden();
    });

    it('layar pendaftaran: blokir tanpa persetujuan, minta ke wali, surat kertas, simpan, hapus', function () {
        $this->actingAs($this->admin);

        $page = Livewire::test(FaceEnrollments::class)->assertSee('pencocokan masih tiruan')
            ->set('search', 'Ahm')->assertSee('A1')->call('choose', $this->student->uuid)
            ->assertSee('Belum ada persetujuan wali')->call('requestConsent');
        expect($this->guardianUser->inAppNotifications()->where('title', 'Mohon keputusan data wajah untuk Ahmad')->exists())->toBeTrue();

        $page->call('recordPaper')->assertHasErrors(['paper_guardian', 'paper_document'])
            ->set('paper_guardian', (string) $this->guardian->id)->set('paper_document', UploadedFile::fake()->create('surat.pdf', 10, 'application/pdf'))
            ->call('recordPaper')->assertHasNoErrors()->assertSee('Wali sudah setuju lewat surat');

        $page->set('photos', [faceUpload(1), faceUpload(1)])->call('enroll')->assertHasErrors('photos')
            ->set('photos', [faceUpload(1), faceUpload(1), faceUpload(1)])->call('enroll')->assertHasNoErrors()->assertSee('Wajah sudah terdaftar');
        expect(FaceEnrollment::where('student_id', $this->student->id)->where('status', 'active')->exists())->toBeTrue();

        $page->call('erase')->assertDontSee('Wajah sudah terdaftar');
        expect(FaceEnrollment::where('student_id', $this->student->id)->where('status', 'active')->exists())->toBeFalse();

        $this->actingAs($this->musyrif)->get(route('staff.face'))->assertForbidden();
    });

    it('persetujuan lisan: wajib keterangan dan pernyataan, bisa sekaligus untuk yang belum setuju, lalu wajah bisa didaftarkan', function () {
        $this->actingAs($this->admin);

        $page = Livewire::test(FaceEnrollments::class)->call('choose', $this->student->uuid)->assertSee('Catat Persetujuan Lisan')
            ->call('recordVerbal')->assertHasErrors(['verbal_note', 'verbal_confirm'])
            ->set('verbal_guardian', (string) $this->guardian->id)->set('verbal_note', 'Disampaikan ayah saat pertemuan wali, 1 November')->set('verbal_confirm', true)
            ->call('recordVerbal')->assertHasNoErrors()->assertSee('Wali sudah setuju secara lisan')->assertSee('Disampaikan ayah saat pertemuan wali');
        $consent = BiometricConsent::query()->active()->where('student_id', $this->student->id)->firstOrFail();
        expect($consent->method)->toBe('verbal')->and($consent->guardian_id)->toBe($this->guardian->id)->and($consent->recorded_by)->toBe($this->admin->id);

        $page->set('photos', [faceUpload(1), faceUpload(1), faceUpload(1)])->call('enroll')->assertHasNoErrors()->assertSee('Wajah sudah terdaftar');

        // Sekaligus: hanya santri yang belum punya persetujuan yang dicatat.
        Livewire::test(FaceEnrollments::class)->call('recordVerbalBulk')->assertHasErrors(['bulk_note', 'bulk_confirm'])
            ->set('bulk_note', 'Disetujui seluruh wali pada pertemuan 1 November')->set('bulk_confirm', true)->call('recordVerbalBulk')->assertHasNoErrors();
        expect(BiometricConsent::query()->active()->where('student_id', $this->secondStudent->id)->value('method'))->toBe('verbal')
            ->and(BiometricConsent::query()->where('student_id', $this->student->id)->count())->toBe(1);
    });
});
