<?php

use App\Modules\Academic\Actions\OpenAttendanceSession;
use App\Modules\Academic\Models\Schedule;
use App\Modules\Academic\Models\Subject;
use App\Modules\Core\Actions\StartSession;
use App\Modules\Core\Models\AcademicYear;
use App\Modules\Core\Models\Classroom;
use App\Modules\Core\Models\Dormitory;
use App\Modules\Core\Models\Guardian;
use App\Modules\Core\Models\InAppNotification;
use App\Modules\Core\Models\Module;
use App\Modules\Core\Models\Student;
use App\Modules\Core\Models\Term;
use App\Modules\Core\Models\User;
use App\Modules\Core\Support\ModuleRegistry;
use App\Modules\Leave\Actions\GateScan;
use App\Modules\Leave\Actions\ManageLeave;
use App\Modules\Leave\Livewire\GateBoard;
use App\Modules\Leave\Livewire\GateScanner;
use App\Modules\Leave\Livewire\GateShiftScreen;
use App\Modules\Leave\Livewire\GuardianLeaves;
use App\Modules\Leave\Livewire\LeaveDesk;
use App\Modules\Leave\Models\GateLog;
use App\Modules\Leave\Models\GateLogReview;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Support\LeaveException;
use App\Modules\Notification\Models\NotificationLog;
use App\Modules\Wallet\Actions\IssueCard;
use App\Modules\Wallet\Actions\ManageCard;
use App\Modules\Wallet\Models\Device;
use Carbon\CarbonImmutable;
use Database\Seeders\ModuleSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    (new ModuleSeeder)->run();
    (new RoleSeeder)->run();
    Module::whereIn('module_key', ['card', 'gate', 'notification_wa'])->update(['is_enabled' => true]);
    app(ModuleRegistry::class)->forget();
    Queue::fake();
    Cache::flush();
    $this->travelTo('2026-11-02 02:00:00');

    $this->admin = tap(User::create(['name' => 'Admin', 'email' => 'a@uji.test', 'phone' => '628100', 'type' => 'staff']))->assignRole('admin');
    $this->musyrif = tap(User::create(['name' => 'Musyrif', 'email' => 'm@uji.test', 'phone' => '628200', 'type' => 'staff']))->assignRole('musyrif');
    $this->otherMusyrif = tap(User::create(['name' => 'Musyrif Lain', 'email' => 'm2@uji.test', 'type' => 'staff']))->assignRole('musyrif');
    $this->penjaga = tap(User::create(['name' => 'Penjaga', 'email' => 'p@uji.test', 'type' => 'staff']))->assignRole('penjaga_gerbang');

    $this->dormitory = Dormitory::create(['name' => 'Asrama 1', 'gender' => 'L', 'musyrif_user_id' => $this->musyrif->id]);
    $this->student = Student::create(['nis' => 'A1', 'name' => 'Ahmad', 'gender' => 'L', 'dormitory_id' => $this->dormitory->id]);
    $this->other = Student::create(['nis' => 'B1', 'name' => 'Budi', 'gender' => 'L']);
    $this->card = app(IssueCard::class)->handle($this->student, 'AA11');
    app(IssueCard::class)->handle($this->other, 'BB22');

    $this->guardianUser = tap(User::create(['name' => 'Wali', 'phone' => '628300', 'type' => 'guardian']))->assignRole('wali');
    $this->guardian = Guardian::create(['user_id' => $this->guardianUser->id, 'name' => 'Wali', 'phone_wa' => '628300']);
    $this->guardian->students()->attach($this->student->id, ['relation' => 'ayah']);
});

function leaveNow($context, string $type = 'keluar', int $hours = 2): LeaveRequest
{
    $leave = app(ManageLeave::class)->request($context->student, $type, 'Periksa gigi', CarbonImmutable::now(), CarbonImmutable::now()->addHours($hours), $context->guardianUser->id);
    app(ManageLeave::class)->approve($leave, $context->musyrif);

    return $leave->fresh();
}

function gate($context, string $credential = 'AA11', ?string $pickup = null): array
{
    Cache::forget('gate-repeat:'.$context->student->id);

    return app(GateScan::class)->handle($credential, $context->penjaga->id, null, $pickup);
}

describe('izin', function () {
    it('alur wali mengajukan, musyrif diberi tahu, disetujui, wali mendapat kabar dan QR', function () {
        $this->actingAs($this->guardianUser);

        Livewire::test(GuardianLeaves::class)
            ->set('reason', '')->set('start_at', '2026-11-02T10:00')->set('end_at', '2026-11-02T12:00')->call('submit')->assertHasErrors('reason')
            ->set('reason', 'Periksa gigi')->set('end_at', '2026-11-02T09:00')->call('submit')->assertHasErrors('end_at')
            ->set('end_at', '2026-11-02T12:00')->call('submit')->assertHasNoErrors();

        $leave = LeaveRequest::first();
        expect($leave->status)->toBe('pending')->and($leave->start_at->toIso8601String())->toBe('2026-11-02T03:00:00+00:00')
            ->and(NotificationLog::where('event_key', 'leave_requested')->value('recipient'))->toBe('628200');

        app(ManageLeave::class)->approve($leave, $this->musyrif);

        expect($leave->fresh()->status)->toBe('approved')->and(NotificationLog::where('event_key', 'leave_decided')->first()->payload['message'])->toContain('Disetujui');

        Livewire::test(GuardianLeaves::class)->call('open', $leave->uuid)->assertSee('QR izin', false);
    });

    it('rentang bertumpuk ditolak; waktu lampau ditolak', function () {
        leaveNow($this);

        expect(fn () => app(ManageLeave::class)->request($this->student, 'keluar', 'x', CarbonImmutable::now()->addHour(), CarbonImmutable::now()->addHours(3), $this->guardianUser->id))->toThrow(LeaveException::class, 'izin lain');
        expect(fn () => app(ManageLeave::class)->request($this->other, 'keluar', 'x', CarbonImmutable::now()->subHours(3), CarbonImmutable::now()->subHour(), $this->guardianUser->id))->toThrow(LeaveException::class, 'sudah lewat');
    });

    it('wali tidak melihat izin anak lain dan hanya bisa membatalkan yang menunggu', function () {
        $foreign = app(ManageLeave::class)->request($this->other, 'keluar', 'Rahasia', CarbonImmutable::now(), CarbonImmutable::now()->addHour(), $this->admin->id);
        $own = app(ManageLeave::class)->request($this->student, 'keluar', 'Punyaku', CarbonImmutable::now(), CarbonImmutable::now()->addHour(), $this->guardianUser->id);
        $this->actingAs($this->guardianUser);

        $component = Livewire::test(GuardianLeaves::class)->assertSee('Keluar sebentar');
        expect(fn () => $component->call('open', $foreign->uuid))->toThrow(ModelNotFoundException::class);

        Livewire::test(GuardianLeaves::class)->call('cancel', $own->uuid);
        expect($own->fresh()->status)->toBe('cancelled');

        $approved = leaveNow($this);
        Livewire::test(GuardianLeaves::class)->call('cancel', $approved->uuid)->assertDispatched('toast');
        expect($approved->fresh()->status)->toBe('approved');
    });

    it('musyrif memutuskan asramanya saja; tolak wajib alasan', function () {
        $leave = app(ManageLeave::class)->request($this->student, 'keluar', 'x', CarbonImmutable::now(), CarbonImmutable::now()->addHour(), $this->guardianUser->id);

        $this->actingAs($this->otherMusyrif);
        Livewire::test(LeaveDesk::class)->assertDontSee('Ahmad')->call('approve', $leave->uuid)->assertForbidden();

        $this->actingAs($this->musyrif);
        Livewire::test(LeaveDesk::class)->assertSee('Ahmad')
            ->call('askReject', $leave->uuid)->call('reject')->assertHasErrors('rejectReason')
            ->set('rejectReason', 'Jadwal ujian')->call('reject')->assertHasNoErrors();

        expect($leave->fresh()->status)->toBe('rejected')->and(NotificationLog::where('event_key', 'leave_decided')->first()->payload['message'])->toContain('Jadwal ujian');
    });

    it('dua tingkat: musyrif meneruskan, pengelola memutuskan', function () {
        Module::where('module_key', 'leave')->update(['settings' => ['two_level' => true]]);
        $leave = app(ManageLeave::class)->request($this->student, 'keluar', 'x', CarbonImmutable::now(), CarbonImmutable::now()->addHour(), $this->guardianUser->id);

        app(ManageLeave::class)->approve($leave, $this->musyrif);
        expect($leave->fresh()->status)->toBe('pending')->and($leave->fresh()->first_approved_by)->toBe($this->musyrif->id);

        app(ManageLeave::class)->approve($leave, $this->admin);
        expect($leave->fresh()->status)->toBe('approved');
    });
});

describe('gerbang', function () {
    it('tanpa izin ditolak, tercatat, musyrif diberi tahu', function () {
        $result = gate($this);

        expect($result['outcome'])->toBe('denied')->and($result['message'])->toBe('Tidak ada izin.')
            ->and(GateLog::where('result', 'denied')->value('deny_reason'))->toBe('Tidak ada izin.')
            ->and(NotificationLog::where('event_key', 'gate_denied')->value('recipient'))->toBe('628200');
    });

    it('alasan penolakan sesuai keadaan izin', function () {
        $leave = app(ManageLeave::class)->request($this->student, 'keluar', 'x', CarbonImmutable::now()->addHours(5), CarbonImmutable::now()->addHours(6), $this->guardianUser->id);
        expect(gate($this)['message'])->toBe('Izin belum disetujui.');

        app(ManageLeave::class)->approve($leave, $this->musyrif);
        expect(gate($this)['message'])->toContain('belum berlaku');

        $this->travel(7)->hours();
        expect(gate($this)['message'])->toBe('Izin sudah kedaluwarsa.');
    });

    it('alur penuh: keluar lalu masuk tepat waktu, papan ikut berubah, wali dikabari', function () {
        $leave = leaveNow($this);

        expect(gate($this)['outcome'])->toBe('out')->and($leave->fresh()->status)->toBe('out')->and($leave->fresh()->out_at)->not->toBeNull();

        $this->actingAs($this->penjaga);
        Livewire::test(GateBoard::class)->assertSee('1 santri')->assertSee('Ahmad');

        $this->travel(1)->hours();
        expect(gate($this)['outcome'])->toBe('in')->and($leave->fresh()->status)->toBe('returned');
        Livewire::test(GateBoard::class)->assertSee('0 santri')->assertSee('Tidak ada santri di luar');

        expect(NotificationLog::where('event_key', 'gate_movement')->where('recipient', '628300')->count())->toBe(2);
    });

    it('tap dobel 5 detik diabaikan, tidak membuat keluar lalu masuk', function () {
        $leave = leaveNow($this);
        $gate = app(GateScan::class);

        expect($gate->handle('AA11', $this->penjaga->id)['outcome'])->toBe('out');
        expect($gate->handle('AA11', $this->penjaga->id)['outcome'])->toBe('repeat');
        expect(GateLog::count())->toBe(1)->and($leave->fresh()->status)->toBe('out');
    });

    it('papan: yang terlambat naik ke atas dan diberi tanda (UAT 12)', function () {
        leaveNow($this, hours: 1);
        gate($this);
        $this->travel(80)->minutes();
        $budi = app(ManageLeave::class)->request($this->other, 'keluar', 'Beli kitab', CarbonImmutable::now(), CarbonImmutable::now()->addHours(2), $this->admin->id);
        app(ManageLeave::class)->approve($budi, $this->admin);
        Cache::forget('gate-repeat:'.$this->other->id);
        expect(app(GateScan::class)->handle('BB22', $this->penjaga->id, null, null)['outcome'])->toBe('out');

        $this->actingAs($this->penjaga);
        Livewire::test(GateBoard::class)->assertSee('2 santri')->assertSeeInOrder(['Ahmad', 'Budi'])->assertViewHas('lateCount', 1);
    });

    it('kembali lewat batas dan toleransi: terlambat, wali dan musyrif dikabari, hilang dari papan', function () {
        $leave = leaveNow($this, hours: 1);
        gate($this);

        $this->travel(1)->hours();
        $this->travel(14)->minutes();
        expect(app(ManageLeave::class)->markLate())->toBe(0);

        $this->travel(2)->minutes();
        expect(app(ManageLeave::class)->markLate())->toBe(1)
            ->and(app(ManageLeave::class)->markLate())->toBe(0)
            ->and($leave->fresh()->status)->toBe('late')
            ->and(LeaveRequest::query()->outside()->count())->toBe(1)
            ->and(NotificationLog::where('event_key', 'leave_late')->pluck('recipient')->all())->toContain('628300', '628200');

        expect(gate($this)['outcome'])->toBe('in');
        expect($leave->fresh()->status)->toBe('late')->and($leave->fresh()->returned_at)->not->toBeNull()
            ->and(LeaveRequest::query()->outside()->count())->toBe(0)
            ->and($leave->fresh()->statusLabel())->toBe('Kembali terlambat')
            ->and(NotificationLog::where('event_key', 'leave_late')->where('recipient', '628300')->count())->toBe(1);
    });

    it('kembali terlambat tanpa job berkala tetap ditandai', function () {
        $leave = leaveNow($this, hours: 1);
        gate($this);
        $this->travel(3)->hours();

        expect(gate($this)['message'])->toBe('Terlambat kembali.')->and($leave->fresh()->status)->toBe('late')
            ->and(NotificationLog::where('event_key', 'leave_late')->exists())->toBeTrue();
    });

    it('izin pulang wajib mencatat penjemput, terlihat oleh wali', function () {
        $leave = leaveNow($this, 'pulang');

        expect(gate($this)['outcome'])->toBe('needs_pickup')->and($leave->fresh()->status)->toBe('approved');
        expect(gate($this, 'AA11', 'Pak Rahmat')['outcome'])->toBe('out');
        expect(GateLog::where('direction', 'out')->value('pickup_name'))->toBe('Pak Rahmat');

        $this->actingAs($this->guardianUser);
        Livewire::test(GuardianLeaves::class)->call('open', $leave->uuid)->assertSee('dijemput Pak Rahmat');
    });

    it('QR izin dipakai di gerbang; QR izin orang lain tidak mengizinkan; QR palsu ditolak', function () {
        $leave = leaveNow($this);

        expect(gate($this, GateScan::leaveQr($leave))['outcome'])->toBe('out')
            ->and(GateLog::where('direction', 'out')->where('result', 'ok')->value('method'))->toBe('qr');

        Cache::flush();
        expect(app(GateScan::class)->handle('L1.palsu', $this->penjaga->id)['outcome'])->toBe('denied');
        expect(app(GateScan::class)->handle('D1.abc.x.y', $this->penjaga->id)['outcome'])->toBe('denied');
        expect(app(GateScan::class)->handle('TIDAKADA', $this->penjaga->id)['outcome'])->toBe('denied');
    });

    it('kartu diblokir ditolak walau izin berlaku', function () {
        leaveNow($this);
        app(ManageCard::class)->block($this->card, 'blocked', 'Uji');

        expect(gate($this)['message'])->toBe('Kartu diblokir.');
    });

    it('manual wajib alasan dan izin aktif, masuk tinjauan musyrif', function () {
        $leave = leaveNow($this);
        $this->actingAs($this->penjaga);

        Livewire::test(GateBoard::class)->call('select', $this->student->uuid)->call('record')->assertHasErrors('reason')
            ->set('reason', 'Kartu tertinggal')->call('record')->assertHasNoErrors();

        expect($leave->fresh()->status)->toBe('out')->and(GateLog::where('result', 'ok')->value('method'))->toBe('manual');

        Livewire::test(GateBoard::class)->call('select', $this->other->uuid)->set('reason', 'x')->call('record')->assertHasErrors('reason');

        $this->actingAs($this->musyrif);
        $log = GateLog::where('method', 'manual')->first();
        Livewire::test(LeaveDesk::class)->assertSee('Kartu tertinggal')->call('markReviewed', $log->uuid)->assertDontSee('Kartu tertinggal');
        expect(GateLogReview::where('gate_log_id', $log->id)->value('reviewed_by'))->toBe($this->musyrif->id);
    });

    it('override hanya musyrif atau admin, wajib alasan, wali dikabari, muncul di papan', function () {
        $this->actingAs($this->penjaga);
        Livewire::test(GateBoard::class)->call('select', $this->student->uuid)->set('reason', 'Darurat')->set('override_return', '2026-11-02T15:00')->call('override')->assertForbidden();

        $this->actingAs($this->musyrif);
        Livewire::test(GateBoard::class)->call('select', $this->student->uuid)->set('override_return', '2026-11-02T15:00')->call('override')->assertHasErrors('reason')
            ->set('reason', 'Ke rumah sakit')->call('override')->assertHasNoErrors()->assertSee('1 santri');

        expect(GateLog::where('result', 'override')->value('note'))->toBe('Ke rumah sakit')
            ->and(LeaveRequest::query()->outside()->count())->toBe(1)
            ->and(NotificationLog::where('event_key', 'gate_override')->pluck('recipient')->all())->toContain('628300', '628200');

        expect(gate($this)['outcome'])->toBe('in');
    });

    it('log gerbang tidak bisa diubah atau dihapus', function () {
        gate($this);
        $log = GateLog::first();

        expect(fn () => DB::transaction(fn () => DB::table('gate_logs')->where('id', $log->id)->update(['result' => 'ok'])))->toThrow(QueryException::class)
            ->and(fn () => DB::transaction(fn () => DB::table('gate_logs')->where('id', $log->id)->delete()))->toThrow(QueryException::class);
    });

    it('layar pindai: hasil, catat penjemput, panggil musyrif', function () {
        $leave = leaveNow($this, 'pulang');
        $this->actingAs($this->penjaga);

        Livewire::test(GateScanner::class)
            ->set('scan', 'AA11')->call('submitScan')->assertSee('CATAT PENJEMPUT')
            ->call('submitPickup')->assertHasErrors('pickup_name')
            ->set('pickup_name', 'Pak Rahmat')->call('submitPickup')->assertSee('KELUAR')->assertSee('Ahmad');

        Cache::flush();
        Livewire::test(GateScanner::class)->set('scan', 'BB22')->call('submitScan')->assertSee('DITOLAK')->assertSee('Budi');

        $component = Livewire::test(GateScanner::class);
        Student::whereKey($this->other->id)->update(['dormitory_id' => $this->dormitory->id]);
        Cache::flush();
        $component->set('scan', 'BB22')->call('submitScan')->call('callMusyrif')->assertDispatched('toast');
        expect(InAppNotification::where('user_id', $this->musyrif->id)->where('title', 'Dipanggil ke gerbang')->exists())->toBeTrue();
    });

    it('serah terima: jumlah di luar tersimpan, shift berikutnya melihat catatan', function () {
        leaveNow($this);
        gate($this);
        $this->actingAs($this->penjaga);

        Livewire::test(GateShiftScreen::class)->call('start')->set('note', 'Ahmad periksa gigi')->call('end');

        $next = tap(User::create(['name' => 'Penjaga 2', 'email' => 'p2@uji.test', 'type' => 'staff']))->assignRole('penjaga_gerbang');
        $this->actingAs($next);
        Livewire::test(GateShiftScreen::class)->assertSee('Ahmad periksa gigi')->assertSee('Saat itu 1 santri')->assertSee('Terima Shift');
    });

    it('API perangkat gerbang', function () {
        leaveNow($this);
        Device::create(['name' => 'Gerbang', 'scopes' => ['gate'], 'api_key_hash' => hash('sha256', 'kunci')]);
        Device::create(['name' => 'Kantin', 'scopes' => ['canteen'], 'api_key_hash' => hash('sha256', 'kantin')]);

        $this->withToken('kantin')->postJson('/api/v1/device/gate/scan', ['card_uid' => 'AA11'])->assertForbidden();
        $this->withToken('kunci')->postJson('/api/v1/device/gate/scan', ['card_uid' => 'AA11'])->assertOk()->assertJson(['ok' => true, 'outcome' => 'out', 'name' => 'Ahmad']);
    });

    it('hak akses: penjaga di gerbang saja; tidak bisa membuka keuangan atau data santri', function () {
        expect(StartSession::homeFor($this->penjaga))->toBe(route('staff.gate.index'));

        foreach (['staff.gate.index', 'staff.gate.scan', 'staff.gate.today', 'staff.gate.logs', 'staff.gate.shift'] as $route) {
            $this->actingAs($this->penjaga)->get(route($route))->assertOk();
            $this->actingAs($this->guardianUser)->get(route($route))->assertRedirect();
        }

        $this->actingAs($this->penjaga)->get(route('staff.students.index'))->assertForbidden();
        $this->get(route('staff.invoices.index'))->assertForbidden();
        $this->get(route('staff.gate.logs.export'))->assertOk();

        Module::where('module_key', 'gate')->update(['is_enabled' => false]);
        app(ModuleRegistry::class)->forget();
        $this->get(route('staff.gate.index'))->assertForbidden();
    });

    it('kolom pencarian tidak muncul untuk penjaga yang tidak punya data untuk dicari', function () {
        $this->actingAs($this->penjaga)->get(route('staff.gate.index'))->assertDontSee('Cari menu, santri');
        $this->actingAs($this->admin)->get(route('staff.dashboard'))->assertSee('Cari menu, santri');
    });

    it('riwayat dengan filter rusak tidak galat', function () {
        $this->actingAs($this->penjaga)->get(route('staff.gate.logs', ['tanggal' => 'rusak', 'hasil' => 'rusak']))->assertOk();
    });
});

it('izin aktif membuat absensi tercatat izin, bukan alpa', function () {
    $year = AcademicYear::create(['name' => '2026', 'start_date' => '2026-07-01', 'end_date' => '2027-06-30', 'is_active' => true]);
    $term = Term::create(['academic_year_id' => $year->id, 'name' => 'G', 'start_date' => '2026-07-01', 'end_date' => '2026-12-31', 'is_active' => true]);
    $classroom = Classroom::create(['name' => '7A']);
    $this->student->update(['classroom_id' => $classroom->id]);
    $this->other->update(['classroom_id' => $classroom->id]);
    $subject = Subject::create(['code' => 'F', 'name' => 'Fiqih']);
    $schedule = Schedule::create(['term_id' => $term->id, 'classroom_id' => $classroom->id, 'subject_id' => $subject->id, 'day_of_week' => 1, 'start_time' => '09:00', 'end_time' => '10:00']);
    $leave = leaveNow($this);
    gate($this);

    $session = app(OpenAttendanceSession::class)->fromSchedule($schedule, '2026-11-02', 'card', $this->admin->id);

    expect($session->records()->where('student_id', $this->student->id)->value('status'))->toBe('izin')
        ->and($session->records()->where('student_id', $this->other->id)->value('status'))->toBe('alpa');
});
