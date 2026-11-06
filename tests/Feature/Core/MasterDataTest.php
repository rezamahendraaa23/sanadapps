<?php

use App\Modules\Core\Actions\GenerateNis;
use App\Modules\Core\Actions\SaveStudent;
use App\Modules\Core\Livewire\Guardians\GuardianIndex;
use App\Modules\Core\Livewire\Masters\MasterData;
use App\Modules\Core\Livewire\Students\StudentForm;
use App\Modules\Core\Livewire\Students\StudentIndex;
use App\Modules\Core\Livewire\Students\StudentShow;
use App\Modules\Core\Livewire\Users\UserIndex;
use App\Modules\Core\Models\AcademicYear;
use App\Modules\Core\Models\Classroom;
use App\Modules\Core\Models\CustomFieldDefinition;
use App\Modules\Core\Models\Guardian;
use App\Modules\Core\Models\SchoolProfile;
use App\Modules\Core\Models\Student;
use App\Modules\Core\Models\StudentEnrollment;
use App\Modules\Core\Models\Teacher;
use App\Modules\Core\Models\Term;
use App\Modules\Core\Models\User;
use Database\Seeders\ModuleSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

function makeStaff(string $role = 'admin'): User
{
    return tap(User::create(['name' => ucfirst($role), 'email' => $role.'-'.uniqid().'@uji.test', 'type' => 'staff']))->assignRole($role);
}

beforeEach(function () {
    (new ModuleSeeder)->run();
    (new RoleSeeder)->run();
    $this->admin = makeStaff();
    $this->actingAs($this->admin);
});

it('membuka layar data induk untuk peran yang berhak dan menolak yang lain', function () {
    foreach (['staff.students.index', 'staff.guardians.index', 'staff.classrooms.index', 'staff.settings.users'] as $route) {
        $this->get(route($route))->assertOk();
    }

    $this->actingAs(makeStaff('kasir'));
    foreach (['staff.students.index', 'staff.guardians.index', 'staff.classrooms.index', 'staff.settings.users', 'staff.students.create'] as $route) {
        $this->get(route($route))->assertForbidden();
    }
});

it('mengganti periode aktif tanpa melanggar aturan satu aktif', function () {
    $old = AcademicYear::create(['name' => '2025/2026', 'start_date' => '2025-07-01', 'end_date' => '2026-06-30', 'is_active' => true]);
    $oldTerm = Term::create(['academic_year_id' => $old->id, 'name' => 'Genap', 'start_date' => '2026-01-01', 'end_date' => '2026-06-30', 'is_active' => true]);
    $new = AcademicYear::create(['name' => '2026/2027', 'start_date' => '2026-07-01', 'end_date' => '2027-06-30']);
    $newTerm = Term::create(['academic_year_id' => $new->id, 'name' => 'Ganjil', 'start_date' => '2026-07-01', 'end_date' => '2026-12-31']);

    Livewire::test(MasterData::class)->call('selectTab', 'terms')->call('activate', $newTerm->id)->assertDispatched('toast');

    expect($new->fresh()->is_active)->toBeTrue()
        ->and($old->fresh()->is_active)->toBeFalse()
        ->and($newTerm->fresh()->is_active)->toBeTrue()
        ->and($oldTerm->fresh()->is_active)->toBeFalse();
});

it('memvalidasi tanggal tahun ajaran dan membuat kelas', function () {
    Livewire::test(MasterData::class)->call('selectTab', 'years')->call('create')
        ->set('form.name', '2026/2027')->set('form.start_date', '2026-07-01')->set('form.end_date', '2026-01-01')
        ->call('save')->assertHasErrors('form.end_date');

    Livewire::test(MasterData::class)->call('create')->set('form.name', 'Kelas 7A')->set('form.level', '7')->call('save')->assertHasNoErrors();
    expect(Classroom::where('name', 'Kelas 7A')->exists())->toBeTrue();
});

it('menolak menghapus kelas yang masih punya santri', function () {
    $classroom = Classroom::create(['name' => 'Kelas 7A']);
    Student::create(['nis' => '1', 'name' => 'A', 'gender' => 'L', 'classroom_id' => $classroom->id]);

    Livewire::test(MasterData::class)->call('askDelete', $classroom->id)->call('confirmDelete')->assertDispatched('toast', message: 'Kelas ini masih punya santri. Pindahkan santrinya dulu.');

    expect(Classroom::find($classroom->id))->not->toBeNull();
});

it('membuat NIS berurutan dari pola pengaturan', function () {
    SchoolProfile::create(['name' => 'P', 'nis_pattern' => '{tahun}{urut:4}']);
    Student::create(['nis' => '20260007', 'name' => 'A', 'gender' => 'L']);

    expect(app(GenerateNis::class)->handle(2026))->toBe('20260008')
        ->and(app(GenerateNis::class)->handle(2027))->toBe('20270001');
});

it('tidak memakai ulang NIS santri yang sudah dihapus', function () {
    SchoolProfile::create(['name' => 'P']);
    Student::create(['nis' => '20260009', 'name' => 'A', 'gender' => 'L'])->delete();

    expect(app(GenerateNis::class)->handle(2026))->toBe('20260010');
});

it('menambah santri lewat form dengan akun NIS, wali, dan riwayat kelas', function () {
    $classroom = Classroom::create(['name' => 'Kelas 7A']);

    Livewire::test(StudentForm::class)
        ->set('name', 'Santri Baru')->set('gender', 'L')->set('classroom_id', (string) $classroom->id)
        ->set('guardian_name', 'Ayah Uji')->set('guardian_phone', '0812-1111-2222')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    $student = Student::where('name', 'Santri Baru')->first();
    expect($student->nis)->not->toBeEmpty()
        ->and($student->user->username)->toBe($student->nis)
        ->and($student->user->hasRole('santri'))->toBeTrue()
        ->and(StudentEnrollment::where('student_id', $student->id)->whereNull('ended_at')->value('classroom_id'))->toBe($classroom->id)
        ->and($student->guardians->first()->phone_wa)->toBe('6281211112222')
        ->and($student->guardians->first()->pivot->is_primary)->toBeTrue()
        ->and(User::where('phone', '6281211112222')->first()->hasRole('wali'))->toBeTrue();
});

it('menghubungkan satu nomor WA ke banyak anak tanpa membuat wali baru', function () {
    foreach (['Anak Satu', 'Anak Dua'] as $name) {
        Livewire::test(StudentForm::class)->set('name', $name)->set('gender', 'P')->set('guardian_name', 'Ibu Uji')->set('guardian_phone', '081233334444')->call('save')->assertHasNoErrors();
    }

    expect(Guardian::count())->toBe(1)->and(Guardian::first()->students()->count())->toBe(2);
});

it('menolak nomor wali salah dan NIS ganda', function () {
    Student::create(['nis' => 'X1', 'name' => 'A', 'gender' => 'L']);

    Livewire::test(StudentForm::class)->set('name', 'B')->set('gender', 'L')->set('nis', 'X1')->set('guardian_name', 'W')->set('guardian_phone', '123')
        ->call('save')->assertHasErrors('nis');

    Livewire::test(StudentForm::class)->set('name', 'B')->set('gender', 'L')->set('guardian_name', 'W')->set('guardian_phone', '123')
        ->call('save')->assertHasErrors('guardian_phone');

    expect(Student::count())->toBe(1);
});

it('mencatat riwayat saat pindah kelas', function () {
    $a = Classroom::create(['name' => 'A']);
    $b = Classroom::create(['name' => 'B']);
    [$student] = app(SaveStudent::class)->handle(['name' => 'S', 'gender' => 'L', 'classroom_id' => $a->id]);

    app(SaveStudent::class)->handle(['classroom_id' => $b->id], $student);

    expect(StudentEnrollment::where('student_id', $student->id)->count())->toBe(2)
        ->and(StudentEnrollment::where('student_id', $student->id)->whereNull('ended_at')->value('classroom_id'))->toBe($b->id)
        ->and(StudentEnrollment::where('classroom_id', $a->id)->value('status'))->toBe('moved');
});

it('menyimpan dan mewajibkan field custom', function () {
    CustomFieldDefinition::create(['key' => 'golongan_darah', 'label' => 'Golongan darah', 'type' => 'select', 'options' => ['A', 'B'], 'is_required' => true]);

    Livewire::test(StudentForm::class)->set('name', 'C')->set('gender', 'L')->call('save')->assertHasErrors('extra.golongan_darah');
    Livewire::test(StudentForm::class)->set('name', 'C')->set('gender', 'L')->set('extra.golongan_darah', 'Z')->call('save')->assertHasErrors('extra.golongan_darah');
    Livewire::test(StudentForm::class)->set('name', 'C')->set('gender', 'L')->set('extra.golongan_darah', 'A')->call('save')->assertHasNoErrors();

    expect(Student::first()->extra['golongan_darah'])->toBe('A');
});

it('mencari, memfilter, mengurutkan, dan memindahkan santri massal', function () {
    $a = Classroom::create(['name' => 'A']);
    $b = Classroom::create(['name' => 'B']);
    $one = Student::create(['nis' => '100', 'name' => 'Ahmad', 'gender' => 'L', 'classroom_id' => $a->id]);
    $two = Student::create(['nis' => '200', 'name' => 'Budi', 'gender' => 'L', 'classroom_id' => $a->id]);

    Livewire::test(StudentIndex::class)
        ->assertSeeInOrder(['Ahmad', 'Budi'])
        ->call('sortBy', 'name')->assertSeeInOrder(['Budi', 'Ahmad'])
        ->set('search', 'bud')->assertSee('Budi')->assertDontSee('Ahmad')
        ->set('search', '100')->assertSee('Ahmad')->assertDontSee('Budi')
        ->set('search', 'zzz')->assertSee('Tidak ada santri yang cocok')
        ->set('search', '')
        ->set('selected', [$one->uuid, $two->uuid])->set('bulkClassroom', (string) $b->id)
        ->call('moveSelected')->assertDispatched('toast');

    expect($one->fresh()->classroom_id)->toBe($b->id)->and($two->fresh()->classroom_id)->toBe($b->id);
});

it('tidak membocorkan santri lain lewat tebakan alamat atau daftar', function () {
    $visible = Classroom::create(['name' => 'A']);
    $hidden = Classroom::create(['name' => 'B']);
    $ustadzUser = makeStaff('ustadz');
    $teacher = Teacher::create(['user_id' => $ustadzUser->id, 'name' => 'U']);
    $visible->update(['homeroom_teacher_id' => $teacher->id]);
    $mine = Student::create(['nis' => 'M1', 'name' => 'Santri Saya', 'gender' => 'L', 'classroom_id' => $visible->id]);
    $other = Student::create(['nis' => 'O1', 'name' => 'Santri Lain', 'gender' => 'L', 'classroom_id' => $hidden->id]);

    $this->actingAs($ustadzUser);

    Livewire::test(StudentIndex::class)->assertSee('Santri Saya')->assertDontSee('Santri Lain');
    $this->get(route('staff.students.show', $mine))->assertOk();
    $this->get(route('staff.students.show', $other))->assertForbidden();
    $this->get(route('staff.students.edit', $mine))->assertForbidden();
    $this->get(route('staff.students.export'))->assertForbidden();
});

it('mengekspor santri dalam xlsx', function () {
    Student::create(['nis' => '1', 'name' => 'A', 'gender' => 'L']);

    $this->get(route('staff.students.export'))->assertOk()->assertDownload('santri.xlsx');
});

it('menampilkan sandi awal sekali dan tab wali serta riwayat', function () {
    [$student, $password] = app(SaveStudent::class)->handle(['name' => 'D', 'gender' => 'L']);
    session()->flash('initial_password', $password);

    Livewire::test(StudentShow::class, ['student' => $student])->assertSee($password)->set('tab', 'wali')->assertSee('Belum ada wali');
    expect(Hash::check($password, $student->user->password))->toBeTrue();
});

it('menormalkan nomor wali dan menolak nomor ganda', function () {
    Livewire::test(GuardianIndex::class)->call('create')->set('name', 'W')->set('phone_wa', '0812 5555 6666')->call('save')->assertHasNoErrors();
    expect(Guardian::first()->phone_wa)->toBe('6281255556666');

    Livewire::test(GuardianIndex::class)->call('create')->set('name', 'X')->set('phone_wa', '081255556666')->call('save')->assertHasErrors('phone_wa');
    Livewire::test(GuardianIndex::class)->call('create')->set('name', 'X')->set('phone_wa', '12')->call('save')->assertHasErrors('phone_wa');
});

it('membuat staf multi-role dengan sandi sekali tampil dan data ustadz', function () {
    Livewire::test(UserIndex::class)
        ->call('create')
        ->set('name', 'Ustadz Uji')->set('email', 'ustadz.baru@uji.test')->set('roles', ['ustadz', 'musyrif'])->set('nip', '123')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Sandi sementara');

    $user = User::where('email', 'ustadz.baru@uji.test')->first();
    expect($user->hasAllRoles(['ustadz', 'musyrif']))->toBeTrue()
        ->and($user->teacher->nip)->toBe('123')
        ->and($user->password)->not->toBeNull();
});

it('mewajibkan role dan melarang mencabut role admin sendiri', function () {
    Livewire::test(UserIndex::class)->call('create')->set('name', 'A')->set('email', 'a@uji.test')->call('save')->assertHasErrors('roles');

    Livewire::test(UserIndex::class)->call('edit', $this->admin->uuid)->set('roles', ['bendahara'])->call('save')->assertSee('Anda tidak bisa mencabut role admin');
    Livewire::test(UserIndex::class)->call('edit', $this->admin->uuid)->set('is_active', false)->call('save')->assertSee('Anda tidak bisa mencabut role admin');

    expect($this->admin->fresh()->hasRole('admin'))->toBeTrue()->and($this->admin->fresh()->is_active)->toBeTrue();
});

it('hanya menerima role staf yang terdaftar', function () {
    Livewire::test(UserIndex::class)->call('create')->set('name', 'A')->set('email', 'b@uji.test')->set('roles', ['wali'])->call('save')->assertHasErrors('roles.0');
});
