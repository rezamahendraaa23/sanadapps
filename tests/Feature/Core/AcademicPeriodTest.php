<?php

use App\Modules\Academic\Models\Schedule;
use App\Modules\Academic\Models\Subject;
use App\Modules\Core\Actions\RollAcademicPeriod;
use App\Modules\Core\Livewire\Masters\MasterData;
use App\Modules\Core\Models\AcademicYear;
use App\Modules\Core\Models\Classroom;
use App\Modules\Core\Models\Term;
use App\Modules\Core\Models\User;
use Database\Seeders\ModuleSeeder;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(function () {
    (new ModuleSeeder)->run();
    (new RoleSeeder)->run();
    $this->actingAs(tap(User::create(['name' => 'Admin', 'email' => 'a@uji.test', 'type' => 'staff']))->assignRole('admin'));
    $this->travelTo('2026-11-08 09:00:00');
});

it('mulai cepat: tahun ajaran berjalan dibuat dengan term dan langsung aktif', function () {
    Livewire::test(MasterData::class, ['tab' => 'years'])->assertSee('Mulai cepat')->assertSee('2026/2027')
        ->set('split', 'semester')->call('quickStart')->assertSee('Semester 1')->assertSee('Semester 2');

    $year = AcademicYear::firstOrFail();
    expect($year->name)->toBe('2026/2027')->and($year->is_active)->toBeTrue()
        ->and($year->start_date->toDateString())->toBe('2026-07-01')->and($year->end_date->toDateString())->toBe('2027-06-30')
        ->and(Term::where('is_active', true)->value('name'))->toBe('Semester 1');
});

it('tahun ajaran pertama yang ditambah lewat form langsung aktif; tahun berikutnya menunggu', function () {
    Livewire::test(MasterData::class, ['tab' => 'years'])->call('create')->assertSet('form.name', '2026/2027')
        ->set('split', 'year')->call('save')->assertHasNoErrors()->assertDispatched('toast', message: 'Tahun ajaran dibuat dan langsung aktif.');

    Livewire::test(MasterData::class, ['tab' => 'years'])->call('create')->assertSet('form.name', '2027/2028')
        ->call('save')->assertDispatched('toast', message: 'Tahun ajaran dibuat. Aktifkan saat periodenya tiba.');

    expect(AcademicYear::where('is_active', true)->value('name'))->toBe('2026/2027')
        ->and(Term::where('is_active', true)->first()->name)->toBe('Tahun penuh');
});

it('term tambahan di tahun aktif mengikuti term terakhir', function () {
    Livewire::test(MasterData::class, ['tab' => 'years'])->call('quickStart');
    $year = AcademicYear::firstOrFail();
    Term::query()->update(['end_date' => '2026-12-31']);

    Livewire::test(MasterData::class, ['tab' => 'years'])->call('createTerm', $year->id)
        ->assertSet('form.start_date', '2027-01-01')->assertSet('form.end_date', '2027-06-30')
        ->set('form.name', 'Fase Lanjutan')->call('save')->assertHasNoErrors();

    expect($year->terms()->count())->toBe(2);
});

it('pergantian otomatis: semester berikutnya, lalu tahun ajaran baru dengan pembagian yang sama', function () {
    Livewire::test(MasterData::class, ['tab' => 'years'])->set('split', 'semester')->call('quickStart');
    $roll = app(RollAcademicPeriod::class);
    expect($roll->handle())->toBeNull();

    $this->travelTo('2027-01-02 00:05:00');
    expect($roll->handle())->toBe('Term Semester 2 aktif.')
        ->and(Term::where('is_active', true)->value('name'))->toBe('Semester 2');

    $this->travelTo('2027-07-01 00:05:00');
    expect($roll->handle())->toBe('Tahun ajaran 2027/2028 aktif.');
    $active = AcademicYear::where('is_active', true)->firstOrFail();
    expect($active->name)->toBe('2027/2028')->and($active->terms()->count())->toBe(2)
        ->and(Term::where('is_active', true)->first()->academic_year_id)->toBe($active->id)
        ->and(AcademicYear::count())->toBe(2);
});

it('pergantian otomatis memakai tahun ajaran yang sudah disiapkan admin', function () {
    Livewire::test(MasterData::class, ['tab' => 'years'])->call('quickStart');
    Livewire::test(MasterData::class, ['tab' => 'years'])->call('create')->set('form.start_date', '2027-07-15')->set('form.end_date', '2028-06-30')->call('save');

    $this->travelTo('2027-07-20 00:05:00');
    app(RollAcademicPeriod::class)->handle();

    expect(AcademicYear::where('is_active', true)->value('name'))->toBe('2027/2028')->and(AcademicYear::count())->toBe(2);
});

it('periode yang sudah dipakai jadwal tidak bisa dihapus, yang kosong bisa', function () {
    Livewire::test(MasterData::class, ['tab' => 'years'])->call('quickStart');
    Livewire::test(MasterData::class, ['tab' => 'years'])->call('create')->call('save');
    $next = AcademicYear::where('is_active', false)->firstOrFail();
    $term = $next->terms()->firstOrFail();
    $classroom = Classroom::create(['name' => 'Kelas Uji']);
    $subject = Subject::create(['code' => 'FIQ', 'name' => 'Fiqih']);
    Schedule::create(['term_id' => $term->id, 'classroom_id' => $classroom->id, 'subject_id' => $subject->id, 'day_of_week' => 1, 'start_time' => '07:00', 'end_time' => '08:00', 'activity_type' => 'kbm']);

    Livewire::test(MasterData::class, ['tab' => 'years'])->call('askDelete', $term->id, 'terms')->call('confirmDelete')
        ->assertDispatched('toast', message: 'Term ini sudah dipakai jadwal, nilai, atau rapor, jadi tidak bisa dihapus.');
    Livewire::test(MasterData::class, ['tab' => 'years'])->call('askDelete', $next->id, 'years')->call('confirmDelete')
        ->assertDispatched('toast', tone: 'danger');
    expect(Term::find($term->id))->not->toBeNull();

    Schedule::query()->delete();
    Livewire::test(MasterData::class, ['tab' => 'years'])->call('askDelete', $next->id, 'years')->call('confirmDelete')
        ->assertDispatched('toast', message: 'Data dihapus.');
    expect(AcademicYear::find($next->id))->toBeNull();
});
