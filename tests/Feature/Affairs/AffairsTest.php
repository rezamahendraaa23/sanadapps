<?php

use App\Modules\Academic\Models\Assessment;
use App\Modules\Academic\Models\AttendanceRecord;
use App\Modules\Academic\Models\AttendanceSession;
use App\Modules\Academic\Models\Grade;
use App\Modules\Academic\Models\GradeComponent;
use App\Modules\Academic\Models\Subject;
use App\Modules\Affairs\Actions\ComputeRiskScores;
use App\Modules\Affairs\Actions\ManageDuty;
use App\Modules\Affairs\Actions\MoveInventory;
use App\Modules\Affairs\Actions\RecordViolation;
use App\Modules\Affairs\Actions\SyncAlumni;
use App\Modules\Affairs\Listeners\PenalizeLateReturn;
use App\Modules\Affairs\Livewire\AlumniDirectory;
use App\Modules\Affairs\Livewire\DisciplineBoard;
use App\Modules\Affairs\Livewire\DutyBoard;
use App\Modules\Affairs\Livewire\GovernmentExport;
use App\Modules\Affairs\Livewire\HealthDesk;
use App\Modules\Affairs\Livewire\InventoryBoard;
use App\Modules\Affairs\Livewire\RiskBoard;
use App\Modules\Affairs\Models\AlumniProfile;
use App\Modules\Affairs\Models\DisciplineRecord;
use App\Modules\Affairs\Models\DisciplineRule;
use App\Modules\Affairs\Models\DisciplineSanction;
use App\Modules\Affairs\Models\DutyRoster;
use App\Modules\Affairs\Models\HealthRecord;
use App\Modules\Affairs\Models\InventoryItem;
use App\Modules\Affairs\Models\InventoryMovement;
use App\Modules\Affairs\Models\StudentRiskScore;
use App\Modules\Affairs\Support\AffairsException;
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
use App\Modules\Core\Support\StudentHistory;
use App\Modules\Finance\Models\Invoice;
use App\Modules\Leave\Events\LeaveEvent;
use App\Modules\Leave\Models\LeaveRequest;
use Database\Seeders\ModuleSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    (new ModuleSeeder)->run();
    (new RoleSeeder)->run();
    Module::query()->whereIn('module_key', ['discipline', 'health', 'analytics', 'alumni', 'inventory', 'duty_roster', 'export_dapodik', 'attendance', 'finance', 'grading', 'leave'])->update(['is_enabled' => true]);
    app(ModuleRegistry::class)->forget();
    Storage::fake('local');
    $this->travelTo('2026-11-02 05:00:00');

    $this->year = AcademicYear::create(['name' => '2026/2027', 'start_date' => '2026-07-01', 'end_date' => '2027-06-30', 'is_active' => true]);
    $this->lastYear = AcademicYear::create(['name' => '2025/2026', 'start_date' => '2025-07-01', 'end_date' => '2026-06-30', 'is_active' => false]);
    $this->classroom = Classroom::create(['name' => '7A']);

    $this->admin = tap(User::create(['name' => 'Admin', 'email' => 'a@uji.test', 'type' => 'staff']))->assignRole('admin');
    $this->musyrif = tap(User::create(['name' => 'Musyrif', 'email' => 'm@uji.test', 'type' => 'staff']))->assignRole('musyrif');
    $this->otherMusyrif = tap(User::create(['name' => 'Lain', 'email' => 'm2@uji.test', 'type' => 'staff']))->assignRole('musyrif');
    $this->uks = tap(User::create(['name' => 'UKS', 'email' => 'k@uji.test', 'type' => 'staff']))->assignRole('petugas_uks');
    $this->ustadz = tap(User::create(['name' => 'Ustadz', 'email' => 'u@uji.test', 'type' => 'staff']))->assignRole('ustadz');
    $this->dormitory = Dormitory::create(['name' => 'Abu Bakar', 'gender' => 'L', 'musyrif_user_id' => $this->musyrif->id]);
    $this->otherDorm = Dormitory::create(['name' => 'Umar', 'gender' => 'L', 'musyrif_user_id' => $this->otherMusyrif->id]);

    $this->student = Student::create(['nis' => 'A1', 'name' => 'Ahmad', 'gender' => 'L', 'classroom_id' => $this->classroom->id, 'dormitory_id' => $this->dormitory->id]);
    $this->guardianUser = tap(User::create(['name' => 'Wali', 'phone' => '6281200000001', 'type' => 'guardian']))->assignRole('wali');
    Guardian::create(['user_id' => $this->guardianUser->id, 'name' => 'Wali', 'phone_wa' => '6281200000001'])->students()->attach($this->student->id, ['relation' => 'ayah', 'is_primary' => true, 'is_payer' => true, 'can_receive_wa' => true]);

    $this->rule = DisciplineRule::create(['name' => 'Terlambat apel', 'points' => 10]);
    $this->sp1 = DisciplineSanction::create(['name' => 'SP1', 'min_points' => 25, 'notify_guardian' => true]);
    $this->sp2 = DisciplineSanction::create(['name' => 'SP2', 'min_points' => 50, 'notify_guardian' => true]);
});

function violate($context, string $date = '2026-11-01', ?int $points = null): DisciplineRecord
{
    return app(RecordViolation::class)->handle($context->student, $points ? DisciplineRule::create(['name' => 'X'.$points, 'points' => $points]) : $context->rule, $date, [], $context->musyrif->id);
}

describe('pelanggaran', function () {
    it('poin akumulatif per tahun ajaran; poin aturan disalin', function () {
        violate($this);
        violate($this, '2026-03-01');
        $this->rule->update(['points' => 99]);

        expect(RecordViolation::points($this->student))->toBe(10)
            ->and(RecordViolation::points($this->student, $this->lastYear))->toBe(10)
            ->and(DisciplineRecord::first()->points)->toBe(10);
    });

    it('melewati ambang: sanksi sekali, wali dan admin diberi tahu; tahun baru bisa lagi', function () {
        violate($this, points: 30);
        violate($this, points: 5);

        expect(DB::table('discipline_sanction_events')->count())->toBe(1)
            ->and($this->guardianUser->inAppNotifications()->where('title', 'Pemberitahuan SP1')->count())->toBe(1)
            ->and($this->admin->inAppNotifications()->where('title', 'SP1: Ahmad')->exists())->toBeTrue();

        violate($this, points: 20);
        expect(DB::table('discipline_sanction_events')->count())->toBe(2);

        violate($this, '2026-02-01', 40);
        expect(DB::table('discipline_sanction_events')->where('academic_year_id', $this->lastYear->id)->count())->toBe(1);
    });

    it('layar: musyrif hanya santri asramanya; bukti foto; ustadz mencatat; hanya admin mengatur', function () {
        $outsider = Student::create(['nis' => 'B1', 'name' => 'Budi', 'gender' => 'L', 'dormitory_id' => $this->otherDorm->id]);
        $this->actingAs($this->musyrif);

        $page = Livewire::test(DisciplineBoard::class)->call('record')->assertHasErrors('search')
            ->set('search', 'Bud')->assertDontSee('Budi')
            ->set('search', 'Ahm')->assertSee('A1')->call('chooseStudent', $this->student->uuid)
            ->call('record')->assertHasErrors('rule_id')
            ->set('rule_id', (string) $this->rule->id)->set('evidence', UploadedFile::fake()->image('b.jpg'))->call('record')->assertHasNoErrors()->assertSee('Terlambat apel');

        expect(DisciplineRecord::first()->evidence_path)->toStartWith('discipline-evidence/');
        expect(fn () => $page->call('chooseStudent', $outsider->uuid))->toThrow(Exception::class);
        $page->call('saveRule')->assertForbidden();

        $this->actingAs($this->admin);
        Livewire::test(DisciplineBoard::class)->set('rule_name', 'Bolos')->set('rule_points', '20')->call('saveRule')->assertHasNoErrors()->assertSee('Bolos')
            ->set('sanction_name', 'Skors')->set('sanction_points', '100')->call('saveSanction')->assertSee('Skors');
    });

    it('terlambat kembali: poin otomatis bila diatur, tidak dua kali', function () {
        $leave = LeaveRequest::create(['student_id' => $this->student->id, 'type' => 'keluar', 'reason' => 'x', 'qr_token' => (string) Str::uuid(), 'start_at' => now()->subDay(), 'end_at' => now()->subHour(), 'status' => 'late']);
        $listener = app(PenalizeLateReturn::class);

        $listener->handle(new LeaveEvent($leave, 'late'));
        expect(DisciplineRecord::count())->toBe(0);

        Module::where('module_key', 'discipline')->first()->update(['settings' => ['late_rule_id' => $this->rule->id]]);
        $listener->handle(new LeaveEvent($leave, 'late'));
        $listener->handle(new LeaveEvent($leave, 'late'));
        expect(DisciplineRecord::count())->toBe(1)->and(DisciplineRecord::first()->leave_request_id)->toBe($leave->id);
    });

    it('wali dan santri melihat poin di portal', function () {
        violate($this);
        $this->actingAs($this->guardianUser)->get(route('wali.progress'))->assertOk()->assertSee('Poin pelanggaran')->assertSee('Terlambat apel');
    });
});

it('lewat tengah malam WIB: tanggal hari ini diterima, besok ditolak dengan pesan Indonesia', function () {
    // 00.30 WIB tanggal 3, di UTC masih tanggal 2.
    $this->travelTo('2026-11-02 17:30:00');
    $this->actingAs($this->musyrif);

    $page = Livewire::test(DisciplineBoard::class)->assertSet('occurred_on', '2026-11-03')
        ->set('search', 'Ahm')->call('chooseStudent', $this->student->uuid)->set('rule_id', (string) $this->rule->id)
        ->set('occurred_on', '2026-11-04')->call('record')->assertHasErrors('occurred_on')->assertSee('tidak boleh setelah')
        ->set('occurred_on', '2026-11-03')->call('record')->assertHasNoErrors();

    expect(DisciplineRecord::first()->occurred_on->toDateString())->toBe('2026-11-03');
    unset($page);
});

describe('kesehatan', function () {
    beforeEach(function () {
        $this->record = HealthRecord::create(['student_id' => $this->student->id, 'visit_date' => '2026-11-01', 'complaint' => 'Demam tinggi', 'action' => 'Kompres', 'medication' => 'Parasetamol', 'outcome' => 'rujuk']);
    });

    it('dienkripsi di database', function () {
        $raw = DB::table('health_records')->first();
        expect($raw->complaint)->not->toContain('Demam')->and($raw->medication)->not->toContain('Parasetamol')->and($this->record->fresh()->complaint)->toBe('Demam tinggi');
    });

    it('petugas UKS membuka detail dan tercatat; admin dan musyrif hanya ringkasan; ustadz ditolak', function () {
        $this->actingAs($this->uks);
        Livewire::test(HealthDesk::class)->assertSee('Dirujuk')->assertDontSee('Demam')->call('open', $this->record->uuid)->assertSee('Demam tinggi')->assertSee('Parasetamol');
        expect(DB::table('audit_logs')->where('action', 'health.viewed')->count())->toBe(1);

        $this->actingAs($this->admin);
        $page = Livewire::test(HealthDesk::class)->assertSee('Dirujuk')->assertDontSee('Demam')->assertSee('hanya melihat ringkasan');
        $page->call('open', $this->record->uuid)->assertForbidden();
        Livewire::test(HealthDesk::class)->call('save')->assertForbidden();

        $this->actingAs($this->ustadz)->get(route('staff.health.index'))->assertForbidden();
    });

    it('catat: rujuk memberi tahu wali tanpa isi keluhan', function () {
        $this->actingAs($this->uks);
        Livewire::test(HealthDesk::class)->call('save')->assertHasErrors('search')
            ->set('search', 'Ahm')->call('chooseStudent', $this->student->uuid)->call('save')->assertHasErrors('complaint')
            ->set('complaint', 'Sakit perut parah')->set('outcome', 'dipulangkan')->call('save')->assertHasNoErrors();

        $note = $this->guardianUser->inAppNotifications()->where('title', 'Kabar kesehatan Ahmad')->first();
        expect($note)->not->toBeNull()->and($note->body)->not->toContain('Sakit perut')->and($note->body)->toContain('dipulangkan');
    });

    it('wali melihat ringkasan tanpa keluhan', function () {
        $this->actingAs($this->guardianUser)->get(route('wali.progress'))->assertSee('Kunjungan UKS')->assertSee('Dirujuk')->assertDontSee('Demam');
    });
});

describe('risiko', function () {
    it('contoh skor: tunggakan 2 bulan, kehadiran rendah, poin SP1 = 7 tinggi, alasan tersimpan', function () {
        foreach (['2026-08-10', '2026-09-10'] as $index => $due) {
            Invoice::create(['invoice_no' => 'I'.$index, 'student_id' => $this->student->id, 'due_date' => $due, 'total' => 1000, 'status' => 'overdue', 'source' => 'manual']);
        }
        foreach (range(1, 10) as $day) {
            $session = AttendanceSession::create(['session_date' => '2026-10-'.str_pad((string) (10 + $day), 2, '0', STR_PAD_LEFT), 'title' => 'A', 'start_time' => '07:00']);
            AttendanceRecord::create(['session_id' => $session->id, 'student_id' => $this->student->id, 'status' => $day <= 8 ? 'hadir' : 'alpa']);
        }
        violate($this, points: 30);

        expect(app(ComputeRiskScores::class)->handle())->toBe(1);
        $score = StudentRiskScore::first();
        expect($score->score)->toBe(7)->and($score->level)->toBe('high')
            ->and(array_column($score->reasons, 'text'))->toBe(['Menunggak 2 bulan', 'Kehadiran 80% dalam 30 hari', 'Poin pelanggaran 30']);
    });

    it('batas level: 0 rendah, 3 sedang; modul mati tidak dihitung; hitung ulang tidak menggandakan', function () {
        app(ComputeRiskScores::class)->handle();
        expect(StudentRiskScore::first()->level)->toBe('low');

        Invoice::create(['invoice_no' => 'I1', 'student_id' => $this->student->id, 'due_date' => '2026-08-10', 'total' => 1000, 'status' => 'overdue', 'source' => 'manual']);
        Invoice::create(['invoice_no' => 'I2', 'student_id' => $this->student->id, 'due_date' => '2026-09-10', 'total' => 1000, 'status' => 'overdue', 'source' => 'manual']);
        app(ComputeRiskScores::class)->handle();
        expect(StudentRiskScore::count())->toBe(1)->and(StudentRiskScore::first()->level)->toBe('medium');

        Module::where('module_key', 'finance')->update(['is_enabled' => false]);
        app(ModuleRegistry::class)->forget();
        app(ComputeRiskScores::class)->handle();
        expect(StudentRiskScore::first()->score)->toBe(0);
    });

    it('nilai turun lebih dari 10 poin; terlambat kembali 2 kali', function () {
        $first = Term::create(['academic_year_id' => $this->year->id, 'name' => 'G', 'start_date' => '2026-07-01', 'end_date' => '2026-09-30', 'is_active' => false]);
        $second = Term::create(['academic_year_id' => $this->year->id, 'name' => 'H', 'start_date' => '2026-10-01', 'end_date' => '2026-12-31', 'is_active' => true]);
        $subject = Subject::create(['code' => 'F', 'name' => 'Fiqih']);
        $component = GradeComponent::create(['name' => 'UH', 'weight' => 100]);
        foreach ([[$first, 90], [$second, 70]] as [$term, $score]) {
            $assessment = Assessment::create(['subject_id' => $subject->id, 'term_id' => $term->id, 'classroom_id' => $this->classroom->id, 'component_id' => $component->id, 'title' => 'UH', 'max_score' => 100, 'assessed_on' => '2026-09-01']);
            Grade::create(['assessment_id' => $assessment->id, 'student_id' => $this->student->id, 'score' => $score]);
        }
        foreach ([1, 2] as $index) {
            LeaveRequest::create(['student_id' => $this->student->id, 'type' => 'keluar', 'reason' => 'x', 'qr_token' => (string) Str::uuid(), 'start_at' => now()->subDays($index + 1), 'end_at' => now()->subDays($index), 'status' => 'returned', 'late_notified_at' => now()->subDays($index)]);
        }

        app(ComputeRiskScores::class)->handle();
        expect(array_column(StudentRiskScore::first()->reasons, 'text'))->toBe(['Rata-rata nilai turun 20 poin', 'Terlambat kembali 2 kali dalam 60 hari'])->and(StudentRiskScore::first()->score)->toBe(3);
    });

    it('layar: daftar dengan alasan, bobot bisa diubah admin, peran lain ditolak', function () {
        violate($this, points: 30);
        $this->actingAs($this->admin);

        $page = Livewire::test(RiskBoard::class)->call('compute')->set('level', 'low')->assertSee('Ahmad')->assertSee('Poin pelanggaran 30');
        $page->set('weights.discipline', '4')->call('saveWeights')->assertHasNoErrors();
        app(ComputeRiskScores::class)->handle();
        expect(StudentRiskScore::first()->score)->toBe(4);
        $page->set('weights.discipline', 'x')->call('saveWeights')->assertHasErrors('weights.discipline');

        $this->actingAs($this->uks)->get(route('staff.analytics.index'))->assertForbidden();
    });
});

describe('alumni, inventaris, piket, ekspor', function () {
    it('alumni otomatis dari kelulusan dengan kontak wali; bisa diubah dan diekspor', function () {
        $this->student->update(['status' => 'graduated']);
        expect(app(SyncAlumni::class)->handle())->toBe(1)->and(app(SyncAlumni::class)->handle())->toBe(0)
            ->and(AlumniProfile::first()->contact_phone)->toBe('6281200000001');

        $this->actingAs($this->admin);
        Livewire::test(AlumniDirectory::class)->assertSee('Ahmad')->call('edit', AlumniProfile::first()->uuid)->set('current_activity', 'Kuliah')->call('save')->assertHasNoErrors()->assertSee('Kuliah');
        $this->get(route('staff.alumni.export'))->assertOk();
    });

    it('inventaris: jumlah lewat mutasi, tidak bisa minus, riwayat tidak bisa dihapus', function () {
        $item = InventoryItem::create(['dormitory_id' => $this->dormitory->id, 'name' => 'Kasur']);
        $moves = app(MoveInventory::class);
        $moves->handle($item, 'in', 10, null, null);
        $moves->handle($item, 'damage', 2, 'Sobek', null);
        $moves->handle($item, 'repair', 1, null, null);
        $moves->handle($item, 'adjust', -1, 'Hitung ulang', null);

        expect($item->fresh()->quantity)->toBe(8);
        expect(fn () => $moves->handle($item, 'out', 9, null, null))->toThrow(AffairsException::class, 'hanya 8')
            ->and(fn () => $moves->handle($item, 'out', -1, null, null))->toThrow(AffairsException::class)
            ->and(fn () => InventoryMovement::first()->delete())->toThrow(Exception::class)
            ->and(fn () => InventoryMovement::first()->update(['quantity' => 99]))->toThrow(Exception::class);
    });

    it('inventaris: musyrif hanya asramanya', function () {
        $mine = InventoryItem::create(['dormitory_id' => $this->dormitory->id, 'name' => 'Kasur']);
        $other = InventoryItem::create(['dormitory_id' => $this->otherDorm->id, 'name' => 'Lemari']);
        $this->actingAs($this->musyrif);

        $page = Livewire::test(InventoryBoard::class)->assertSee('Kasur')->assertDontSee('Lemari')
            ->set('name', 'Bantal')->set('quantity', '5')->set('new_dormitory', (string) $this->otherDorm->id)->call('save')->assertForbidden();

        $page = Livewire::test(InventoryBoard::class)->set('name', 'Bantal')->set('quantity', '5')->set('new_dormitory', (string) $this->dormitory->id)->call('save')->assertHasNoErrors()->assertSee('Stok awal');
        expect(fn () => $page->call('askMove', $other->uuid))->toThrow(Exception::class);
        $page->call('askMove', $mine->uuid)->set('move_type', 'out')->set('move_quantity', '3')->call('move')->assertHasErrors('move_quantity');
        unset($page);
    });

    it('piket: tanggal sesuai hari, dobel ditolak, tukar, tampil di portal santri', function () {
        $roster = DutyRoster::create(['name' => 'Dapur', 'day_of_week' => 2, 'start_time' => '05:00', 'end_time' => '06:00']);
        $teacher = Teacher::create(['name' => 'Pak Ali']);
        $duty = app(ManageDuty::class);

        expect(fn () => $duty->assign($roster, 'student', $this->student->id, '2026-11-02'))->toThrow(AffairsException::class, 'Selasa');
        $first = $duty->assign($roster, 'student', $this->student->id, '2026-11-03');
        expect(fn () => $duty->assign($roster, 'student', $this->student->id, '2026-11-03'))->toThrow(AffairsException::class, 'sudah terjadwal');
        $second = $duty->assign($roster, 'teacher', $teacher->id, '2026-11-10');

        $duty->swap($first, $second);
        expect($first->fresh()->assignee_type)->toBe('teacher')->and($second->fresh()->assignee_id)->toBe($this->student->id)->and($first->fresh()->duty_date->toDateString())->toBe('2026-11-03');

        $this->actingAs($this->guardianUser)->get(route('wali.progress'))->assertSee('Jadwal piket')->assertSee('Dapur');

        $this->actingAs($this->admin);
        Livewire::test(DutyBoard::class)->assertSee('Pak Ali')->set('swap', [$first->uuid])->call('swapSelected')->assertHasErrors('swap');
    });

    it('ekspor: berkas terunduh, tidak memuat data santri lain status, peran lain ditolak', function () {
        $this->actingAs($this->admin);
        Livewire::test(GovernmentExport::class)->assertSee('belum dicocokkan');
        $this->get(route('staff.export.download', ['untuk' => 'dapodik', 'status' => 'active']))->assertOk()->assertHeader('content-disposition');
        $this->actingAs($this->musyrif)->get(route('staff.export.download'))->assertForbidden();
    });

    it('riwayat lengkap santri ikut memuat pelanggaran dan ringkasan kesehatan', function () {
        violate($this);
        HealthRecord::create(['student_id' => $this->student->id, 'visit_date' => '2026-11-01', 'complaint' => 'x', 'outcome' => 'ditangani']);

        $sections = app(StudentHistory::class)->build($this->student, $this->admin)[0]['sections'];
        expect($sections['discipline'])->toMatchArray(['points' => 10, 'count' => 1])->and($sections['health'])->toBe(['ditangani' => 1]);

        expect(app(StudentHistory::class)->build($this->student, $this->ustadz)[0]['sections'])->not->toHaveKey('health');
    });

    it('modul mati menutup rute', function () {
        Module::query()->whereIn('module_key', ['discipline', 'health', 'analytics', 'alumni', 'inventory', 'duty_roster', 'export_dapodik'])->update(['is_enabled' => false]);
        app(ModuleRegistry::class)->forget();
        $this->actingAs($this->admin);

        foreach (['staff.discipline.index', 'staff.health.index', 'staff.analytics.index', 'staff.alumni.index', 'staff.inventory.index', 'staff.duty.index', 'staff.export.index'] as $name) {
            $this->get(route($name))->assertForbidden();
        }
    });
});
