<?php

use App\Modules\Academic\Actions\ManagePhases;
use App\Modules\Academic\Actions\SaveGrades;
use App\Modules\Academic\Livewire\CurriculumBoard;
use App\Modules\Academic\Models\Assessment;
use App\Modules\Academic\Models\Curriculum;
use App\Modules\Academic\Models\CurriculumPhase;
use App\Modules\Academic\Models\GradeComponent;
use App\Modules\Academic\Models\PhaseResult;
use App\Modules\Academic\Models\Subject;
use App\Modules\Academic\Support\AcademicException;
use App\Modules\Academic\Support\CertificateDesigns;
use App\Modules\Core\Models\AcademicYear;
use App\Modules\Core\Models\Classroom;
use App\Modules\Core\Models\Guardian;
use App\Modules\Core\Models\Module;
use App\Modules\Core\Models\SchoolProfile;
use App\Modules\Core\Models\Student;
use App\Modules\Core\Models\Term;
use App\Modules\Core\Models\User;
use App\Modules\Core\Support\ModuleRegistry;
use App\Support\Pdf\PdfRenderer;
use Database\Seeders\ModuleSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    (new ModuleSeeder)->run();
    (new RoleSeeder)->run();
    Module::whereIn('module_key', ['academic', 'curriculum'])->update(['is_enabled' => true]);
    app(ModuleRegistry::class)->forget();
    $this->travelTo('2026-11-08 03:00:00');

    $this->admin = tap(User::create(['name' => 'Akademik', 'email' => 'a@uji.test', 'type' => 'staff']))->assignRole('admin_akademik');
    $this->ustadz = tap(User::create(['name' => 'Ustadz', 'email' => 'u@uji.test', 'type' => 'staff']))->assignRole('ustadz');
    $year = AcademicYear::create(['name' => '2026', 'start_date' => '2026-07-01', 'end_date' => '2027-06-30', 'is_active' => true]);
    // Satu periode setahun penuh: kenaikan diatur fase, bukan semester.
    $this->term = Term::create(['academic_year_id' => $year->id, 'name' => 'Tahun 2026/2027', 'start_date' => '2026-07-01', 'end_date' => '2027-06-30', 'is_active' => true]);
    $this->fiqih = Subject::create(['code' => 'FIQ', 'name' => 'Fiqih']);
    $this->quran = Subject::create(['code' => 'QRN', 'name' => 'Tahsin']);
    $this->daily = GradeComponent::create(['name' => 'Harian', 'weight' => 40]);
    $this->final = GradeComponent::create(['name' => 'Ujian akhir fase', 'weight' => 60]);

    $this->curriculum = Curriculum::create(['name' => 'Program Tahfidz']);
    $phases = app(ManagePhases::class);
    $this->intro = $phases->savePhase($this->curriculum, ['name' => 'Masa Perkenalan', 'code' => 'PRK', 'duration_weeks' => 12, 'min_score' => 70, 'has_certificate' => true, 'certificate_title' => 'Sertifikat Masa Perkenalan']);
    $this->basic = $phases->savePhase($this->curriculum, ['name' => 'Fase Dasar', 'code' => 'DSR', 'duration_weeks' => 24, 'min_score' => 75, 'has_certificate' => true]);

    $this->room = Classroom::create(['name' => 'Perkenalan A']);
    $this->nextRoom = Classroom::create(['name' => 'Dasar A']);
    $phases->assign($this->room, $this->intro, '2026-08-01');
    $phases->assign($this->nextRoom, $this->basic, '2026-11-01');
    $this->ahmad = Student::create(['nis' => 'A1', 'name' => 'Ahmad', 'gender' => 'L', 'classroom_id' => $this->room->id]);
    $this->budi = Student::create(['nis' => 'B1', 'name' => 'Budi', 'gender' => 'L', 'classroom_id' => $this->room->id]);
});

function phaseAssess($context, Subject $subject, GradeComponent $component, string $on, array $scores): void
{
    $assessment = Assessment::create(['subject_id' => $subject->id, 'classroom_id' => $context->room->id, 'term_id' => $context->term->id, 'component_id' => $component->id, 'title' => $component->name, 'max_score' => 100, 'assessed_on' => $on]);
    app(SaveGrades::class)->handle($assessment, $scores);
}

it('fase menghitung tanggal selesai dari durasi dan urutannya bisa ditukar', function () {
    expect($this->room->fresh()->phase_ends_on->toDateString())->toBe('2026-10-23')
        ->and($this->intro->next()->id)->toBe($this->basic->id);

    app(ManagePhases::class)->move($this->basic, -1);
    expect($this->basic->fresh()->sort_order)->toBeLessThan($this->intro->fresh()->sort_order);
});

it('nilai fase hanya dari penilaian selama masa fase, memakai bobot komponen', function () {
    phaseAssess($this, $this->fiqih, $this->daily, '2026-08-20', [$this->ahmad->id => '80', $this->budi->id => '60']);
    phaseAssess($this, $this->fiqih, $this->final, '2026-10-20', [$this->ahmad->id => '90', $this->budi->id => '60']);
    phaseAssess($this, $this->quran, $this->final, '2026-10-21', [$this->ahmad->id => '70', $this->budi->id => '64']);
    // Di luar masa fase: tidak dihitung.
    phaseAssess($this, $this->quran, $this->final, '2026-11-02', [$this->ahmad->id => '10', $this->budi->id => '10']);

    expect(app(ManagePhases::class)->evaluate($this->room))->toBe(2);
    $ahmad = PhaseResult::where('student_id', $this->ahmad->id)->first();

    // Fiqih 80 x 40% + 90 x 60% = 86; Tahsin 70; rata-rata 78.
    expect($ahmad->scores[$this->fiqih->id]['score'])->toEqual(86)->and($ahmad->scores[$this->quran->id]['score'])->toEqual(70)
        ->and($ahmad->final_score)->toEqual(78)->and($ahmad->status)->toBe('pending');
});

it('alur penuh di layar: usulan, keputusan, sertifikat bernomor, PDF, naik fase hanya yang lulus', function () {
    phaseAssess($this, $this->fiqih, $this->final, '2026-10-20', [$this->ahmad->id => '88', $this->budi->id => '55']);
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
    $this->actingAs($this->admin);

    Livewire::test(CurriculumBoard::class)->call('openEvaluation', $this->room->uuid)
        ->call('evaluate')->assertSee('Ahmad')
        ->call('suggest')->call('decide')->assertSee('Lulus')->assertSee('Belum lulus')
        ->call('certify')->assertSee('001/SRT/PRK/2026')
        ->set('promoteTo', $this->room->uuid)->call('promote')->assertHasErrors('promoteTo')
        ->set('promoteTo', $this->nextRoom->uuid)->call('promote')->assertHasNoErrors();

    expect($this->ahmad->fresh()->classroom_id)->toBe($this->nextRoom->id)
        ->and($this->budi->fresh()->classroom_id)->toBe($this->room->id)
        ->and(PhaseResult::where('student_id', $this->budi->id)->value('certificate_no'))->toBeNull();

    $this->get(route('staff.curriculum.certificate', $this->room))->assertOk()->assertHeader('content-type', 'application/pdf');
    expect($captured->html)->toContain('Ahmad')->toContain('Masa Perkenalan')->toContain('001/SRT/PRK/2026')->not->toContain('Budi');

    $this->get(route('staff.curriculum.report', $this->room))->assertOk();
    expect($captured->html)->toContain('Budi')->toContain('Belum lulus');
});

it('hasil yang sudah ditetapkan tidak berubah saat dihitung ulang; kelas tanpa fase ditolak', function () {
    phaseAssess($this, $this->fiqih, $this->final, '2026-10-20', [$this->ahmad->id => '88', $this->budi->id => '90']);
    $phases = app(ManagePhases::class);
    $phases->evaluate($this->room);
    $result = PhaseResult::where('student_id', $this->ahmad->id)->first();
    $phases->decide($this->room, [$result->uuid => 'passed'], $this->admin);

    phaseAssess($this, $this->fiqih, $this->final, '2026-10-21', [$this->ahmad->id => '10']);
    expect($phases->evaluate($this->room))->toBe(1)->and($result->fresh()->final_score)->toEqual(88);

    expect(fn () => $phases->evaluate(Classroom::create(['name' => 'Tanpa Fase'])))->toThrow(AcademicException::class, 'belum ditempatkan');
});

it('layar kurikulum: fase baru dengan validasi; ustadz tidak boleh; modul mati menutup', function () {
    $this->actingAs($this->admin);
    Livewire::test(CurriculumBoard::class)->call('newPhase', $this->curriculum->uuid)
        ->set('p_name', 'Fase Lanjut')->set('p_code', 'L J')->set('p_weeks', '0')->call('savePhase')->assertHasErrors(['p_code', 'p_weeks'])
        ->set('p_code', 'LNJ')->set('p_weeks', '24')->call('savePhase')->assertHasNoErrors();
    expect(CurriculumPhase::where('code', 'LNJ')->value('sort_order'))->toBe(3);

    $this->actingAs($this->ustadz)->get(route('staff.curriculum.index'))->assertForbidden();
    Module::where('module_key', 'curriculum')->update(['is_enabled' => false]);
    app(ModuleRegistry::class)->forget();
    $this->actingAs($this->admin)->get(route('staff.curriculum.index'))->assertForbidden();
});

it('sepuluh desain sertifikat bisa dipratinjau; desain fase dan nama Arab dipakai di PDF beserta fontnya', function () {
    $this->actingAs($this->admin);
    foreach (array_keys(CertificateDesigns::ALL) as $key) {
        $this->get(route('staff.curriculum.design', $key))->assertOk()->assertSee('d-'.$key, false)->assertSee('شَهَادَة');
    }
    $this->get(route('staff.curriculum.design', 'tidak-ada'))->assertNotFound();

    Livewire::test(CurriculumBoard::class, ['tab' => 'desain'])->assertSee('Kufi Modern')
        ->set('school_name_ar', 'مَعْهَدُ الْمُخْتَارِ')->call('saveDesignSettings')->assertHasNoErrors()
        ->call('useDesign', 'tinta')->assertSet('default_design', 'tinta');

    phaseAssess($this, $this->fiqih, $this->final, '2026-10-20', [$this->ahmad->id => '90', $this->budi->id => '90']);
    $phases = app(ManagePhases::class);
    $phases->evaluate($this->room);
    $phases->decide($this->room, PhaseResult::pluck('uuid')->mapWithKeys(fn ($uuid) => [$uuid => 'passed'])->all(), $this->admin);
    $phases->certify($this->room);

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

    $this->get(route('staff.curriculum.certificate', $this->room))->assertOk();
    expect($captured->html)->toContain('d-tinta')->toContain('مَعْهَدُ الْمُخْتَارِ')->toContain("font-family:'Aref Ruqaa'")->toContain('data:font/woff2;base64,');

    $this->intro->update(['certificate_design' => 'kufi', 'certificate_title_ar' => 'شَهَادَةُ التَّعَارُفِ']);
    $this->get(route('staff.curriculum.certificate', $this->room).'?santri='.$this->ahmad->uuid)->assertOk();
    expect($captured->html)->toContain('d-kufi')->toContain('شَهَادَةُ التَّعَارُفِ')->toContain('Ahmad')->not->toContain('Budi');
});

it('logo sertifikat bisa lebih dari satu, paling banyak empat, dan ikut tercetak', function () {
    Storage::fake('local');
    $this->actingAs($this->admin);
    $page = Livewire::test(CurriculumBoard::class, ['tab' => 'desain']);
    foreach (range(1, 4) as $i) {
        $page->set('newLogo', UploadedFile::fake()->image("logo{$i}.png", 200, 200))->call('addLogo')->assertHasNoErrors();
    }
    $page->set('newLogo', UploadedFile::fake()->image('logo5.png', 200, 200))->call('addLogo')->assertHasErrors('newLogo')
        ->call('moveLogo', 0, 1)->call('removeLogo', 3);

    expect(CertificateDesigns::settings()['logos'])->toHaveCount(3)->and(CertificateDesigns::logoData())->toHaveCount(3);
    $this->get(route('staff.curriculum.logo', 0))->assertOk();
    $this->get(route('staff.curriculum.design', 'mihrab'))->assertOk()->assertSee('class="logos', false);
});

it('tanpa logo khusus, sertifikat memakai logo pesantren dari Pengaturan Profil', function () {
    Storage::fake('public');
    Storage::disk('public')->put('logo/pesantren.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII='));
    SchoolProfile::query()->updateOrCreate([], ['name' => 'Pesantren Uji', 'logo_path' => 'logo/pesantren.png']);

    expect(CertificateDesigns::settings()['logos'])->toBe([])
        ->and(CertificateDesigns::logoData())->toHaveCount(1)
        ->and(CertificateDesigns::logoData()[0])->toStartWith('data:image/png;base64,');

    $this->actingAs($this->admin)->get(route('staff.curriculum.design', 'kufi'))->assertOk()->assertSee('data:image/png;base64,', false);
});

it('sertifikat dua sisi: belakangnya hasil ujian bila dipilih di fase', function () {
    phaseAssess($this, $this->fiqih, $this->final, '2026-10-20', [$this->ahmad->id => '90', $this->budi->id => '90']);
    $phases = app(ManagePhases::class);
    $phases->evaluate($this->room);
    $phases->decide($this->room, PhaseResult::pluck('uuid')->mapWithKeys(fn ($uuid) => [$uuid => 'passed'])->all(), $this->admin);
    $phases->certify($this->room);
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
    $this->actingAs($this->admin);

    $this->get(route('staff.curriculum.certificate', $this->room))->assertOk();
    expect($captured->html)->not->toContain('كَشْفُ الدَّرَجَاتِ');

    Livewire::test(CurriculumBoard::class)->call('editPhase', $this->intro->uuid)->set('p_back', true)->call('savePhase')->assertHasNoErrors();
    $this->get(route('staff.curriculum.certificate', $this->room))->assertOk();
    expect($captured->html)->toContain('كَشْفُ الدَّرَجَاتِ')->toContain('Fiqih')->and(substr_count($captured->html, 'class="sheet back"'))->toBe(2);
});

it('portal: wali dan santri melihat rapor fase dan mengunduh syahadah miliknya sendiri saja', function () {
    Module::whereIn('module_key', ['portal_wali', 'portal_santri'])->update(['is_enabled' => true]);
    app(ModuleRegistry::class)->forget();
    $this->app->instance(PdfRenderer::class, new class implements PdfRenderer
    {
        public function render(string $html, string $format = 'A4'): string
        {
            return '%PDF'.$html;
        }
    });
    $passed = PhaseResult::create(['student_id' => $this->ahmad->id, 'phase_id' => $this->intro->id, 'classroom_id' => $this->room->id, 'started_on' => '2026-08-01', 'ended_on' => '2026-10-23',
        'scores' => [$this->fiqih->id => ['name' => 'Fiqih', 'score' => 88]], 'final_score' => 88, 'status' => 'passed', 'certificate_no' => '001/SRT/PRK/2026', 'certified_at' => now()]);
    $running = PhaseResult::create(['student_id' => $this->ahmad->id, 'phase_id' => $this->basic->id, 'started_on' => '2026-11-01', 'ended_on' => '2027-04-17', 'status' => 'pending']);
    $other = PhaseResult::create(['student_id' => $this->budi->id, 'phase_id' => $this->intro->id, 'started_on' => '2026-08-01', 'ended_on' => '2026-10-23', 'final_score' => 55, 'status' => 'failed']);

    $guardianUser = tap(User::create(['name' => 'Wali Ahmad', 'email' => 'wali@uji.test', 'type' => 'guardian']))->assignRole('wali');
    $guardian = Guardian::create(['user_id' => $guardianUser->id, 'name' => 'Wali Ahmad', 'phone_wa' => '6281200000009']);
    $guardian->students()->attach($this->ahmad->id, ['relation' => 'ayah', 'is_primary' => true, 'is_payer' => true, 'can_receive_wa' => true]);

    $this->actingAs($guardianUser)->get(route('wali.progress', ['segment' => 'fase']))->assertOk()
        ->assertSee('Rapor Fase')->assertSee('Masa Perkenalan')->assertSee('Lulus')->assertSee('Unduh Syahadah')->assertDontSee('Fase Dasar');
    $this->get(route('phase-docs.report', $passed))->assertOk()->assertSee('Ahmad');
    $this->get(route('phase-docs.certificate', $passed))->assertOk()->assertSee('001/SRT/PRK/2026');
    $this->get(route('phase-docs.report', $running))->assertNotFound();
    $this->get(route('phase-docs.report', $other))->assertForbidden();
    $this->get(route('phase-docs.certificate', $other))->assertForbidden();

    $studentUser = tap(User::create(['name' => 'Budi', 'username' => 'B1', 'type' => 'student']))->assignRole('santri');
    $this->budi->update(['user_id' => $studentUser->id]);
    $this->actingAs($studentUser)->get(route('phase-docs.report', $other))->assertOk()->assertSee('Belum lulus');
    $this->get(route('phase-docs.certificate', $other))->assertNotFound();
    $this->get(route('phase-docs.report', $passed))->assertForbidden();

    $this->actingAs($this->admin)->get(route('phase-docs.report', $passed))->assertForbidden();
});
