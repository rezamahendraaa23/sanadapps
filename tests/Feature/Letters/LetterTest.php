<?php

use App\Modules\Core\Models\Classroom;
use App\Modules\Core\Models\Guardian;
use App\Modules\Core\Models\Module;
use App\Modules\Core\Models\Student;
use App\Modules\Core\Models\User;
use App\Modules\Core\Support\ModuleRegistry;
use App\Modules\Letters\Actions\IssueLetter;
use App\Modules\Letters\Livewire\LetterCenter;
use App\Modules\Letters\Models\Letter;
use App\Modules\Letters\Models\Letterhead;
use App\Modules\Letters\Models\LetterTemplate;
use App\Modules\Letters\Support\DefaultTemplates;
use App\Modules\Letters\Support\LetterSettings;
use App\Support\Pdf\PdfRenderer;
use Database\Seeders\ModuleSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    (new ModuleSeeder)->run();
    (new RoleSeeder)->run();
    Module::where('module_key', 'letters')->update(['is_enabled' => true]);
    app(ModuleRegistry::class)->forget();
    Storage::fake('local');
    $this->travelTo('2026-11-08 03:00:00');

    $this->admin = tap(User::create(['name' => 'Admin', 'email' => 'a@uji.test', 'type' => 'staff']))->assignRole('admin');
    $this->tu = tap(User::create(['name' => 'TU', 'email' => 'tu@uji.test', 'type' => 'staff']))->assignRole('tata_usaha');
    $this->ustadz = tap(User::create(['name' => 'Ustadz', 'email' => 'u@uji.test', 'type' => 'staff']))->assignRole('ustadz');
    $this->student = Student::create(['nis' => '2026001', 'name' => 'Ahmad Fauzi', 'gender' => 'L', 'classroom_id' => Classroom::create(['name' => '7A'])->id]);
    $guardian = Guardian::create(['name' => 'Bapak Fauzi', 'phone_wa' => '6281200000001']);
    $guardian->students()->attach($this->student->id, ['relation' => 'ayah', 'is_primary' => true, 'is_payer' => true]);
    DefaultTemplates::ensure();
    $this->sick = LetterTemplate::where('code', 'SKS')->first();
    $this->event = LetterTemplate::where('code', 'SPK')->first();
});

function issueSick($context, array $extra = ['keluhan' => 'Demam', 'lama_istirahat' => '3']): Letter
{
    return app(IssueLetter::class)->handle($context->sick, $context->student, null, $extra, now(config('sanad.display_timezone'))->toDateString(), 'KH. Abdullah', 'Pimpinan Pondok', null, $context->admin);
}

it('nomor berurutan per tahun lintas template, dibatalkan tetap memegang nomornya, tahun baru mulai dari satu', function () {
    $first = issueSick($this);
    $second = app(IssueLetter::class)->handle($this->event, null, 'Ketua RT 03', ['nama_acara' => 'Maulid', 'hari_tanggal' => 'Ahad, 15 November 2026', 'waktu' => '08.00', 'tempat' => 'Aula'], '2026-11-08', 'KH. Abdullah', 'Pimpinan Pondok', null, $this->admin);

    expect($first->letter_no)->toBe('001/SKS/XI/2026')->and($second->letter_no)->toBe('002/SPK/XI/2026');

    app(IssueLetter::class)->void($first, 'Salah nama');
    expect(issueSick($this)->letter_no)->toBe('003/SKS/XI/2026')->and(Letter::count())->toBe(3);

    $this->travelTo('2027-01-02 03:00:00');
    expect(issueSick($this)->letter_no)->toBe('001/SKS/I/2027');
});

it('isian otomatis terisi dari data santri dan tidak ada kurung kurawal tersisa', function () {
    $letter = issueSick($this);

    expect($letter->body)->toContain('Nama: Ahmad Fauzi')->toContain('NIS: 2026001')->toContain('Kelas: 7A')
        ->toContain('Asrama: -')->toContain('sakit (Demam)')->toContain('selama 3 hari')->toContain('Pimpinan Pondok')
        ->not->toContain('{');
});

it('menolak bila isian kosong atau santri belum dipilih', function () {
    expect(fn () => issueSick($this, ['keluhan' => 'Demam']))->toThrow(RuntimeException::class, 'Lama istirahat')
        ->and(fn () => app(IssueLetter::class)->handle($this->sick, null, null, ['keluhan' => 'x', 'lama_istirahat' => '1'], '2026-11-08', 'A', 'B', null, $this->admin))->toThrow(RuntimeException::class, 'santri')
        ->and(Letter::count())->toBe(0);
});

it('format nomor bisa diubah', function () {
    LetterSettings::save(['number_format' => 'PP-ALF/{kode}/{nomor}/{bulan}/{tahun}', 'number_digits' => 4]);
    expect(issueSick($this)->letter_no)->toBe('PP-ALF/SKS/0001/11/2026');
});

it('layar: pilih template, santri, isi, terbitkan, lalu PDF memakai kop dan footer yang diunggah', function () {
    $captured = new class implements PdfRenderer
    {
        public string $html = '';

        public function render(string $html, string $format = 'A4'): string
        {
            $this->html = $html;

            return '%PDF';
        }
    };
    $this->app->instance(PdfRenderer::class, $captured);
    $this->actingAs($this->tu);

    Livewire::test(LetterCenter::class, ['tab' => 'pengaturan'])
        ->set('kop_name', 'Kop resmi')->call('saveLetterhead')->assertHasErrors('kop_header')
        ->set('kop_header', UploadedFile::fake()->image('kop.png', 1240, 260))->set('kop_footer', UploadedFile::fake()->image('footer.png', 1240, 120))
        ->call('saveLetterhead')->assertHasNoErrors()
        ->set('number_format', 'tanpa nomor')->call('saveSettings')->assertHasErrors('number_format')
        ->set('number_format', '{nomor}/{kode}/{tahun}')->set('city', 'Bogor')->set('default_signer_name', 'KH. Abdullah')->call('saveSettings')->assertHasNoErrors();
    expect(Letterhead::first()->is_default)->toBeTrue();

    $component = Livewire::test(LetterCenter::class)
        ->call('pickTemplate', $this->sick->uuid)
        ->set('studentSearch', 'Ahmad')->assertSee('2026001')->call('pickStudent', $this->student->uuid)
        ->assertSee('Belum diisi')
        ->set('extra.keluhan', 'Tifus')->set('extra.lama_istirahat', '5')
        ->assertSee('001/SKS/2026')->assertDontSee('Belum diisi')
        ->call('issue')->assertHasNoErrors()->assertSee('Surat terbit');

    $letter = Letter::firstOrFail();
    expect($letter->letter_no)->toBe('001/SKS/2026')->and($letter->signer_name)->toBe('KH. Abdullah');

    $this->get(route('staff.letters.pdf', $letter))->assertOk()->assertHeader('content-type', 'application/pdf');
    expect($captured->html)->toContain('data:image/png;base64,')->toContain('Bogor, 8 November 2026')->toContain('Tifus');
    $this->get(route('staff.letters.kop', [Letterhead::first(), 'header']))->assertOk();
});

it('template baru: isian tambahan menjadi kunci, kode divalidasi', function () {
    $this->actingAs($this->admin);
    Livewire::test(LetterCenter::class, ['tab' => 'template'])->call('editTemplate')
        ->set('t_name', 'Undangan wali')->set('t_code', 'U D')->set('t_subject', 'Undangan')->set('t_body', 'Hadir pada {tanggal_acara}.')->call('saveTemplate')->assertHasErrors('t_code')
        ->set('t_code', 'UND')->set('t_fields', "Tanggal acara\nNama santri")->call('saveTemplate')->assertHasNoErrors();

    expect(LetterTemplate::where('code', 'UND')->first()->fields)->toBe([['key' => 'tanggal_acara', 'label' => 'Tanggal acara']]);
});

it('hanya admin dan tata usaha; modul mati menutup halaman', function () {
    $this->actingAs($this->ustadz)->get(route('staff.letters.index'))->assertForbidden();
    $this->actingAs($this->tu)->get(route('staff.letters.index'))->assertOk()->assertSee('Surat keterangan sakit');

    Module::where('module_key', 'letters')->update(['is_enabled' => false]);
    app(ModuleRegistry::class)->forget();
    $this->actingAs($this->admin)->get(route('staff.letters.index'))->assertForbidden();
});
