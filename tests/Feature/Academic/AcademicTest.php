<?php

use App\Modules\Academic\Actions\CopySchedule;
use App\Modules\Academic\Actions\ManageReportCards;
use App\Modules\Academic\Actions\OpenAttendanceSession;
use App\Modules\Academic\Actions\RecordAttendance;
use App\Modules\Academic\Actions\SaveGrades;
use App\Modules\Academic\Actions\SaveSchedule;
use App\Modules\Academic\Livewire\AcademicMasters;
use App\Modules\Academic\Livewire\AttendanceRecap;
use App\Modules\Academic\Livewire\AttendanceSheet;
use App\Modules\Academic\Livewire\AttendanceToday;
use App\Modules\Academic\Livewire\GradeBook;
use App\Modules\Academic\Livewire\ReportCards;
use App\Modules\Academic\Livewire\ScheduleBuilder;
use App\Modules\Academic\Livewire\StudentAcademics;
use App\Modules\Academic\Models\Assessment;
use App\Modules\Academic\Models\AttendanceRecord;
use App\Modules\Academic\Models\AttendanceSession;
use App\Modules\Academic\Models\Grade;
use App\Modules\Academic\Models\GradeComponent;
use App\Modules\Academic\Models\ReportCard;
use App\Modules\Academic\Models\Schedule;
use App\Modules\Academic\Models\Subject;
use App\Modules\Academic\Models\TeachingAssignment;
use App\Modules\Academic\Support\AcademicException;
use App\Modules\Academic\Support\AcademicSettings;
use App\Modules\Academic\Support\AttendanceStats;
use App\Modules\Core\Actions\StartSession;
use App\Modules\Core\Models\AcademicYear;
use App\Modules\Core\Models\Classroom;
use App\Modules\Core\Models\Dormitory;
use App\Modules\Core\Models\Guardian;
use App\Modules\Core\Models\Module;
use App\Modules\Core\Models\Student;
use App\Modules\Core\Models\Teacher;
use App\Modules\Core\Models\Term;
use App\Modules\Core\Models\User;
use App\Modules\Core\Support\ModuleRegistry;
use App\Modules\Notification\Models\NotificationLog;
use App\Modules\Wallet\Actions\IssueCard;
use App\Modules\Wallet\Models\CardScan;
use App\Modules\Wallet\Models\Device;
use App\Modules\Wallet\Support\QrToken;
use App\Support\Pdf\PdfRenderer;
use Database\Seeders\ModuleSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    (new ModuleSeeder)->run();
    (new RoleSeeder)->run();
    Queue::fake();
    // Senin 2 November 2026, 09.00 WIB.
    $this->travelTo('2026-11-02 02:00:00');

    $this->year = AcademicYear::create(['name' => '2026/2027', 'start_date' => '2026-07-01', 'end_date' => '2027-06-30', 'is_active' => true]);
    $this->term = Term::create(['academic_year_id' => $this->year->id, 'name' => 'Ganjil', 'start_date' => '2026-07-01', 'end_date' => '2026-12-31', 'is_active' => true]);

    $this->akademik = tap(User::create(['name' => 'Akademik', 'email' => 'ak@uji.test', 'type' => 'staff']))->assignRole('admin_akademik');
    $this->ustadzUser = tap(User::create(['name' => 'Ustadz Ali', 'email' => 'u@uji.test', 'type' => 'staff']))->assignRole('ustadz');
    $this->teacher = Teacher::create(['user_id' => $this->ustadzUser->id, 'name' => 'Ustadz Ali']);
    $this->otherUser = tap(User::create(['name' => 'Ustadz Lain', 'email' => 'u2@uji.test', 'type' => 'staff']))->assignRole('ustadz');
    $this->otherTeacher = Teacher::create(['user_id' => $this->otherUser->id, 'name' => 'Ustadz Lain']);
    $this->musyrif = tap(User::create(['name' => 'Musyrif', 'email' => 'm@uji.test', 'type' => 'staff']))->assignRole('musyrif');

    $this->classroom = Classroom::create(['name' => '7A']);
    $this->otherClass = Classroom::create(['name' => '8A']);
    $this->dormitory = Dormitory::create(['name' => 'Asrama 1', 'gender' => 'L', 'musyrif_user_id' => $this->musyrif->id]);
    $this->ahmad = Student::create(['nis' => 'A1', 'name' => 'Ahmad', 'gender' => 'L', 'classroom_id' => $this->classroom->id, 'dormitory_id' => $this->dormitory->id]);
    $this->budi = Student::create(['nis' => 'B1', 'name' => 'Budi', 'gender' => 'L', 'classroom_id' => $this->classroom->id, 'dormitory_id' => $this->dormitory->id]);
    $this->lain = Student::create(['nis' => 'C1', 'name' => 'Candra', 'gender' => 'L', 'classroom_id' => $this->otherClass->id]);

    $this->subject = Subject::create(['code' => 'FIQ', 'name' => 'Fiqih', 'type' => 'diniyah', 'kkm' => 75]);
    TeachingAssignment::create(['teacher_id' => $this->teacher->id, 'subject_id' => $this->subject->id, 'classroom_id' => $this->classroom->id, 'academic_year_id' => $this->year->id]);
    $this->schedule = Schedule::create(['term_id' => $this->term->id, 'classroom_id' => $this->classroom->id, 'subject_id' => $this->subject->id, 'teacher_id' => $this->teacher->id, 'day_of_week' => 1, 'start_time' => '09:00', 'end_time' => '10:00', 'room' => 'R1']);

    $this->harian = GradeComponent::create(['name' => 'Harian', 'weight' => 30]);
    $this->uts = GradeComponent::create(['name' => 'UTS', 'weight' => 30]);
    $this->uas = GradeComponent::create(['name' => 'UAS', 'weight' => 40]);
});

function scheduleData($context, array $overrides = []): array
{
    return array_merge(['term_id' => $context->term->id, 'classroom_id' => $context->otherClass->id, 'subject_id' => $context->subject->id, 'teacher_id' => $context->otherTeacher->id, 'day_of_week' => 1, 'start_time' => '09:30', 'end_time' => '10:30', 'room' => 'R2', 'activity_type' => 'kbm'], $overrides);
}

function assess($context, GradeComponent $component, array $scores, int $max = 100): Assessment
{
    $assessment = Assessment::create(['subject_id' => $context->subject->id, 'classroom_id' => $context->classroom->id, 'term_id' => $context->term->id, 'component_id' => $component->id, 'title' => $component->name, 'max_score' => $max]);
    app(SaveGrades::class)->handle($assessment, $scores);

    return $assessment;
}

describe('jadwal', function () {
    it('bentrok ustadz, kelas, dan ruang ditolak; bersebelahan boleh', function () {
        $save = app(SaveSchedule::class);

        expect(fn () => $save->handle(scheduleData($this, ['teacher_id' => $this->teacher->id])))->toThrow(AcademicException::class, 'Ustadz Ali');
        expect(fn () => $save->handle(scheduleData($this, ['classroom_id' => $this->classroom->id])))->toThrow(AcademicException::class, '7A');
        expect(fn () => $save->handle(scheduleData($this, ['room' => 'r1'])))->toThrow(AcademicException::class, 'ruang R1');

        expect($save->handle(scheduleData($this, ['teacher_id' => $this->teacher->id, 'start_time' => '10:00', 'end_time' => '11:00', 'room' => 'R1']))->exists)->toBeTrue();
        expect($save->handle(scheduleData($this, ['day_of_week' => 2, 'teacher_id' => $this->teacher->id]))->exists)->toBeTrue();
    });

    it('jam selesai sebelum mulai ditolak di database dan layar; layar menampilkan bentrok', function () {
        expect(fn () => DB::transaction(fn () => Schedule::create(scheduleData($this, ['start_time' => '10:00', 'end_time' => '09:00']))))->toThrow(QueryException::class);

        $this->actingAs($this->akademik);

        Livewire::test(ScheduleBuilder::class)
            ->call('create', 1)
            ->set('form_classroom', (string) $this->classroom->id)->set('subject_id', (string) $this->subject->id)->set('start_time', '10:00')->set('end_time', '09:00')
            ->call('save')->assertHasErrors('end_time')
            ->set('start_time', '09:15')->set('end_time', '09:45')
            ->call('save')->assertHasErrors('conflict')->assertSee('Bentrok');
    });

    it('term tidak aktif diberi peringatan di layar jadwal', function () {
        $this->actingAs($this->akademik);
        Livewire::test(ScheduleBuilder::class)->assertDontSee('Periode ini belum aktif');

        $this->term->update(['is_active' => false]);
        Livewire::test(ScheduleBuilder::class)->assertSee('Periode ini belum aktif');
    });

    it('salin jadwal hanya ke term kosong', function () {
        $next = Term::create(['academic_year_id' => $this->year->id, 'name' => 'Genap', 'start_date' => '2027-01-01', 'end_date' => '2027-06-30']);

        expect(app(CopySchedule::class)->handle($this->term->id, $next->id))->toBe(1)
            ->and(fn () => app(CopySchedule::class)->handle($this->term->id, $next->id))->toThrow(AcademicException::class, 'sudah punya');
    });

    it('hanya pengelola akademik yang membuka master dan jadwal', function () {
        foreach (['staff.schedules.index', 'staff.subjects.index'] as $route) {
            $this->actingAs($this->akademik)->get(route($route))->assertOk();
            $this->actingAs($this->ustadzUser)->get(route($route))->assertForbidden();
        }
    });

    it('semua tab master bisa dibuka dan total bobot tampil', function () {
        $this->actingAs($this->akademik);

        $page = Livewire::test(AcademicMasters::class);
        $page->call('selectTab', 'penugasan')->assertSee('Fiqih');
        $page->call('selectTab', 'komponen')->assertSee('Harian')->assertSee('Total bobot aktif 100%');
        $page->call('selectTab', 'aturan')->assertSee('Toleransi');
    });

    it('master: mapel, penugasan satu ustadz per mapel-kelas, bobot, aturan absensi', function () {
        $this->actingAs($this->akademik);

        $component = Livewire::test(AcademicMasters::class)
            ->call('createSubject')->set('code', 'FIQ')->set('name', 'X')->call('saveSubject')->assertHasErrors('code')
            ->set('code', 'NHW')->set('name', 'Nahwu')->set('type', 'diniyah')->call('saveSubject')->assertHasNoErrors();

        $nahwu = Subject::where('code', 'NHW')->first();
        $component->set('teacher_id', (string) $this->teacher->id)->set('subject_id', (string) $nahwu->id)->set('classroom_id', (string) $this->classroom->id)->call('saveAssignment')->assertHasNoErrors();
        $component->set('teacher_id', (string) $this->otherTeacher->id)->set('subject_id', (string) $nahwu->id)->set('classroom_id', (string) $this->classroom->id)->call('saveAssignment');
        expect(TeachingAssignment::where('subject_id', $nahwu->id)->count())->toBe(1)->and(TeachingAssignment::where('subject_id', $nahwu->id)->value('teacher_id'))->toBe($this->otherTeacher->id);

        $component->set('late_tolerance', '5')->set('verification_modes', ['manual'])->call('saveRules')->assertHasNoErrors();
        expect(AcademicSettings::lateTolerance())->toBe(5)->and(AcademicSettings::verificationModes())->toBe(['manual']);
    });
});

describe('absensi', function () {
    it('sesi baru: semua santri aktif kelasnya hadir, buka dua kali sesi yang sama', function () {
        $this->budi->update(['status' => 'inactive']);
        $open = app(OpenAttendanceSession::class);

        $session = $open->fromSchedule($this->schedule, '2026-11-02', 'manual', $this->ustadzUser->id);
        $again = $open->fromSchedule($this->schedule, '2026-11-02', 'manual', $this->ustadzUser->id);

        expect($again->id)->toBe($session->id)
            ->and($session->records()->pluck('status')->all())->toBe(['hadir'])
            ->and($session->records()->value('student_id'))->toBe($this->ahmad->id);
    });

    it('ustadz: hanya mengubah yang tidak hadir, ringkasan benar, alpa mengabari wali sekali', function () {
        Module::where('module_key', 'notification_wa')->update(['is_enabled' => true]);
        app(ModuleRegistry::class)->forget();
        Guardian::create(['name' => 'Wali', 'phone_wa' => '62812'])->students()->attach($this->ahmad->id, ['relation' => 'ayah']);
        $this->actingAs($this->ustadzUser);

        $today = Livewire::test(AttendanceToday::class)->assertSee('Fiqih')->assertSee('Mulai Absensi')->call('openSchedule', $this->schedule->uuid);
        $session = AttendanceSession::first();
        $today->assertRedirect(route('ustadz.attendance.session', $session));

        $record = $session->records()->where('student_id', $this->ahmad->id)->first();

        Livewire::test(AttendanceSheet::class, ['session' => $session])
            ->assertSee('Ahmad')->assertSee('Budi')
            ->set("statuses.{$record->uuid}", 'alpa')
            ->call('save')->assertDispatched('attendance-saved');

        expect($record->fresh()->status)->toBe('alpa')->and($session->fresh()->isClosed())->toBeTrue()
            ->and(NotificationLog::where('event_key', 'attendance_alert')->first()->payload['message'])->toContain('Ahmad')->toContain('Alpa')
            ->and($session->records()->where('status', 'hadir')->count())->toBe(1);

        Livewire::test(AttendanceSheet::class, ['session' => $session->fresh()])->call('save');
        expect(NotificationLog::where('event_key', 'attendance_alert')->count())->toBe(1);
    });

    it('ustadz lain dan musyrif tidak bisa membuka sesi kelas ini', function () {
        $session = app(OpenAttendanceSession::class)->fromSchedule($this->schedule, '2026-11-02', 'manual', $this->ustadzUser->id);

        $this->actingAs($this->otherUser)->get(route('ustadz.attendance.session', $session))->assertForbidden();
        $this->actingAs($this->musyrif)->get(route('ustadz.attendance.session', $session))->assertForbidden();
        $this->actingAs($this->otherUser);
        expect(fn () => Livewire::test(AttendanceToday::class)->call('openSchedule', $this->schedule->uuid))->not->toThrow(Exception::class);
        expect(AttendanceSession::where('taken_by', $this->otherUser->id)->count())->toBe(0);
    });

    it('admin membuka absen tanpa jadwal untuk semua santri atau satu kelas; semua cara absen boleh, jam otomatis', function () {
        $this->actingAs($this->akademik);
        $page = Livewire::test(AttendanceToday::class)->assertSee('Semua santri aktif')->assertSet('dorm_title', 'Sholat Berjamaah')
            ->set('dorm_kind', 'asrama')->assertSet('dorm_title', 'Apel Asrama');
        $page->set('target', 'all')->call('openGroup')->assertHasNoErrors()->assertRedirect();

        $session = AttendanceSession::latest('id')->firstOrFail();
        $active = Student::where('status', 'active')->count();
        expect($session->records()->count())->toBe($active)
            ->and($session->allowed_modes)->toEqualCanonicalizing(array_keys(AcademicSettings::modeOptions()))
            ->and($session->start_time)->not->toBeNull()
            ->and($session->records()->where('status', 'alpa')->count())->toBe($active);
        $this->get(route('staff.attendance.session', $session))->assertOk()->assertSee('Tap kartu atau pindai QR');
        // Tanpa jadwal tidak ada telat; kamera wajah belum aktif diberi petunjuk.
        Livewire::test(AttendanceSheet::class, ['session' => $session])->assertDontSee('aria-label="Terlambat"', false)
            ->assertSee('Absen wajah belum aktif')->assertSee('dibuka');
    });

    it('musyrif membuka sesi asrama sendiri, bukan asrama lain', function () {
        $other = Dormitory::create(['name' => 'Asrama 2', 'gender' => 'L']);
        $this->actingAs($this->musyrif);

        // Musyrif hanya melihat asramanya sendiri, dan karena hanya satu, langsung terpilih.
        Livewire::test(AttendanceToday::class)->assertSet('target', 'd:'.$this->dormitory->id)->assertDontSee('Semua santri aktif')
            ->set('target', 'd:'.$other->id)->call('openGroup')->assertHasErrors('target')
            ->set('target', 'd:'.$this->dormitory->id)->set('dorm_kind', 'sholat')->call('openGroup')->assertHasNoErrors();

        $session = AttendanceSession::where('kind', 'sholat')->first();
        expect($session->records()->count())->toBe(2);
        $this->get(route('musyrif.attendance.session', $session))->assertOk();

        $otherMusyrif = tap(User::create(['name' => 'Musyrif Lain', 'email' => 'm2@uji.test', 'type' => 'staff']))->assignRole('musyrif');
        $this->actingAs($otherMusyrif)->get(route('musyrif.attendance.session', $session))->assertForbidden();
        $this->actingAs($this->otherUser)->get(route('musyrif.attendance.session', $session))->assertForbidden();
    });

    it('kartu: belum pindai alpa; sebelum toleransi hadir, lewat toleransi terlambat; pindai ganda diabaikan', function () {
        app(IssueCard::class)->handle($this->ahmad, 'AA11');
        app(IssueCard::class)->handle($this->budi, 'BB22');
        $card = app(IssueCard::class)->handle($this->lain, 'CC33');
        $session = app(OpenAttendanceSession::class)->fromSchedule($this->schedule, '2026-11-02', 'card', $this->ustadzUser->id);
        $record = app(RecordAttendance::class);

        expect($session->records()->pluck('status')->unique()->all())->toBe(['alpa']);

        $this->travelTo('2026-11-02 02:10:00');
        expect($record->scan($session, 'AA11')['status'])->toBe('hadir');

        $this->travelTo('2026-11-02 02:10:01');
        expect($record->scan($session, 'BB22')['status'])->toBe('terlambat');

        $first = AttendanceRecord::where('student_id', $this->ahmad->id)->value('scanned_at');
        $this->travelTo('2026-11-02 02:40:00');
        expect($record->scan($session, 'AA11')['message'])->toBe('Sudah tercatat.')
            ->and(AttendanceRecord::where('student_id', $this->ahmad->id)->value('status'))->toBe('hadir')
            ->and(AttendanceRecord::where('student_id', $this->ahmad->id)->value('scanned_at'))->toEqual($first);

        expect($record->scan($session, 'CC33')['message'])->toContain('bukan peserta')
            ->and($record->scan($session, 'TIDAKADA')['ok'])->toBeFalse()
            ->and(CardScan::where('scope', 'attendance')->where('result', 'ok')->count())->toBe(2);
    });

    it('QR dinamis dipakai untuk absen; kartu diblokir ditolak; sesi tutup ditolak', function () {
        $card = app(IssueCard::class)->handle($this->ahmad, 'AA11');
        $session = app(OpenAttendanceSession::class)->fromSchedule($this->schedule, '2026-11-02', 'qr', $this->ustadzUser->id);
        $record = app(RecordAttendance::class);

        expect($record->scan($session, QrToken::dynamic($card))['status'])->toBe('hadir')
            ->and(AttendanceRecord::where('student_id', $this->ahmad->id)->value('method'))->toBe('qr');

        $record->save($session, [], $this->ustadzUser->id);
        expect($record->scan($session->fresh(), 'AA11')['message'])->toContain('sudah ditutup');
    });

    it('cara absen di luar yang diizinkan ditolak', function () {
        $module = Module::where('module_key', 'attendance')->first();
        $module->update(['settings' => ['verification_modes' => ['manual']]]);

        expect(fn () => app(OpenAttendanceSession::class)->fromSchedule($this->schedule, '2026-11-02', 'card', $this->ustadzUser->id))->toThrow(AcademicException::class, 'tidak diizinkan');
    });

    it('API perangkat: pindai absensi lewat scope yang benar', function () {
        Module::where('module_key', 'card')->update(['is_enabled' => true]);
        app(ModuleRegistry::class)->forget();
        app(IssueCard::class)->handle($this->ahmad, 'AA11');
        $session = app(OpenAttendanceSession::class)->fromSchedule($this->schedule, '2026-11-02', 'card', $this->ustadzUser->id);
        Device::create(['name' => 'Reader', 'scopes' => ['attendance'], 'api_key_hash' => hash('sha256', 'kunci')]);
        Device::create(['name' => 'Kantin', 'scopes' => ['canteen'], 'api_key_hash' => hash('sha256', 'kantin')]);

        $this->withToken('kantin')->postJson('/api/v1/device/attendance/scan', ['session_id' => $session->uuid, 'card_uid' => 'AA11'])->assertForbidden();
        $this->withToken('kunci')->postJson('/api/v1/device/attendance/scan', ['session_id' => $session->uuid, 'card_uid' => 'AA11'])->assertOk()->assertJson(['ok' => true, 'status' => 'hadir', 'name' => 'Ahmad']);
    });

    it('rekap per santri: persen dari sesi tersimpan, terlambat dihitung hadir, ekspor jalan', function () {
        $session = app(OpenAttendanceSession::class)->fromSchedule($this->schedule, '2026-11-02', 'manual', $this->ustadzUser->id);
        $ahmad = $session->records()->where('student_id', $this->ahmad->id)->first();
        $budi = $session->records()->where('student_id', $this->budi->id)->first();
        app(RecordAttendance::class)->save($session, [$ahmad->uuid => 'terlambat', $budi->uuid => 'alpa'], $this->ustadzUser->id);
        app(OpenAttendanceSession::class)->forDormitory($this->dormitory->id, 'sholat', 'Belum disimpan', '2026-11-02', '04:30', 'manual', $this->musyrif->id);

        $rows = AttendanceStats::perStudent($this->classroom->id, null, '2026-11-01', '2026-11-30');
        expect($rows->firstWhere('student.id', $this->ahmad->id)['percent'])->toBe(100)
            ->and($rows->firstWhere('student.id', $this->budi->id)['percent'])->toBe(0)
            ->and($rows->firstWhere('student.id', $this->budi->id)['total'])->toBe(1);

        $this->app->instance(PdfRenderer::class, new class implements PdfRenderer
        {
            public function render(string $html, string $format = 'A4'): string
            {
                return '%PDF'.$html;
            }
        });

        $this->actingAs($this->akademik);
        Livewire::test(AttendanceRecap::class)->assertSee('Ahmad')->assertSee('100%');
        $this->get(route('staff.attendance.export', ['kelas' => $this->classroom->id]))->assertOk();
        $this->get(route('staff.attendance.pdf', ['kelas' => $this->classroom->id]))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->get(route('staff.attendance.recap', ['dari' => 'rusak']))->assertOk();
        $this->actingAs($this->otherUser)->get(route('staff.attendance.recap'))->assertForbidden();
    });
});

describe('nilai dan rapor', function () {
    it('contoh PRD: harian 80 (30%), UTS 70 (30%), UAS 90 (40%) menghasilkan 81', function () {
        assess($this, $this->harian, [$this->ahmad->id => '70']);
        assess($this, $this->harian, [$this->ahmad->id => '90']);
        assess($this, $this->uts, [$this->ahmad->id => '70']);
        assess($this, $this->uas, [$this->ahmad->id => '90']);

        expect(app(ManageReportCards::class)->finalScores($this->ahmad, $this->term->id, $this->classroom->id)[$this->subject->id])->toBe(81.0);
    });

    it('nilai dinormalkan ke skala 100; bobot hanya dari komponen yang ada penilaiannya', function () {
        assess($this, $this->harian, [$this->ahmad->id => '40'], 50);

        expect(app(ManageReportCards::class)->finalScores($this->ahmad, $this->term->id, $this->classroom->id)[$this->subject->id])->toBe(80.0);
    });

    it('nilai di luar rentang ditolak; kosong menghapus; koma diterima', function () {
        $assessment = assess($this, $this->harian, [$this->ahmad->id => '80,5']);
        expect(Grade::first()->score)->toBe(80.5);

        expect(fn () => app(SaveGrades::class)->handle($assessment, [$this->ahmad->id => '101']))->toThrow(AcademicException::class, 'antara 0 dan 100');
        expect(fn () => app(SaveGrades::class)->handle($assessment, [$this->ahmad->id => 'abc']))->toThrow(AcademicException::class);

        app(SaveGrades::class)->handle($assessment, [$this->ahmad->id => '']);
        expect(Grade::count())->toBe(0);
    });

    it('predikat sesuai batas PRD', function () {
        expect(AcademicSettings::predicate(90))->toBe('A')->and(AcademicSettings::predicate(89.99))->toBe('B')->and(AcademicSettings::predicate(70))->toBe('C')->and(AcademicSettings::predicate(60))->toBe('D')->and(AcademicSettings::predicate(59))->toBe('E');
    });

    it('buku nilai: ustadz hanya mapel-kelas yang diajar; santri kelas lain tidak bisa disisipkan', function () {
        $foreignSubject = Subject::create(['code' => 'X', 'name' => 'Rahasia']);
        TeachingAssignment::create(['teacher_id' => $this->otherTeacher->id, 'subject_id' => $foreignSubject->id, 'classroom_id' => $this->otherClass->id, 'academic_year_id' => $this->year->id]);
        $this->actingAs($this->ustadzUser);

        $component = Livewire::test(GradeBook::class)
            ->assertSee('Fiqih')->assertDontSee('Rahasia')
            ->call('createAssessment')
            ->set('title', 'Ulangan 1')->set('component_id', (string) $this->harian->id)
            ->call('saveAssessment')->assertHasNoErrors();

        $assessment = Assessment::first();
        expect($assessment->subject_id)->toBe($this->subject->id);

        $component->set("scores.{$this->ahmad->id}", '101')->call('saveScores')->assertHasErrors("scores.{$this->ahmad->id}");
        $component->set("scores.{$this->ahmad->id}", '85')->set("scores.{$this->lain->id}", '100')->call('saveScores')->assertHasNoErrors();

        expect(Grade::where('student_id', $this->ahmad->id)->value('score'))->toBe(85.0)->and(Grade::where('student_id', $this->lain->id)->exists())->toBeFalse();

        $this->set = null;
        Livewire::test(GradeBook::class, ['assignmentKey' => $foreignSubject->id.':'.$this->otherClass->id])->assertSet('assignmentKey', $this->subject->id.':'.$this->classroom->id);
    });

    it('rapor: bobot harus 100; draf, predikat, KKM; terbit mengunci; ubah butuh alasan dan tercatat', function () {
        Module::where('module_key', 'notification_wa')->update(['is_enabled' => true]);
        app(ModuleRegistry::class)->forget();
        Guardian::create(['name' => 'Wali', 'phone_wa' => '62812'])->students()->attach($this->ahmad->id, ['relation' => 'ayah']);
        $assessment = assess($this, $this->uas, [$this->ahmad->id => '70', $this->budi->id => '95']);
        $cards = app(ManageReportCards::class);

        $this->uas->update(['weight' => 50]);
        expect(fn () => $cards->generate($this->term->id, $this->classroom->id))->toThrow(AcademicException::class, '110%');
        $this->uas->update(['weight' => 40]);

        expect($cards->generate($this->term->id, $this->classroom->id))->toBe(2);
        $card = ReportCard::where('student_id', $this->ahmad->id)->first();
        $item = $card->items()->first();
        expect($item->final_score)->toBe(70.0)->and($item->predicate)->toBe('C')->and($item->remark)->toBe('Di bawah KKM')->and($card->status)->toBe('draft');

        expect($cards->publish(ReportCard::all()))->toBe(2)
            ->and($card->fresh()->status)->toBe('published')
            ->and(NotificationLog::where('event_key', 'report_published')->first()->payload['message'])->toContain('Ahmad');

        expect(fn () => app(SaveGrades::class)->handle($assessment, [$this->ahmad->id => '99']))->toThrow(AcademicException::class, 'terkunci');
        expect(Grade::where('student_id', $this->ahmad->id)->value('score'))->toBe(70.0);

        app(SaveGrades::class)->handle($assessment, [$this->ahmad->id => '99'], 'Salah input');
        expect(Grade::where('student_id', $this->ahmad->id)->value('score'))->toBe(99.0)
            ->and(DB::table('audit_logs')->where('action', 'grade_unlock')->exists())->toBeTrue();

        $cards->generate($this->term->id, $this->classroom->id);
        expect($card->fresh()->items()->first()->final_score)->toBe(70.0);
        expect($cards->publish(ReportCard::all()))->toBe(0);
    });

    it('PDF rapor: wali anaknya saja, draf tidak terlihat wali, memuat teks Arab', function () {
        $captured = new class implements PdfRenderer
        {
            public string $html = '';

            public function render(string $html, string $format = 'A4'): string
            {
                $this->html = $html;

                return '%PDF';
            }
        };
        $this->app->instance(PdfRenderer::class, $captured);
        assess($this, $this->uas, [$this->ahmad->id => '90', $this->budi->id => '80']);
        app(ManageReportCards::class)->generate($this->term->id, $this->classroom->id);
        $card = ReportCard::where('student_id', $this->ahmad->id)->first();
        $guardianUser = tap(User::create(['name' => 'Wali', 'phone' => '62812', 'type' => 'guardian']))->assignRole('wali');
        Guardian::create(['user_id' => $guardianUser->id, 'name' => 'Wali', 'phone_wa' => '62812'])->students()->attach($this->ahmad->id, ['relation' => 'ayah']);
        $other = tap(User::create(['name' => 'Lain', 'phone' => '62899', 'type' => 'guardian']))->assignRole('wali');

        $this->actingAs($guardianUser)->get(route('report-cards.pdf', $card))->assertForbidden();

        app(ManageReportCards::class)->publish(collect([$card]));

        $this->get(route('report-cards.pdf', $card))->assertOk();
        expect($captured->html)->toContain('Ahmad')->toContain('Fiqih')->toContain('بِسْمِ');

        $this->actingAs($other)->get(route('report-cards.pdf', $card))->assertForbidden();
        $this->actingAs($this->akademik)->get(route('report-cards.pdf', $card))->assertOk();
    });

    it('layar rapor: buat draf, terbitkan, hak akses', function () {
        assess($this, $this->uas, [$this->ahmad->id => '90', $this->budi->id => '80']);
        $this->actingAs($this->akademik);

        Livewire::test(ReportCards::class)
            ->call('generate')->assertHasErrors('classroomId')
            ->set('classroomId', (string) $this->classroom->id)
            ->call('generate')->assertHasNoErrors()->assertSee('Ahmad')->assertSee('Draf')
            ->call('publish')->assertSee('Terbit');

        $this->actingAs($this->ustadzUser)->get(route('staff.report-cards.index'))->assertForbidden();
    });
});

describe('portal', function () {
    beforeEach(function () {
        $this->guardianUser = tap(User::create(['name' => 'Wali', 'phone' => '62812', 'type' => 'guardian']))->assignRole('wali');
        Guardian::create(['user_id' => $this->guardianUser->id, 'name' => 'Wali', 'phone_wa' => '62812'])->students()->attach($this->ahmad->id, ['relation' => 'ayah']);
    });

    it('wali melihat jadwal, absensi, dan rapor terbit anaknya saja', function () {
        $session = app(OpenAttendanceSession::class)->fromSchedule($this->schedule, '2026-11-02', 'manual', $this->ustadzUser->id);
        app(RecordAttendance::class)->save($session, [], $this->ustadzUser->id);
        assess($this, $this->uas, [$this->ahmad->id => '90', $this->budi->id => '80']);
        app(ManageReportCards::class)->generate($this->term->id, $this->classroom->id);
        $this->actingAs($this->guardianUser);

        Livewire::test(StudentAcademics::class)->assertSee('Fiqih')->assertSee('sedang berlangsung');
        Livewire::test(StudentAcademics::class)->set('segment', 'absensi')->assertSee('100%');
        Livewire::test(StudentAcademics::class)->set('segment', 'nilai')->assertSee('Rapor belum terbit');

        app(ManageReportCards::class)->publish(ReportCard::all());
        Livewire::test(StudentAcademics::class)->set('segment', 'nilai')->assertSee('Rapor Ganjil')->assertSee('90');

        Livewire::test(StudentAcademics::class, ['childUuid' => $this->lain->uuid])->assertDontSee('Candra');
    });

    it('santri melihat miliknya sendiri', function () {
        $user = tap(User::create(['name' => 'Ahmad', 'username' => 'A1', 'type' => 'student']))->assignRole('santri');
        $this->ahmad->update(['user_id' => $user->id]);

        $this->actingAs($user)->get(route('santri.schedule'))->assertOk()->assertSee('Fiqih');
    });

    it('ustadz dan musyrif masuk ke portalnya; admin tetap ke panel', function () {
        expect(StartSession::homeFor($this->ustadzUser))->toBe(route('ustadz.home'))
            ->and(StartSession::homeFor($this->musyrif))->toBe(route('musyrif.home'))
            ->and(StartSession::homeFor($this->akademik))->toBe(route('staff.dashboard'));

        $this->actingAs($this->ustadzUser)->get(route('ustadz.home'))->assertOk()->assertSee('Fiqih')->assertSee('Mulai Absensi');
        $this->actingAs($this->musyrif)->get(route('musyrif.home'))->assertOk()->assertSee('Asrama 1');
        $this->actingAs($this->guardianUser)->get(route('ustadz.home'))->assertRedirect();
    });

    it('ustadz melihat santri kelas yang diajar, bukan kelas lain', function () {
        $this->actingAs($this->otherUser);
        expect(Student::query()->visibleTo($this->otherUser)->count())->toBe(0);

        $this->actingAs($this->ustadzUser);
        expect(Student::query()->visibleTo($this->ustadzUser)->pluck('name')->sort()->values()->all())->toBe(['Ahmad', 'Budi']);
    });
});
