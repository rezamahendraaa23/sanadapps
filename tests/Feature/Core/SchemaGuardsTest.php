<?php

use App\Modules\Core\Models\AcademicYear;
use App\Modules\Core\Models\AuditLog;
use App\Modules\Core\Models\Integration;
use App\Modules\Core\Models\Student;
use App\Modules\Core\Models\Term;
use App\Modules\Core\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

// Savepoint perlu karena satu perintah gagal membatalkan seluruh transaksi test di PostgreSQL.
function rejectedByDatabase(Closure $statement, string $message): void
{
    expect(fn () => DB::transaction($statement))->toThrow(QueryException::class, $message);
}

it('menolak ubah dan hapus pada audit log', function () {
    $log = AuditLog::create(['action' => 'created', 'entity_type' => 'Uji', 'entity_id' => 1]);

    rejectedByDatabase(fn () => DB::table('audit_logs')->where('id', $log->id)->update(['action' => 'diubah']), 'append-only');
    rejectedByDatabase(fn () => DB::table('audit_logs')->where('id', $log->id)->delete(), 'append-only');

    expect($log->fresh()->action)->toBe('created');
});

it('tetap bisa mengosongkan pengguna pada audit log saat akun dihapus, isi lain utuh', function () {
    $user = User::create(['name' => 'Staf Uji', 'email' => 'staf@uji.test', 'type' => 'staff']);
    $this->actingAs($user);
    $log = AuditLog::create(['user_id' => $user->id, 'action' => 'created', 'entity_type' => 'Uji']);
    auth()->logout();

    $user->delete();

    expect($log->fresh()->user_id)->toBeNull()->and($log->fresh()->action)->toBe('created');
});

it('mencatat perubahan santri tanpa kolom rahasia', function () {
    $user = User::create(['name' => 'Staf', 'email' => 'a@uji.test', 'type' => 'staff', 'password' => 'rahasia-panjang']);
    $this->actingAs($user);

    $student = Student::create(['nis' => '2026001', 'name' => 'Santri Uji', 'gender' => 'L']);
    $student->update(['name' => 'Santri Baru']);

    $log = AuditLog::where('entity_type', 'Student')->where('action', 'updated')->first();
    expect($log->user_id)->toBe($user->id)
        ->and($log->before['name'])->toBe('Santri Uji')
        ->and($log->after['name'])->toBe('Santri Baru');

    $userLog = AuditLog::where('entity_type', 'User')->first();
    expect($userLog->after)->not->toHaveKey('password');
});

it('menolak NIS ganda', function () {
    Student::create(['nis' => '1', 'name' => 'A', 'gender' => 'L']);

    rejectedByDatabase(fn () => Student::create(['nis' => '1', 'name' => 'B', 'gender' => 'P']), 'students_nis_unique');
});

it('hanya mengizinkan satu tahun ajaran dan satu term aktif', function () {
    $first = AcademicYear::create(['name' => '2026/2027', 'start_date' => '2026-07-01', 'end_date' => '2027-06-30', 'is_active' => true]);

    rejectedByDatabase(fn () => AcademicYear::create(['name' => '2027/2028', 'start_date' => '2027-07-01', 'end_date' => '2028-06-30', 'is_active' => true]), 'academic_years_one_active');

    Term::create(['academic_year_id' => $first->id, 'name' => 'Ganjil', 'start_date' => '2026-07-01', 'end_date' => '2026-12-31', 'is_active' => true]);

    rejectedByDatabase(fn () => Term::create(['academic_year_id' => $first->id, 'name' => 'Genap', 'start_date' => '2027-01-01', 'end_date' => '2027-06-30', 'is_active' => true]), 'terms_one_active');
});

it('menolak nomor WA wali ganda tetapi membolehkan pada staf', function () {
    User::create(['name' => 'W1', 'phone' => '6281', 'type' => 'guardian']);
    User::create(['name' => 'S1', 'phone' => '6281', 'type' => 'staff']);

    rejectedByDatabase(fn () => User::create(['name' => 'W2', 'phone' => '6281', 'type' => 'guardian']), 'users_guardian_phone_unique');
});

it('menyimpan kredensial integrasi terenkripsi dan tidak pernah mengeluarkannya', function () {
    $integration = Integration::create(['provider' => 'fonnte', 'credentials' => ['token' => 'rahasia-fonnte'], 'settings' => []]);

    expect(DB::table('integrations')->value('credentials'))->not->toContain('rahasia-fonnte')
        ->and($integration->fresh()->credentials['token'])->toBe('rahasia-fonnte')
        ->and($integration->toArray())->not->toHaveKey('credentials')
        ->and(AuditLog::where('entity_type', 'Integration')->first()->after)->not->toHaveKey('credentials');
});
