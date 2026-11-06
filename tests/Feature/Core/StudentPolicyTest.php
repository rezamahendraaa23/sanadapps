<?php

use App\Modules\Core\Models\Classroom;
use App\Modules\Core\Models\Dormitory;
use App\Modules\Core\Models\Guardian;
use App\Modules\Core\Models\Student;
use App\Modules\Core\Models\Teacher;
use App\Modules\Core\Models\User;
use Database\Seeders\RoleSeeder;

function staffWith(string $role): User
{
    return tap(User::create(['name' => $role, 'email' => $role.'@uji.test', 'type' => 'staff']))->assignRole($role);
}

beforeEach(function () {
    (new RoleSeeder)->run();

    $this->classA = Classroom::create(['name' => 'Kelas A']);
    $this->classB = Classroom::create(['name' => 'Kelas B']);
    $this->dormA = Dormitory::create(['name' => 'Asrama A', 'gender' => 'L']);
    $this->dormB = Dormitory::create(['name' => 'Asrama B', 'gender' => 'L']);

    $this->studentA = Student::create(['nis' => 'A1', 'name' => 'Santri A', 'gender' => 'L', 'classroom_id' => $this->classA->id, 'dormitory_id' => $this->dormA->id]);
    $this->studentB = Student::create(['nis' => 'B1', 'name' => 'Santri B', 'gender' => 'L', 'classroom_id' => $this->classB->id, 'dormitory_id' => $this->dormB->id]);
});

it('hanya membuka anak sendiri untuk wali', function () {
    $user = tap(User::create(['name' => 'Wali A', 'phone' => '6281111111', 'type' => 'guardian']))->assignRole('wali');
    $guardian = Guardian::create(['user_id' => $user->id, 'name' => 'Wali A', 'phone_wa' => '6281111111']);
    $guardian->students()->attach($this->studentA->id, ['relation' => 'ayah', 'uuid' => (string) str()->uuid()]);

    expect($user->can('view', $this->studentA))->toBeTrue()
        ->and($user->can('view', $this->studentB))->toBeFalse()
        ->and($user->can('update', $this->studentA))->toBeFalse()
        ->and(Student::query()->visibleTo($user)->pluck('nis')->all())->toBe(['A1']);
});

it('hanya membuka diri sendiri untuk santri', function () {
    $user = tap(User::create(['name' => 'Santri A', 'username' => 'A1', 'type' => 'student']))->assignRole('santri');
    $this->studentA->update(['user_id' => $user->id]);

    expect($user->can('view', $this->studentA))->toBeTrue()
        ->and($user->can('view', $this->studentB))->toBeFalse();
});

it('membatasi ustadz pada kelasnya dan musyrif pada asramanya', function () {
    $ustadz = staffWith('ustadz');
    $teacher = Teacher::create(['user_id' => $ustadz->id, 'name' => 'Ustadz']);
    $this->classA->update(['homeroom_teacher_id' => $teacher->id]);

    $musyrif = staffWith('musyrif');
    $this->dormB->update(['musyrif_user_id' => $musyrif->id]);

    expect($ustadz->can('view', $this->studentA))->toBeTrue()
        ->and($ustadz->can('view', $this->studentB))->toBeFalse()
        ->and($musyrif->can('view', $this->studentB))->toBeTrue()
        ->and($musyrif->can('view', $this->studentA))->toBeFalse();
});

it('memberi hak sesuai matriks PRD untuk role staf', function (string $role, bool $canView, bool $canManage) {
    $user = staffWith($role);

    expect($user->can('view', $this->studentA))->toBe($canView)
        ->and($user->can('update', $this->studentA))->toBe($canManage);
})->with([
    ['admin', true, true],
    ['admin_akademik', true, true],
    ['bendahara', true, false],
    ['petugas_uks', true, false],
    ['kasir', false, false],
    ['pengelola_merchant', false, false],
    ['panitia_ppdb', false, false],
]);

it('hanya admin yang memegang pengaturan dan audit', function () {
    foreach (['bendahara', 'admin_akademik', 'ustadz', 'musyrif', 'petugas_uks', 'kasir', 'pengelola_merchant', 'penjaga_gerbang', 'panitia_ppdb'] as $role) {
        $user = staffWith($role);
        expect($user->can('settings.manage'))->toBeFalse()->and($user->can('audit.view'))->toBeFalse();
    }

    $admin = staffWith('admin');
    expect($admin->can('settings.manage'))->toBeTrue()->and($admin->can('audit.view'))->toBeTrue();
});
