<?php

use App\Modules\Core\Actions\PromoteStudents;
use App\Modules\Core\Actions\SaveStudent;
use App\Modules\Core\Jobs\ProcessStudentImport;
use App\Modules\Core\Livewire\Announcements\AnnouncementIndex;
use App\Modules\Core\Livewire\Students\ClassPromotion;
use App\Modules\Core\Livewire\Students\CustomFields;
use App\Modules\Core\Livewire\Students\StudentImport;
use App\Modules\Core\Models\Announcement;
use App\Modules\Core\Models\Classroom;
use App\Modules\Core\Models\CustomFieldDefinition;
use App\Modules\Core\Models\Dormitory;
use App\Modules\Core\Models\Guardian;
use App\Modules\Core\Models\Import;
use App\Modules\Core\Models\Student;
use App\Modules\Core\Models\StudentEnrollment;
use App\Modules\Core\Models\User;
use App\Modules\Core\Support\StudentImportRow;
use Database\Seeders\ModuleSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\Testing\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

function importFile(array $rows): File
{
    $spreadsheet = new Spreadsheet;
    $spreadsheet->getActiveSheet()->fromArray([StudentImportRow::HEADINGS, ...$rows], null, 'A1', true);
    $path = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    return File::createWithContent('santri.xlsx', file_get_contents($path));
}

function importRow(string $name, array $overrides = []): array
{
    return array_values(array_merge(array_fill_keys(StudentImportRow::HEADINGS, ''), ['nama' => $name, 'jenis_kelamin' => 'L'], $overrides));
}

beforeEach(function () {
    (new ModuleSeeder)->run();
    (new RoleSeeder)->run();
    Storage::fake('local');
    $this->admin = tap(User::create(['name' => 'Admin', 'email' => 'admin@uji.test', 'type' => 'staff']))->assignRole('admin');
    $this->actingAs($this->admin);
    $this->classroom = Classroom::create(['name' => 'Kelas 7A']);
    $this->dormitory = Dormitory::create(['name' => 'Asrama Putra', 'gender' => 'L']);
});

it('mengunduh template dengan kolom yang benar', function () {
    $this->get(route('staff.students.import.template'))->assertOk()->assertDownload('template-impor-santri.xlsx');
});

it('memeriksa berkas tanpa menyimpan lalu melaporkan baris bermasalah', function () {
    $file = importFile([
        importRow('Benar Satu', ['kelas' => 'kelas 7a', 'asrama' => 'Asrama Putra', 'tanggal_lahir' => '31/12/2010', 'nama_wali' => 'Ayah', 'no_wa_wali' => '081234560001', 'hubungan_wali' => 'ayah']),
        importRow('', ['jenis_kelamin' => 'X']),
        importRow('Kelas Salah', ['kelas' => 'Tidak Ada']),
        importRow('Wa Salah', ['no_wa_wali' => '123', 'nama_wali' => 'W']),
        importRow('Tanggal Salah', ['tanggal_lahir' => 'bukan tanggal']),
    ]);

    Livewire::test(StudentImport::class)
        ->set('file', $file)
        ->call('check')
        ->assertHasNoErrors()
        ->assertSet('preview.total', 5)
        ->assertSet('preview.valid', 1)
        ->assertSet('preview.invalid', 4)
        ->assertSee('Nama wajib diisi.')
        ->assertSee('Jenis kelamin harus L atau P.')
        ->assertSee('belum ada')
        ->assertSee('Nomor WA wali');

    expect(Student::count())->toBe(0);
});

it('mengimpor baris valid, melewati yang salah, dan menyediakan laporan', function () {
    Student::create(['nis' => 'ADA', 'name' => 'Lama', 'gender' => 'L']);

    $component = Livewire::test(StudentImport::class)
        ->set('file', importFile([
            importRow('Anak Satu', ['nis' => 'N1', 'kelas' => 'Kelas 7A', 'nama_wali' => 'Ibu', 'no_wa_wali' => '081234560002', 'hubungan_wali' => 'ibu']),
            importRow('Anak Dua', ['nis' => 'N2', 'nama_wali' => 'Ibu', 'no_wa_wali' => '081234560002', 'hubungan_wali' => 'ibu']),
            importRow('NIS Ganda', ['nis' => 'N1']),
            importRow('NIS Terpakai', ['nis' => 'ADA']),
        ]))
        ->call('check')
        ->call('start');

    $import = Import::first();
    expect($import->status)->toBe('completed')
        ->and($import->success_rows)->toBe(2)
        ->and($import->failed_rows)->toBe(2)
        ->and(Student::where('nis', 'N1')->first()->classroom_id)->toBe($this->classroom->id)
        ->and(Student::where('nis', 'N1')->first()->user->password)->toBeNull()
        ->and(StudentEnrollment::count())->toBe(1)
        ->and(Guardian::count())->toBe(1)
        ->and(Guardian::first()->students()->count())->toBe(2);

    $component->assertSee('2 santri berhasil diimpor')->assertSee('Unduh Baris Gagal');
    $this->get(route('staff.students.import.errors', $import))->assertOk()->assertDownload('baris-gagal-impor.xlsx');
});

it('memantau impor yang masih berjalan di antrean', function () {
    Queue::fake();

    Livewire::test(StudentImport::class)
        ->set('file', importFile([importRow('Antre')]))
        ->call('check')
        ->call('start')
        ->assertSee('Sedang mengimpor')
        ->assertSeeHtml('wire:poll.2s');

    Queue::assertPushed(ProcessStudentImport::class);
    expect(Student::count())->toBe(0);
});

it('mengimpor 200 baris di bawah 30 detik', function () {
    $rows = array_map(fn ($n) => importRow("Santri {$n}", ['nis' => "T{$n}", 'kelas' => 'Kelas 7A', 'nama_wali' => "Wali {$n}", 'no_wa_wali' => '0812'.str_pad((string) $n, 8, '0', STR_PAD_LEFT)]), range(1, 200));
    $file = importFile($rows);
    $start = microtime(true);

    Livewire::test(StudentImport::class)->set('file', $file)->call('check')->call('start');

    expect(Student::count())->toBe(200)->and(microtime(true) - $start)->toBeLessThan(30);
});

it('menolak berkas yang bukan xlsx atau kosong', function () {
    Livewire::test(StudentImport::class)->set('file', UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'))->call('check')->assertHasErrors('file');
    Livewire::test(StudentImport::class)->set('file', importFile([]))->call('check')->assertHasErrors('file');
});

it('menolak import dan field custom untuk peran yang tidak berhak', function () {
    $this->actingAs(tap(User::create(['name' => 'B', 'email' => 'b@uji.test', 'type' => 'staff']))->assignRole('bendahara'));

    foreach (['staff.students.import', 'staff.students.import.template', 'staff.students.custom-fields', 'staff.students.promotion', 'staff.announcements.index'] as $route) {
        $this->get(route($route))->assertForbidden();
    }
});

it('membatasi laporan impor pada yang berhak', function () {
    $import = Import::create(['type' => 'students', 'errors' => []]);
    $this->actingAs(tap(User::create(['name' => 'B', 'email' => 'c@uji.test', 'type' => 'staff']))->assignRole('kasir'));

    $this->get(route('staff.students.import.errors', $import))->assertForbidden();
});

it('membuat field custom dengan kunci unik dan mewajibkan pilihan', function () {
    Livewire::test(CustomFields::class)->call('create')->set('label', 'Golongan Darah')->set('type', 'select')->call('save')->assertHasErrors('options');
    Livewire::test(CustomFields::class)->call('create')->set('label', 'Golongan Darah')->set('type', 'select')->set('options', 'A, B, O')->call('save')->assertHasNoErrors();
    Livewire::test(CustomFields::class)->call('create')->set('label', 'Golongan Darah')->call('save')->assertHasNoErrors();

    expect(CustomFieldDefinition::orderBy('id')->pluck('key')->all())->toBe(['golongan_darah', 'golongan_darah_2'])
        ->and(CustomFieldDefinition::first()->options)->toBe(['A', 'B', 'O']);
});

it('kenaikan kelas: pratinjau cocok, eksekusi, lalu batal dalam 24 jam', function () {
    $next = Classroom::create(['name' => 'Kelas 8A']);
    [$one] = app(SaveStudent::class)->handle(['name' => 'Satu', 'gender' => 'L', 'classroom_id' => $this->classroom->id], null, issuePassword: false);
    [$two] = app(SaveStudent::class)->handle(['name' => 'Dua', 'gender' => 'L', 'classroom_id' => $this->classroom->id], null, issuePassword: false);
    [$left] = app(SaveStudent::class)->handle(['name' => 'Keluar', 'gender' => 'L', 'classroom_id' => $this->classroom->id, 'status' => 'dropped'], null, issuePassword: false);

    $action = app(PromoteStudents::class);
    expect($action->preview($this->classroom->id))->toHaveCount(2);

    $batch = $action->execute($this->classroom->id, $next->id);

    expect($one->fresh()->classroom_id)->toBe($next->id)
        ->and($left->fresh()->classroom_id)->toBe($this->classroom->id)
        ->and(StudentEnrollment::where('student_id', $one->id)->count())->toBe(2);

    expect($action->undo($batch))->toBe(2);
    expect($one->fresh()->classroom_id)->toBe($this->classroom->id)
        ->and($two->fresh()->status)->toBe('active')
        ->and(StudentEnrollment::where('student_id', $one->id)->whereNull('ended_at')->value('classroom_id'))->toBe($this->classroom->id);

    expect(fn () => $action->undo($batch))->toThrow(RuntimeException::class, 'sudah dibatalkan');
});

it('kelulusan menandai santri lulus dan tidak bisa dibatalkan setelah 24 jam', function () {
    [$student] = app(SaveStudent::class)->handle(['name' => 'Lulus', 'gender' => 'L', 'classroom_id' => $this->classroom->id], null, issuePassword: false);
    $action = app(PromoteStudents::class);

    $batch = $action->execute($this->classroom->id, null);
    expect($student->fresh()->status)->toBe('graduated')->and($student->fresh()->classroom_id)->toBeNull();

    $this->travel(25)->hours();
    expect(fn () => $action->undo($batch))->toThrow(RuntimeException::class, '24 jam');
});

it('layar kenaikan menolak tujuan yang sama dan memproses setelah konfirmasi', function () {
    $next = Classroom::create(['name' => 'Kelas 8A']);
    app(SaveStudent::class)->handle(['name' => 'Santri', 'gender' => 'L', 'classroom_id' => $this->classroom->id], null, issuePassword: false);

    Livewire::test(ClassPromotion::class)
        ->set('from', (string) $this->classroom->id)
        ->assertSee('Pratinjau: 1 santri')
        ->set('to', (string) $this->classroom->id)
        ->call('execute')
        ->assertHasErrors('to')
        ->set('to', (string) $next->id)
        ->call('execute')
        ->assertDispatched('toast')
        ->assertSee('Bisa dibatalkan')
        ->assertSee('Kelas 7A: 1 santri dinaikkan');
});

it('dropdown tujuan memakai id kelas yang sebenarnya', function () {
    Classroom::create(['name' => 'Kelas Pengisi']);
    $next = Classroom::create(['name' => 'Kelas 8A']);

    Livewire::test(ClassPromotion::class)->assertViewHas('targets', fn (array $targets) => $targets['lulus'] === 'Lulus' && $targets[$next->id] === 'Kelas 8A');
});

it('pengumuman hanya tampil untuk penerima dan setelah jadwal tayang', function () {
    $other = Classroom::create(['name' => 'Kelas 8A']);
    $guardianUser = tap(User::create(['name' => 'Wali', 'phone' => '6281200000001', 'type' => 'guardian']))->assignRole('wali');
    $guardian = Guardian::create(['user_id' => $guardianUser->id, 'name' => 'Wali', 'phone_wa' => '6281200000001']);
    $student = Student::create(['nis' => 'A1', 'name' => 'Anak', 'gender' => 'L', 'classroom_id' => $this->classroom->id]);
    $guardian->students()->attach($student->id, ['relation' => 'ayah']);

    Livewire::test(AnnouncementIndex::class)->call('create')->set('title', 'Untuk semua')->set('body', 'Isi A')->call('save')->assertHasNoErrors();
    Livewire::test(AnnouncementIndex::class)->call('create')->set('title', 'Kelas saya')->set('body', 'Isi B')->set('audience', 'classroom')->set('audience_id', (string) $this->classroom->id)->call('save')->assertHasNoErrors();
    Livewire::test(AnnouncementIndex::class)->call('create')->set('title', 'Kelas lain')->set('body', 'Isi C')->set('audience', 'classroom')->set('audience_id', (string) $other->id)->call('save');
    Livewire::test(AnnouncementIndex::class)->call('create')->set('title', 'Jadwal nanti')->set('body', 'Isi D')->set('publish_at', now()->addDay()->format('Y-m-d\TH:i'))->call('save');
    Livewire::test(AnnouncementIndex::class)->call('create')->set('title', 'x')->set('body', 'y')->set('audience', 'classroom')->call('save')->assertHasErrors('audience_id');

    $this->actingAs($guardianUser)->get(route('wali.home'))
        ->assertOk()
        ->assertSee('Untuk semua')
        ->assertSee('Kelas saya')
        ->assertDontSee('Kelas lain')
        ->assertDontSee('Jadwal nanti');

    expect(Announcement::count())->toBe(4);
});

it('tidak menjalankan HTML dari isi pengumuman', function () {
    $user = tap(User::create(['name' => 'S', 'username' => 'S9', 'type' => 'student']))->assignRole('santri');
    Student::create(['nis' => 'S9', 'name' => 'S', 'gender' => 'L', 'user_id' => $user->id]);
    Announcement::create(['title' => 'T', 'body' => '<script>alert(1)</script>', 'publish_at' => now()]);

    $this->actingAs($user)->get(route('santri.home'))->assertOk()->assertDontSee('<script>alert(1)</script>', false);
});
