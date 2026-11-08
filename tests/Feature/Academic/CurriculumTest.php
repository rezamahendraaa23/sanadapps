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
use App\Modules\Core\Models\AcademicYear;
use App\Modules\Core\Models\Classroom;
use App\Modules\Core\Models\Module;
use App\Modules\Core\Models\Student;
use App\Modules\Core\Models\Term;
use App\Modules\Core\Models\User;
use App\Modules\Core\Support\ModuleRegistry;
use App\Support\Pdf\PdfRenderer;
use Database\Seeders\ModuleSeeder;
use Database\Seeders\RoleSeeder;
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
