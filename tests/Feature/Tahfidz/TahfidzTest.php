<?php

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
use App\Modules\Tahfidz\Actions\RecordSetoran;
use App\Modules\Tahfidz\Livewire\MutabaahVerify;
use App\Modules\Tahfidz\Livewire\SetoranForm;
use App\Modules\Tahfidz\Livewire\StudentTahfidz;
use App\Modules\Tahfidz\Livewire\TahfidzAdmin;
use App\Modules\Tahfidz\Models\MutabaahItem;
use App\Modules\Tahfidz\Models\MutabaahLog;
use App\Modules\Tahfidz\Models\QuranSurah;
use App\Modules\Tahfidz\Models\TahfidzRecord;
use App\Modules\Tahfidz\Models\TahfidzTarget;
use App\Modules\Tahfidz\Support\TahfidzException;
use App\Modules\Tahfidz\Support\TahfidzProgress;
use App\Support\Pdf\PdfRenderer;
use Database\Seeders\ModuleSeeder;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(function () {
    (new ModuleSeeder)->run();
    (new RoleSeeder)->run();
    Module::where('module_key', 'tahfidz')->update(['is_enabled' => true]);
    app(ModuleRegistry::class)->forget();
    $this->travelTo('2026-11-02 03:00:00');

    $year = AcademicYear::create(['name' => '2026', 'start_date' => '2026-07-01', 'end_date' => '2027-06-30', 'is_active' => true]);
    $this->term = Term::create(['academic_year_id' => $year->id, 'name' => 'G', 'start_date' => '2026-07-01', 'end_date' => '2026-12-31', 'is_active' => true]);

    $this->admin = tap(User::create(['name' => 'Admin', 'email' => 'a@uji.test', 'type' => 'staff']))->assignRole('admin');
    $this->musyrif = tap(User::create(['name' => 'Musyrif', 'email' => 'm@uji.test', 'type' => 'staff']))->assignRole('musyrif');
    $this->ustadzUser = tap(User::create(['name' => 'Ustadz', 'email' => 'u@uji.test', 'type' => 'staff']))->assignRole('ustadz');
    $teacher = Teacher::create(['user_id' => $this->ustadzUser->id, 'name' => 'Ustadz']);

    $this->classroom = Classroom::create(['name' => '7A', 'homeroom_teacher_id' => $teacher->id]);
    $dorm = Dormitory::create(['name' => 'D1', 'gender' => 'L', 'musyrif_user_id' => $this->musyrif->id]);
    $this->studentUser = tap(User::create(['name' => 'Ahmad', 'username' => 'A1', 'type' => 'student']))->assignRole('santri');
    $this->student = Student::create(['nis' => 'A1', 'name' => 'Ahmad', 'gender' => 'L', 'classroom_id' => $this->classroom->id, 'dormitory_id' => $dorm->id, 'user_id' => $this->studentUser->id]);
    $this->other = Student::create(['nis' => 'B1', 'name' => 'Budi', 'gender' => 'L']);

    $this->guardianUser = tap(User::create(['name' => 'Wali', 'phone' => '62812', 'type' => 'guardian']))->assignRole('wali');
    Guardian::create(['user_id' => $this->guardianUser->id, 'name' => 'Wali', 'phone_wa' => '62812'])->students()->attach($this->student->id, ['relation' => 'ayah']);
});

function setoran($context, array $overrides = []): TahfidzRecord
{
    return app(RecordSetoran::class)->handle($context->student, array_merge(['type' => 'ziyadah', 'surah_from' => 114, 'ayah_from' => 1, 'surah_to' => 114, 'ayah_to' => 6, 'pages' => 1, 'grade' => 'jayyid', 'record_date' => '2026-11-02'], $overrides), $context->ustadzUser->id);
}

it('referensi Quran: 114 surat, 6236 ayat, nama Arab terisi', function () {
    expect(QuranSurah::count())->toBe(114)
        ->and((int) QuranSurah::sum('ayah_count'))->toBe(6236)
        ->and(QuranSurah::find(2)->ayah_count)->toBe(286)
        ->and(QuranSurah::find(36)->name_latin)->toBe('Yasin')
        ->and(QuranSurah::find(78)->juz_start)->toBe(30)
        ->and(QuranSurah::whereRaw("name_ar = ''")->count())->toBe(0);
});

it('ayat divalidasi dari referensi; rentang harus maju; lintas surat boleh', function () {
    expect(fn () => setoran($this, ['ayah_to' => 7]))->toThrow(TahfidzException::class, '1 sampai 6')
        ->and(fn () => setoran($this, ['ayah_from' => 0]))->toThrow(TahfidzException::class, 'Ayat awal')
        ->and(fn () => setoran($this, ['ayah_from' => 5, 'ayah_to' => 2]))->toThrow(TahfidzException::class, 'tidak boleh sebelum')
        ->and(fn () => setoran($this, ['surah_from' => 114, 'surah_to' => 113, 'ayah_to' => 2]))->toThrow(TahfidzException::class, 'tidak boleh sebelum')
        ->and(fn () => setoran($this, ['surah_from' => 115]))->toThrow(TahfidzException::class, 'Pilih surat')
        ->and(fn () => setoran($this, ['grade' => 'x']))->toThrow(TahfidzException::class, 'predikat');

    expect(setoran($this, ['surah_from' => 113, 'ayah_from' => 3, 'surah_to' => 114, 'ayah_to' => 2])->exists)->toBeTrue();
});

it('progres target: hanya ziyadah term ini; target juz dihitung bila halaman kosong; tidak lewat 100', function () {
    TahfidzTarget::create(['student_id' => $this->student->id, 'term_id' => $this->term->id, 'target_pages' => 10]);
    setoran($this, ['pages' => 4]);
    setoran($this, ['type' => 'murojaah', 'pages' => 50]);
    setoran($this, ['pages' => 90, 'record_date' => '2026-01-01']);

    expect(TahfidzProgress::forStudent($this->student)['percent'])->toBe(40);

    setoran($this, ['pages' => 100]);
    expect(TahfidzProgress::forStudent($this->student)['percent'])->toBe(100);

    TahfidzTarget::query()->update(['target_pages' => 0, 'target_juz' => 10]);
    expect(TahfidzProgress::forStudent($this->student)['percent'])->toBe(52);
});

it('peta juz: surat selesai hanya bila semua ayat tercakup, setoran bertahap digabung, rasib tidak dihitung', function () {
    setoran($this, ['ayah_to' => 3]);
    expect(TahfidzProgress::memorizedSurahs($this->student))->toBe([]);

    setoran($this, ['ayah_from' => 5, 'ayah_to' => 6]);
    expect(TahfidzProgress::memorizedSurahs($this->student))->toBe([]);

    setoran($this, ['ayah_from' => 4, 'ayah_to' => 4, 'grade' => 'rasib']);
    expect(TahfidzProgress::memorizedSurahs($this->student))->toBe([]);

    setoran($this, ['ayah_from' => 4, 'ayah_to' => 4]);
    expect(TahfidzProgress::memorizedSurahs($this->student))->toBe([114]);

    setoran($this, ['surah_from' => 112, 'ayah_from' => 1, 'surah_to' => 113, 'ayah_to' => 5]);
    expect(TahfidzProgress::memorizedSurahs($this->student))->toEqualCanonicalizing([112, 113, 114]);

    $map = TahfidzProgress::juzMap($this->student);
    expect($map)->toHaveCount(30)->and($map->firstWhere('juz', 30)['memorized'])->toBe(3)->and($map->firstWhere('juz', 1)['percent'])->toBe(0);
});

it('layar setoran: santri di luar cakupan tidak bisa dipilih; ayat salah tampil; simpan dan lanjut', function () {
    $this->actingAs($this->ustadzUser);

    $component = Livewire::test(SetoranForm::class)
        ->assertSee('Ahmad')->assertDontSee('Budi')
        ->call('pick', $this->student->uuid)
        ->set('surah_from', '114')
        ->assertSet('surah_to', '114')
        ->assertSee('Surat ini punya 6 ayat')
        ->set('ayah_to', '9')
        ->call('save')->assertHasErrors('ayah_to')->assertSee('1 sampai 6')
        ->set('ayah_to', '6')->set('pages', '1,5')
        ->call('save', true)->assertHasNoErrors()->assertSet('studentUuid', '');

    expect(TahfidzRecord::first()->pages)->toBe(1.5)->and(TahfidzRecord::first()->teacher_id)->toBe($this->ustadzUser->id);

    Livewire::test(SetoranForm::class, ['studentUuid' => $this->other->uuid])->assertDontSee('Setoran baru');
});

it('hak akses: wali dan santri hanya melihat, peran lain ditolak', function () {
    $this->actingAs($this->ustadzUser)->get(route('ustadz.tahfidz'))->assertOk();
    $this->actingAs($this->musyrif)->get(route('musyrif.tahfidz'))->assertOk();
    $this->actingAs($this->admin)->get(route('staff.tahfidz.index'))->assertOk();
    $this->actingAs($this->admin)->get(route('staff.tahfidz.admin'))->assertOk();
    $this->actingAs($this->ustadzUser)->get(route('staff.tahfidz.admin'))->assertForbidden();
    $this->actingAs($this->guardianUser)->get(route('wali.tahfidz'))->assertOk();
    $this->actingAs($this->studentUser)->get(route('santri.tahfidz'))->assertOk();

    Module::where('module_key', 'tahfidz')->update(['is_enabled' => false]);
    app(ModuleRegistry::class)->forget();
    $this->actingAs($this->studentUser)->get(route('santri.tahfidz'))->assertForbidden();
});

it('mutabaah: santri mencentang, musyrif memverifikasi, setelah itu terkunci; wali hanya melihat', function () {
    $item = MutabaahItem::create(['name' => 'Sholat Dhuha']);
    $this->actingAs($this->studentUser);

    $component = Livewire::test(StudentTahfidz::class)->assertSee('Sholat Dhuha')->call('toggle', $item->uuid)->assertSee('Menunggu verifikasi');
    $log = MutabaahLog::first();
    expect($log->status)->toBe('done')->and($log->verified_by)->toBeNull();

    $component->call('toggle', $item->uuid);
    expect($log->fresh()->status)->toBe('skipped');
    $component->call('toggle', $item->uuid);

    $this->actingAs($this->ustadzUser);
    $other = tap(User::create(['name' => 'Lain', 'email' => 'l@uji.test', 'type' => 'staff']))->assignRole('musyrif');
    $this->actingAs($other);
    Livewire::test(MutabaahVerify::class)->assertDontSee('Ahmad')->call('verifyAll');
    expect($log->fresh()->verified_by)->toBeNull();

    $this->actingAs($this->musyrif);
    Livewire::test(MutabaahVerify::class)->assertSee('Ahmad')->set('selected', [$log->uuid])->call('verifySelected')->assertSee('Tidak ada amalan');
    expect($log->fresh()->verified_by)->toBe($this->musyrif->id);

    $this->actingAs($this->studentUser);
    Livewire::test(StudentTahfidz::class)->assertSee('Diverifikasi')->call('toggle', $item->uuid);
    expect($log->fresh()->status)->toBe('done');

    $this->actingAs($this->guardianUser);
    Livewire::test(StudentTahfidz::class)->assertSee('Sholat Dhuha')->assertSee('Sudah')->call('toggle', $item->uuid)->assertForbidden();
});

it('wali tidak melihat anak lain; admin: amalan, target sekelas, rekap, ekspor, ringkasan PDF', function () {
    $this->actingAs($this->guardianUser);
    Livewire::test(StudentTahfidz::class, ['childUuid' => $this->other->uuid])->assertDontSee('Budi');

    setoran($this, ['pages' => 2]);
    $this->app->instance(PdfRenderer::class, new class implements PdfRenderer
    {
        public function render(string $html, string $format = 'A4'): string
        {
            return '%PDF'.$html;
        }
    });

    $this->actingAs($this->admin);
    Livewire::test(TahfidzAdmin::class)
        ->assertSee('Ahmad')
        ->call('selectTab', 'target')->call('saveTarget')->assertHasErrors('target_pages')
        ->set('target_pages', '20')->call('saveTarget')->assertHasErrors('target_classroom')
        ->set('target_classroom', (string) $this->classroom->id)->call('saveTarget')->assertHasNoErrors()
        ->call('selectTab', 'amalan')->set('item_name', 'Puasa Senin')->call('saveItem')->assertSee('Puasa Senin');

    expect(TahfidzTarget::where('student_id', $this->student->id)->value('target_pages'))->toBe(20.0)
        ->and(TahfidzTarget::where('student_id', $this->other->id)->exists())->toBeFalse();

    $this->get(route('staff.tahfidz.export', ['dari' => '2026-11-01', 'sampai' => '2026-11-30']))->assertOk();
    $this->get(route('staff.tahfidz.summary', $this->student))->assertOk()->assertHeader('content-type', 'application/pdf');
    $this->get(route('staff.tahfidz.admin', ['dari' => 'rusak']))->assertOk();

    $this->actingAs($this->guardianUser)->get(route('staff.tahfidz.summary', $this->student))->assertRedirect();
});
