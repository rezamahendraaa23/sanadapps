<?php

use App\Modules\Academic\Models\AttendanceRecord;
use App\Modules\Academic\Models\AttendanceSession;
use App\Modules\Core\Livewire\Students\StudentShow;
use App\Modules\Core\Models\AcademicYear;
use App\Modules\Core\Models\Classroom;
use App\Modules\Core\Models\Module;
use App\Modules\Core\Models\Student;
use App\Modules\Core\Models\User;
use App\Modules\Core\Support\ModuleRegistry;
use App\Modules\Core\Support\StudentHistory;
use App\Modules\Finance\Models\Invoice;
use App\Modules\Tahfidz\Models\TahfidzRecord;
use Database\Seeders\ModuleSeeder;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(function () {
    (new ModuleSeeder)->run();
    (new RoleSeeder)->run();
    Module::query()->whereIn('module_key', ['attendance', 'tahfidz', 'finance'])->update(['is_enabled' => true]);
    app(ModuleRegistry::class)->forget();

    AcademicYear::create(['name' => '2024/2025', 'start_date' => '2024-07-01', 'end_date' => '2025-06-30', 'is_active' => false]);
    AcademicYear::create(['name' => '2025/2026', 'start_date' => '2025-07-01', 'end_date' => '2026-06-30', 'is_active' => true]);
    AcademicYear::create(['name' => '2023/2024', 'start_date' => '2023-07-01', 'end_date' => '2024-06-30', 'is_active' => false]);

    $this->classroom = Classroom::create(['name' => '7A']);
    $this->student = Student::create(['nis' => 'S1', 'name' => 'Ahmad', 'gender' => 'L', 'classroom_id' => $this->classroom->id]);
    $this->admin = tap(User::create(['name' => 'Akademik', 'email' => 'a@uji.test', 'type' => 'staff']))->assignRole('admin_akademik');
    $this->treasurer = tap(User::create(['name' => 'Bendahara', 'email' => 'b@uji.test', 'type' => 'staff']))->assignRole('bendahara');

    $session = fn (string $date) => AttendanceSession::create(['session_date' => $date, 'title' => 'Apel', 'start_time' => '07:00']);
    AttendanceRecord::create(['session_id' => $session('2024-09-01')->id, 'student_id' => $this->student->id, 'status' => 'hadir']);
    AttendanceRecord::create(['session_id' => $session('2024-09-02')->id, 'student_id' => $this->student->id, 'status' => 'alpa']);
    AttendanceRecord::create(['session_id' => $session('2025-09-01')->id, 'student_id' => $this->student->id, 'status' => 'hadir']);

    TahfidzRecord::create(['student_id' => $this->student->id, 'record_date' => '2025-10-01', 'type' => 'ziyadah', 'surah_from' => 1, 'ayah_from' => 1, 'surah_to' => 1, 'ayah_to' => 7, 'pages' => 1.5, 'grade' => 'mumtaz']);

    Invoice::create(['invoice_no' => 'INV-1', 'student_id' => $this->student->id, 'due_date' => '2025-08-10', 'total' => 500000, 'paid_total' => 200000, 'status' => 'partial', 'source' => 'manual']);
    Invoice::create(['invoice_no' => 'INV-2', 'student_id' => $this->student->id, 'due_date' => '2025-09-10', 'total' => 100000, 'status' => 'void', 'source' => 'manual']);
});

it('mengelompokkan catatan per tahun, terbaru dulu, tahun kosong tidak ikut', function () {
    $history = app(StudentHistory::class)->build($this->student, $this->treasurer);

    expect(array_column($history, 'name'))->toBe(['2025/2026', '2024/2025'])
        ->and($history[0]['active'])->toBeTrue()
        ->and($history[1]['sections']['attendance'])->toBe(['alpa' => 1, 'hadir' => 1])
        ->and($history[0]['sections']['attendance'])->toBe(['hadir' => 1])
        ->and($history[0]['sections']['tahfidz'])->toMatchArray(['setoran' => 1, 'ziyadah' => 1.5])
        ->and($history[0]['sections']['finance'])->toBe(['billed' => 500000, 'paid' => 200000]);
});

it('tagihan hanya untuk yang berhak; modul mati tidak ikut', function () {
    $ustadz = tap(User::create(['name' => 'Ustadz', 'email' => 'u@uji.test', 'type' => 'staff']))->assignRole('ustadz');
    expect(app(StudentHistory::class)->build($this->student, $ustadz)[0]['sections'])->not->toHaveKey('finance');

    Module::query()->where('module_key', 'tahfidz')->update(['is_enabled' => false]);
    app(ModuleRegistry::class)->forget();
    expect(app(StudentHistory::class)->build($this->student, $this->treasurer)[0]['sections'])->not->toHaveKey('tahfidz');
});

it('tab Riwayat Lengkap tampil di detail santri; santri tanpa catatan mendapat keadaan kosong', function () {
    $this->actingAs($this->admin);
    Livewire::test(StudentShow::class, ['student' => $this->student])
        ->call('$set', 'tab', 'lengkap')->assertSee('Riwayat Lengkap')->assertSee('Tahun ajaran 2025/2026')->assertSee('Tahun ajaran 2024/2025')->assertSee('Kehadiran');

    $empty = Student::create(['nis' => 'S2', 'name' => 'Kosong', 'gender' => 'L']);
    Livewire::test(StudentShow::class, ['student' => $empty])->call('$set', 'tab', 'lengkap')->assertSee('Belum ada catatan untuk santri ini.');
});
