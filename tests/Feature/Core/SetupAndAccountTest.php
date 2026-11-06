<?php

use App\Modules\Core\Livewire\Settings\SystemHealth;
use App\Modules\Core\Livewire\Staff\AccountSecurity;
use App\Modules\Core\Livewire\Staff\Dashboard;
use App\Modules\Core\Livewire\Staff\GlobalSearch;
use App\Modules\Core\Livewire\Staff\NotificationBell;
use App\Modules\Core\Models\AcademicYear;
use App\Modules\Core\Models\Classroom;
use App\Modules\Core\Models\Dormitory;
use App\Modules\Core\Models\SchoolProfile;
use App\Modules\Core\Models\Student;
use App\Modules\Core\Models\Teacher;
use App\Modules\Core\Models\User;
use App\Modules\Core\Support\SetupChecklist;
use Database\Seeders\ModuleSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;

beforeEach(function () {
    (new ModuleSeeder)->run();
    (new RoleSeeder)->run();
    $this->admin = tap(User::create(['name' => 'Admin', 'email' => 'admin@uji.test', 'type' => 'staff', 'password' => 'sandi-lama-123']))->assignRole('admin');
    $this->actingAs($this->admin);
});

it('menghitung checklist dari data nyata', function () {
    $checklist = app(SetupChecklist::class);
    expect(collect($checklist->steps())->where('done', true))->toHaveCount(0);

    SchoolProfile::current()->update(['name' => 'Pesantren Uji']);
    AcademicYear::create(['name' => '2026/2027', 'start_date' => '2026-07-01', 'end_date' => '2027-06-30', 'is_active' => true]);
    Classroom::create(['name' => 'A']);
    Dormitory::create(['name' => 'B', 'gender' => 'L']);
    Student::create(['nis' => '1', 'name' => 'S', 'gender' => 'L']);

    expect(collect($checklist->steps())->where('done', true)->pluck('key')->all())->toBe(['profile', 'year', 'classes', 'students']);
});

it('menandai langkah opsional dan menyembunyikan checklist hanya bila semua selesai', function () {
    Livewire::test(Dashboard::class)->assertSee('Setup awal pesantren')->assertSee('0 dari 6')->call('mark', 'modules')->assertSee('1 dari 6')->call('dismissChecklist')->assertStatus(422);

    Livewire::test(Dashboard::class)->call('mark', 'students')->assertNotFound();

    SchoolProfile::current()->update(['name' => 'P']);
    AcademicYear::create(['name' => '2026/2027', 'start_date' => '2026-07-01', 'end_date' => '2027-06-30', 'is_active' => true]);
    Classroom::create(['name' => 'A']);
    Dormitory::create(['name' => 'B', 'gender' => 'L']);
    Student::create(['nis' => '1', 'name' => 'S', 'gender' => 'L']);

    Livewire::test(Dashboard::class)->call('mark', 'integrations')->assertSee('Sembunyikan Daftar Ini')->call('dismissChecklist')->assertDontSee('Setup awal pesantren');
});

it('tidak menampilkan checklist dan angka yang bukan haknya ke peran lain', function () {
    $this->actingAs(tap(User::create(['name' => 'K', 'email' => 'k@uji.test', 'type' => 'staff']))->assignRole('kasir'));

    Livewire::test(Dashboard::class)->assertDontSee('Setup awal')->assertDontSee('Santri aktif')->call('mark', 'modules')->assertForbidden();
});

it('hanya menghitung santri yang boleh dilihat di dashboard', function () {
    $mine = Classroom::create(['name' => 'Saya']);
    $user = tap(User::create(['name' => 'U', 'email' => 'u@uji.test', 'type' => 'staff']))->assignRole('ustadz');
    $mine->update(['homeroom_teacher_id' => Teacher::create(['user_id' => $user->id, 'name' => 'U'])->id]);
    Student::create(['nis' => '1', 'name' => 'A', 'gender' => 'L', 'classroom_id' => $mine->id]);
    Student::create(['nis' => '2', 'name' => 'B', 'gender' => 'L']);

    $this->actingAs($user);

    Livewire::test(Dashboard::class)->assertViewHas('activeStudents', 1);
});

it('pencarian cepat menghormati hak akses', function () {
    $mine = Classroom::create(['name' => 'Saya']);
    Student::create(['nis' => '111', 'name' => 'Ahmad Terlihat', 'gender' => 'L', 'classroom_id' => $mine->id]);
    Student::create(['nis' => '222', 'name' => 'Ahmad Tersembunyi', 'gender' => 'L']);

    Livewire::test(GlobalSearch::class)->set('query', 'a')->assertDontSee('Ahmad')->set('query', 'ahmad')->assertSee('Ahmad Terlihat')->assertSee('Ahmad Tersembunyi')->set('query', '222')->assertSee('Ahmad Tersembunyi');

    $user = tap(User::create(['name' => 'U', 'email' => 'u2@uji.test', 'type' => 'staff']))->assignRole('ustadz');
    $mine->update(['homeroom_teacher_id' => Teacher::create(['user_id' => $user->id, 'name' => 'U'])->id]);
    $this->actingAs($user);

    Livewire::test(GlobalSearch::class)->set('query', 'ahmad')->assertSee('Ahmad Terlihat')->assertDontSee('Ahmad Tersembunyi');

    $this->actingAs(tap(User::create(['name' => 'K', 'email' => 'k2@uji.test', 'type' => 'staff']))->assignRole('kasir'));
    Livewire::test(GlobalSearch::class)->set('query', 'ahmad')->assertDontSee('Ahmad');
});

it('lonceng hanya menampilkan notifikasi milik sendiri', function () {
    $other = User::create(['name' => 'O', 'email' => 'o@uji.test', 'type' => 'staff']);
    $other->inAppNotifications()->create(['title' => 'Milik orang lain']);
    $this->admin->inAppNotifications()->create(['title' => 'Milik saya', 'link' => '/panel']);

    Livewire::test(NotificationBell::class)
        ->assertSee('Milik saya')
        ->assertDontSee('Milik orang lain')
        ->assertSee('1 belum dibaca')
        ->call('markAllRead')
        ->assertDontSee('belum dibaca');

    expect($other->inAppNotifications()->whereNull('read_at')->count())->toBe(1);
});

it('mengganti sandi hanya dengan sandi lama yang benar', function () {
    Livewire::test(AccountSecurity::class)->set('current_password', 'keliru')->set('password', 'sandi-baru-123')->set('password_confirmation', 'sandi-baru-123')->call('changePassword')->assertHasErrors('current_password');
    expect(Hash::check('sandi-lama-123', $this->admin->fresh()->password))->toBeTrue();

    Livewire::test(AccountSecurity::class)->set('current_password', 'sandi-lama-123')->set('password', 'sandi-baru-123')->set('password_confirmation', 'beda')->call('changePassword')->assertHasErrors('password');

    Livewire::test(AccountSecurity::class)->set('current_password', 'sandi-lama-123')->set('password', 'sandi-baru-123')->set('password_confirmation', 'sandi-baru-123')->call('changePassword')->assertHasNoErrors();
    expect(Hash::check('sandi-baru-123', $this->admin->fresh()->password))->toBeTrue();
});

it('mengaktifkan 2FA dengan kode benar, menampilkan kode cadangan sekali, lalu bisa dimatikan', function () {
    $component = Livewire::test(AccountSecurity::class)->call('enable')->assertSee('Pindai kode ini');

    $secret = decrypt($this->admin->fresh()->two_factor_secret);
    expect($this->admin->fresh()->hasEnabledTwoFactorAuthentication())->toBeFalse();

    $component->set('code', '000000')->call('confirm')->assertHasErrors('code');
    expect($this->admin->fresh()->two_factor_confirmed_at)->toBeNull();

    $component->set('code', (new Google2FA)->getCurrentOtp($secret))->call('confirm')->assertHasNoErrors()->assertSee('kode cadangan');
    expect($this->admin->fresh()->hasEnabledTwoFactorAuthentication())->toBeTrue();

    Livewire::test(AccountSecurity::class)->assertDontSee('kode cadangan')->call('disable');
    expect($this->admin->fresh()->two_factor_secret)->toBeNull();
});

it('kesehatan sistem menampilkan dan mengulang job gagal, hanya untuk admin', function () {
    DB::table('failed_jobs')->insert([
        'uuid' => 'a0000000-0000-4000-8000-000000000001',
        'connection' => 'sync',
        'queue' => 'default',
        'payload' => json_encode(['displayName' => 'App\\Jobs\\KirimUji', 'job' => 'x', 'data' => []]),
        'exception' => "RuntimeException: gagal uji\n#0 trace",
        'failed_at' => now(),
    ]);

    Livewire::test(SystemHealth::class)->assertSee('KirimUji')->assertSee('gagal uji')->call('retry', 'b0000000-0000-4000-8000-000000000009')->assertNotFound();

    $this->actingAs(tap(User::create(['name' => 'B', 'email' => 'b@uji.test', 'type' => 'staff']))->assignRole('bendahara'));
    $this->get(route('staff.settings.system'))->assertForbidden();
});
