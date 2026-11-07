<?php

use App\Modules\Academic\Models\Assessment;
use App\Modules\Academic\Models\Grade;
use App\Modules\Academic\Models\GradeComponent;
use App\Modules\Academic\Models\Subject;
use App\Modules\Core\Models\AcademicYear;
use App\Modules\Core\Models\Classroom;
use App\Modules\Core\Models\Module;
use App\Modules\Core\Models\Student;
use App\Modules\Core\Models\Teacher;
use App\Modules\Core\Models\Term;
use App\Modules\Core\Models\User;
use App\Modules\Core\Support\ModuleRegistry;
use App\Modules\Exam\Actions\GradeAttempt;
use App\Modules\Exam\Actions\ManageExam;
use App\Modules\Exam\Actions\RunAttempt;
use App\Modules\Exam\Actions\SaveQuestion;
use App\Modules\Exam\Livewire\ExamList;
use App\Modules\Exam\Livewire\ExamMonitor;
use App\Modules\Exam\Livewire\ExamPrepare;
use App\Modules\Exam\Livewire\ExamResults;
use App\Modules\Exam\Livewire\ExamWizard;
use App\Modules\Exam\Livewire\QuestionBanks;
use App\Modules\Exam\Livewire\StudentExams;
use App\Modules\Exam\Models\Exam;
use App\Modules\Exam\Models\ExamAnswer;
use App\Modules\Exam\Models\ExamAttempt;
use App\Modules\Exam\Models\ExamTarget;
use App\Modules\Exam\Models\Question;
use App\Modules\Exam\Models\QuestionBank;
use App\Modules\Exam\Support\AttemptGone;
use App\Modules\Exam\Support\ExamException;
use App\Modules\Exam\Support\SafeHtml;
use App\Modules\Finance\Exports\ArrayExport;
use Database\Seeders\ModuleSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;

beforeEach(function () {
    (new ModuleSeeder)->run();
    (new RoleSeeder)->run();
    Module::where('module_key', 'exam')->update(['is_enabled' => true]);
    app(ModuleRegistry::class)->forget();
    Storage::fake('local');
    $this->travelTo('2026-11-02 01:00:00');

    $year = AcademicYear::create(['name' => '2026', 'start_date' => '2026-07-01', 'end_date' => '2027-06-30', 'is_active' => true]);
    $this->term = Term::create(['academic_year_id' => $year->id, 'name' => 'G', 'start_date' => '2026-07-01', 'end_date' => '2026-12-31', 'is_active' => true]);
    $this->classroom = Classroom::create(['name' => '7A']);
    $this->otherClass = Classroom::create(['name' => '8A']);
    $this->subject = Subject::create(['code' => 'F', 'name' => 'Fiqih']);

    $this->ustadzUser = tap(User::create(['name' => 'Ustadz', 'email' => 'u@uji.test', 'type' => 'staff']))->assignRole('ustadz');
    $this->teacher = Teacher::create(['user_id' => $this->ustadzUser->id, 'name' => 'Ustadz']);
    $this->otherUstadz = tap(User::create(['name' => 'Lain', 'email' => 'u2@uji.test', 'type' => 'staff']))->assignRole('ustadz');
    Teacher::create(['user_id' => $this->otherUstadz->id, 'name' => 'Lain']);
    $this->admin = tap(User::create(['name' => 'Akademik', 'email' => 'a@uji.test', 'type' => 'staff']))->assignRole('admin_akademik');

    $this->studentUser = tap(User::create(['name' => 'Ahmad', 'username' => 'A1', 'type' => 'student']))->assignRole('santri');
    $this->student = Student::create(['nis' => 'A1', 'name' => 'Ahmad', 'gender' => 'L', 'classroom_id' => $this->classroom->id, 'user_id' => $this->studentUser->id]);
    $this->outsiderUser = tap(User::create(['name' => 'Budi', 'username' => 'B1', 'type' => 'student']))->assignRole('santri');
    $this->outsider = Student::create(['nis' => 'B1', 'name' => 'Budi', 'gender' => 'L', 'classroom_id' => $this->otherClass->id, 'user_id' => $this->outsiderUser->id]);

    $this->bank = QuestionBank::create(['name' => 'Bank', 'subject_id' => $this->subject->id, 'owner_teacher_id' => $this->teacher->id]);
    $save = app(SaveQuestion::class);
    $this->mcq = $save->handle($this->bank, ['type' => 'mcq', 'body' => 'Rukun islam ada', 'options' => ['Tiga', 'Lima'], 'answer_key' => 'B', 'points' => 1]);
    $this->multi = $save->handle($this->bank, ['type' => 'multi', 'body' => 'Pilih yang benar', 'options' => ['X', 'Y', 'Z'], 'answer_key' => ['A', 'C'], 'points' => 1]);
    $this->truth = $save->handle($this->bank, ['type' => 'true_false', 'body' => 'Sholat wajib lima', 'answer_key' => 'true', 'points' => 1]);
    $this->short = $save->handle($this->bank, ['type' => 'short', 'body' => 'Kitab suci', 'answer_key' => 'Al Quran|القرآن', 'points' => 1, 'direction' => 'ltr']);
    $this->essay = $save->handle($this->bank, ['type' => 'essay', 'body' => 'Jelaskan wudhu', 'points' => 6]);

    $this->exam = Exam::create(['title' => 'UTS', 'subject_id' => $this->subject->id, 'term_id' => $this->term->id, 'duration_minutes' => 60, 'start_at' => '2026-11-02 00:30:00', 'end_at' => '2026-11-02 03:00:00', 'status' => 'published', 'created_by' => $this->ustadzUser->id, 'show_result_mode' => 'immediately']);
    foreach ([$this->mcq, $this->multi, $this->truth, $this->short, $this->essay] as $order => $question) {
        $this->exam->questions()->attach($question->id, ['sort_order' => $order]);
    }
    ExamTarget::create(['exam_id' => $this->exam->id, 'classroom_id' => $this->classroom->id]);
});

function startAttempt($context, ?string $code = null): ExamAttempt
{
    return app(RunAttempt::class)->start($context->exam, $context->student, $code, '127.0.0.1', 'uji');
}

function answer(ExamAttempt $attempt, array $rows, ?string $token = null): array
{
    return app(RunAttempt::class)->saveAnswers($attempt, $token ?? $attempt->session_token, $rows);
}

describe('bank soal', function () {
    it('kunci divalidasi terhadap opsi; soal kosong ditolak; skrip dibuang', function () {
        $save = app(SaveQuestion::class);

        expect(fn () => $save->handle($this->bank, ['type' => 'mcq', 'body' => 'a', 'options' => ['x', 'y'], 'answer_key' => 'C']))->toThrow(ExamException::class, 'kunci')
            ->and(fn () => $save->handle($this->bank, ['type' => 'mcq', 'body' => 'a', 'options' => ['x'], 'answer_key' => 'A']))->toThrow(ExamException::class, 'dua opsi')
            ->and(fn () => $save->handle($this->bank, ['type' => 'mcq', 'body' => '<script>x</script>', 'options' => ['x', 'y'], 'answer_key' => 'A']))->not->toThrow(ExamException::class)
            ->and(fn () => $save->handle($this->bank, ['type' => 'short', 'body' => 'a']))->toThrow(ExamException::class, 'jawaban')
            ->and(fn () => $save->handle($this->bank, ['type' => 'x', 'body' => 'a']))->toThrow(ExamException::class, 'Jenis');

        expect(SafeHtml::clean('<p onclick="x()">a<script>alert(1)</script><img src=x onerror=y><b style="c">b</b></p>'))->toBe('<p>aalert(1)<b>b</b></p>');
    });

    it('ustadz hanya melihat bank miliknya; form dan impor per baris', function () {
        $path = 'impor-soal.xlsx';
        Excel::store(new ArrayExport(QuestionBanks::HEADINGS, [
            ['pg', 'Dua tambah dua', 'ltr', '3', '4', '', '', '', 'B', 2, 1, 'hitung'],
            ['bs', 'Air suci', '', '', '', '', '', '', 'benar', '', '', ''],
            ['pg', 'Tanpa kunci', '', 'a', 'b', '', '', '', '', '', '', ''],
            ['aneh', 'x', '', '', '', '', '', '', '', '', '', ''],
        ]), $path, 'local');
        $this->actingAs($this->ustadzUser);

        $component = Livewire::test(QuestionBanks::class)->assertSee('Bank')
            ->set('bankUuid', $this->bank->uuid)->assertSee('Rukun islam ada')
            ->set('importFile', UploadedFile::fake()->createWithContent('impor.xlsx', Storage::disk('local')->get($path)))
            ->call('import')->assertSee('2 soal masuk')->assertSee('Baris 4')->assertSee('Baris 5');

        expect(Question::where('body', 'Dua tambah dua')->first()->answer_key)->toBe(['B'])
            ->and(Question::where('body', 'Dua tambah dua')->first()->tags)->toBe(['hitung'])
            ->and(Question::where('body', 'Air suci')->first()->answer_key)->toBe(['true']);

        $component->call('create')->set('type', 'mcq')->set('body', 'Baru')->set('options', ['p', 'q', '', ''])->set('singleKey', 'A')->call('save')->assertHasNoErrors();
        $component->call('create')->set('body', 'Salah')->set('options', ['p', 'q', '', ''])->call('save')->assertHasErrors('body');

        $this->actingAs($this->otherUstadz);
        Livewire::test(QuestionBanks::class)->assertDontSee('Rukun')->set('bankUuid', $this->bank->uuid)->assertDontSee('Rukun islam ada')->call('create')->assertForbidden();
    });
});

describe('mulai ujian', function () {
    it('batas waktu: contoh PRD, durasi 60, jendela tutup lebih dulu', function () {
        $this->exam->update(['end_at' => '2026-11-02 01:25:00']);

        expect(startAttempt($this)->deadline_at->toIso8601String())->toBe('2026-11-02T01:25:00+00:00');

        $this->exam->update(['end_at' => '2026-11-02 05:00:00']);
        $this->student->refresh();
        ExamAttempt::query()->delete();
        expect(startAttempt($this)->deadline_at->toIso8601String())->toBe('2026-11-02T02:00:00+00:00');
    });

    it('menolak: bukan sasaran, belum dibuka, sudah lewat, draf, kode salah, batas percobaan', function () {
        $run = app(RunAttempt::class);

        expect(fn () => $run->start($this->exam, $this->outsider, null, null, null))->toThrow(ExamException::class, 'bukan untuk');

        $this->exam->update(['start_at' => '2026-11-02 02:00:00']);
        expect(fn () => startAttempt($this))->toThrow(ExamException::class, 'belum dimulai');

        $this->exam->update(['start_at' => '2026-11-01 00:00:00', 'end_at' => '2026-11-01 02:00:00']);
        expect(fn () => startAttempt($this))->toThrow(ExamException::class, 'berakhir');

        $this->exam->update(['status' => 'draft', 'end_at' => '2026-11-03 00:00:00']);
        expect(fn () => startAttempt($this))->toThrow(ExamException::class, 'tidak dibuka');

        $this->exam->update(['status' => 'published', 'access_code' => 'ABC']);
        expect(fn () => startAttempt($this, 'XYZ'))->toThrow(ExamException::class, 'Kode akses');

        $attempt = startAttempt($this, ' ABC ');
        $run->submit($attempt, $attempt->session_token);
        expect(fn () => startAttempt($this, 'ABC'))->toThrow(ExamException::class, 'percobaan');
    });

    it('urutan acak tersimpan di attempt; kunci tidak pernah terkirim', function () {
        $this->exam->update(['shuffle_questions' => true, 'shuffle_options' => true]);
        $attempt = startAttempt($this);
        $payload = app(RunAttempt::class)->publicQuestions($attempt);

        expect($attempt->question_order)->toHaveCount(5)
            ->and(array_column($payload, 'id'))->toBe($attempt->question_order)
            ->and(json_encode($payload))->not->toContain('answer_key')->not->toContain('Al Quran');

        $this->actingAs($this->studentUser);
        $html = $this->get(route('exam.take', $attempt))->assertOk()->getContent();
        expect($html)->toContain('Rukun islam ada')->not->toContain('answer_key')->not->toContain('Al Quran');
    });

    it('perangkat kedua mengambil alih; perangkat lama diberi tahu', function () {
        $attempt = startAttempt($this);
        $oldToken = $attempt->session_token;

        $second = startAttempt($this);

        expect($second->id)->toBe($attempt->id)->and($second->session_token)->not->toBe($oldToken);
        expect(fn () => answer($attempt->fresh(), [['question_id' => $this->mcq->id, 'answer' => 'B', 'client_seq' => 1]], $oldToken))->toThrow(AttemptGone::class);

        $this->actingAs($this->studentUser);
        $this->postJson(route('exam.answers', $attempt), ['token' => $oldToken, 'batch' => []])->assertStatus(409)->assertJson(['reason' => 'taken_over', 'message' => 'Ujian dilanjutkan di perangkat lain.']);
    });
});

describe('autosave', function () {
    it('kirim ulang idempoten; urutan terbalik tidak menimpa; soal luar diabaikan', function () {
        $attempt = startAttempt($this);

        expect(answer($attempt, [['question_id' => $this->mcq->id, 'answer' => 'A', 'client_seq' => 5]])['max_client_seq'])->toBe(5);
        answer($attempt, [['question_id' => $this->mcq->id, 'answer' => 'B', 'client_seq' => 3]]);
        expect(ExamAnswer::first()->answer['value'])->toBe('A')->and(ExamAnswer::first()->client_seq)->toBe(5);

        answer($attempt, [['question_id' => $this->mcq->id, 'answer' => 'B', 'client_seq' => 6]]);
        answer($attempt, [['question_id' => $this->mcq->id, 'answer' => 'B', 'client_seq' => 6]]);
        expect(ExamAnswer::first()->answer['value'])->toBe('B')->and(ExamAnswer::count())->toBe(1);

        $foreign = app(SaveQuestion::class)->handle($this->bank, ['type' => 'essay', 'body' => 'Asing']);
        answer($attempt, [['question_id' => $foreign->id, 'answer' => 'x', 'client_seq' => 7], ['question_id' => $this->truth->id, 'answer' => 'true', 'client_seq' => 0]]);
        expect(ExamAnswer::count())->toBe(1);
    });

    it('santri lain tidak bisa menulis; server mengembalikan jam dan batas', function () {
        $attempt = startAttempt($this);
        $this->actingAs($this->outsiderUser);
        $this->postJson(route('exam.answers', $attempt), ['token' => $attempt->session_token, 'batch' => []])->assertForbidden();
        $this->get(route('exam.take', $attempt))->assertForbidden();

        $this->actingAs($this->studentUser);
        $this->postJson(route('exam.answers', $attempt), ['token' => $attempt->session_token, 'batch' => [['question_id' => $this->mcq->id, 'answer' => 'B', 'client_seq' => 1]]])
            ->assertOk()->assertJson(['ok' => true, 'max_client_seq' => 1, 'deadline_at' => '2026-11-02T02:00:00.000Z']);
    });

    it('waktu habis saat offline: jawaban masih diterima 10 menit lalu dinilai ulang; lewat itu ditolak', function () {
        $attempt = startAttempt($this);
        answer($attempt, [['question_id' => $this->mcq->id, 'answer' => 'A', 'client_seq' => 1]]);

        $this->travelTo('2026-11-02 02:01:00');
        expect(app(RunAttempt::class)->autoSubmitExpired())->toBe(1);
        expect($attempt->fresh()->wasAutoSubmitted())->toBeTrue()->and($attempt->fresh()->isOpen())->toBeFalse()->and($attempt->fresh()->score)->toBe(0.0);

        answer($attempt->fresh(), [['question_id' => $this->mcq->id, 'answer' => 'B', 'client_seq' => 2]]);
        expect(ExamAnswer::first()->is_correct)->toBeTrue()
            ->and($attempt->fresh()->score)->toBe(10.0)
            ->and($attempt->events()->where('type', 'reconnect')->exists())->toBeTrue();

        $this->travelTo('2026-11-02 02:11:00');
        expect(fn () => answer($attempt->fresh(), [['question_id' => $this->mcq->id, 'answer' => 'A', 'client_seq' => 3]]))->toThrow(AttemptGone::class);
        expect(ExamAnswer::first()->answer['value'])->toBe('B');
    });

    it('sudah dikumpulkan sebelum batas: tidak bisa menulis lagi', function () {
        $attempt = startAttempt($this);
        app(RunAttempt::class)->submit($attempt, $attempt->session_token);

        expect(fn () => answer($attempt->fresh(), [['question_id' => $this->mcq->id, 'answer' => 'B', 'client_seq' => 1]]))->toThrow(AttemptGone::class, 'dikumpulkan');

        // Tenggang 10 menit hanya untuk yang terkumpul otomatis, bukan yang sudah mengumpulkan sendiri.
        $this->travelTo('2026-11-02 02:05:00');
        expect(fn () => answer($attempt->fresh(), [['question_id' => $this->mcq->id, 'answer' => 'B', 'client_seq' => 2]]))->toThrow(AttemptGone::class, 'dikumpulkan');
        expect(ExamAnswer::count())->toBe(0);
    });

    it('auto-submit hanya yang lewat batas; halaman yang dibuka setelah batas mengumpulkan', function () {
        $attempt = startAttempt($this);
        expect(app(RunAttempt::class)->autoSubmitExpired())->toBe(0);

        $this->travelTo('2026-11-02 02:00:01');
        $this->actingAs($this->studentUser);
        $this->get(route('exam.take', $attempt))->assertRedirect(route('exam.done', $attempt));
        expect($attempt->fresh()->isOpen())->toBeFalse()->and($attempt->fresh()->wasAutoSubmitted())->toBeTrue();
        $this->get(route('exam.done', $attempt))->assertSee('otomatis saat waktu habis');
    });

    it('peristiwa pindah layar dihitung dan tampil di monitor', function () {
        $attempt = startAttempt($this);
        $this->actingAs($this->studentUser);
        $this->postJson(route('exam.events', $attempt), ['events' => ['blur', 'focus', 'blur', 'aneh']])->assertOk();

        expect($attempt->fresh()->blur_count)->toBe(2)->and($attempt->events()->count())->toBe(3);

        $this->actingAs($this->ustadzUser);
        Livewire::test(ExamMonitor::class, ['exam' => $this->exam])->assertSee('Ahmad')->assertSee('Mengerjakan');
    });
});

describe('penilaian', function () {
    it('otomatis: pilihan, kompleks himpunan sama persis, isian abaikan spasi kapital harakat', function () {
        expect(GradeAttempt::isCorrect($this->mcq, 'B'))->toBeTrue()
            ->and(GradeAttempt::isCorrect($this->mcq, 'A'))->toBeFalse()
            ->and(GradeAttempt::isCorrect($this->multi, ['C', 'A']))->toBeTrue()
            ->and(GradeAttempt::isCorrect($this->multi, ['A']))->toBeFalse()
            ->and(GradeAttempt::isCorrect($this->multi, ['A', 'B', 'C']))->toBeFalse()
            ->and(GradeAttempt::isCorrect($this->truth, 'true'))->toBeTrue()
            ->and(GradeAttempt::isCorrect($this->short, '  al   quran '))->toBeTrue()
            ->and(GradeAttempt::isCorrect($this->short, 'القُرْآن'))->toBeTrue()
            ->and(GradeAttempt::isCorrect($this->short, 'Injil'))->toBeFalse();
    });

    it('contoh PRD: 40 PG dan 2 esai 10 poin, benar 32, esai 7 dan 8 menghasilkan 78,3', function () {
        $exam = Exam::create(['title' => 'Besar', 'duration_minutes' => 60, 'start_at' => '2026-11-02 00:30:00', 'end_at' => '2026-11-02 03:00:00', 'status' => 'published']);
        ExamTarget::create(['exam_id' => $exam->id, 'classroom_id' => $this->classroom->id]);
        $save = app(SaveQuestion::class);
        $mcqs = collect(range(1, 40))->map(fn ($i) => $save->handle($this->bank, ['type' => 'mcq', 'body' => "S{$i}", 'options' => ['x', 'y'], 'answer_key' => 'A']));
        $essays = collect(range(1, 2))->map(fn ($i) => $save->handle($this->bank, ['type' => 'essay', 'body' => "E{$i}", 'points' => 10]));
        foreach ($mcqs->concat($essays) as $order => $question) {
            $exam->questions()->attach($question->id, ['sort_order' => $order]);
        }

        $run = app(RunAttempt::class);
        $attempt = $run->start($exam, $this->student, null, null, null);
        $batch = $mcqs->map(fn ($question, $i) => ['question_id' => $question->id, 'answer' => $i < 32 ? 'A' : 'B', 'client_seq' => $i + 1])->all();
        $batch[] = ['question_id' => $essays[0]->id, 'answer' => 'jawab', 'client_seq' => 50];
        $batch[] = ['question_id' => $essays[1]->id, 'answer' => 'jawab', 'client_seq' => 51];
        $run->saveAnswers($attempt, $attempt->session_token, $batch);
        $run->submit($attempt, $attempt->session_token);

        expect($attempt->fresh()->status)->toBe('submitted');

        $this->actingAs($this->ustadzUser);
        $exam->update(['created_by' => $this->ustadzUser->id]);
        $component = Livewire::test(ExamResults::class, ['exam' => $exam]);
        foreach ([7, 8] as $score) {
            $answer = ExamAnswer::whereHas('question', fn ($query) => $query->where('type', 'essay'))->whereNull('graded_by')->orderBy('id')->first();
            $component->set('essayScore', (string) $score)->call('gradeEssay', $answer->uuid)->assertHasNoErrors();
        }

        expect($attempt->fresh()->status)->toBe('graded')->and(round($attempt->fresh()->score, 1))->toBe(78.3);
    });

    it('esai: poin di luar rentang ditolak; ustadz lain tidak bisa; jawaban kosong otomatis nol', function () {
        $attempt = startAttempt($this);
        answer($attempt, [['question_id' => $this->essay->id, 'answer' => 'Wudhu adalah...', 'client_seq' => 1], ['question_id' => $this->mcq->id, 'answer' => 'B', 'client_seq' => 2]]);
        app(RunAttempt::class)->submit($attempt, $attempt->session_token);
        $answer = ExamAnswer::where('question_id', $this->essay->id)->first();

        $this->actingAs($this->otherUstadz)->get(route('ustadz.exams.results', $this->exam))->assertForbidden();

        $this->actingAs($this->ustadzUser);
        Livewire::test(ExamResults::class, ['exam' => $this->exam])->assertSee('Wudhu adalah')
            ->set('essayScore', '7')->call('gradeEssay', $answer->uuid)->assertHasErrors('essayScore')
            ->set('essayScore', '5')->call('gradeEssay', $answer->uuid)->assertHasNoErrors()->assertDontSee('Koreksi esai');

        expect($attempt->fresh()->status)->toBe('graded')->and($attempt->fresh()->score)->toBe(60.0);

        $this->actingAs($this->studentUser);
        $this->get(route('exam.done', $attempt))->assertSee('60');
    });

    it('hasil disembunyikan sesuai pengaturan', function () {
        $this->exam->update(['show_result_mode' => 'after_close']);
        $this->exam->questions()->detach($this->essay->id);
        $attempt = startAttempt($this);
        answer($attempt, [['question_id' => $this->mcq->id, 'answer' => 'B', 'client_seq' => 1]]);
        app(RunAttempt::class)->submit($attempt, $attempt->session_token);
        $this->actingAs($this->studentUser);

        $this->get(route('exam.done', $attempt))->assertSee('Hasil akan diumumkan');

        app(ManageExam::class)->close($this->exam);
        $this->get(route('exam.done', $attempt))->assertSee('25');
    });

    it('kirim ke buku nilai: nilai masuk, kirim ulang tidak menggandakan', function () {
        $component = GradeComponent::create(['name' => 'UTS', 'weight' => 100]);
        $this->exam->questions()->detach($this->essay->id);
        $attempt = startAttempt($this);
        answer($attempt, [['question_id' => $this->mcq->id, 'answer' => 'B', 'client_seq' => 1]]);
        app(RunAttempt::class)->submit($attempt, $attempt->session_token);
        $this->actingAs($this->ustadzUser);

        $page = Livewire::test(ExamResults::class, ['exam' => $this->exam])
            ->call('push')->assertHasErrors('push_classroom')
            ->set('push_classroom', (string) $this->classroom->id)->set('push_component', (string) $component->id)
            ->call('push')->assertHasNoErrors();
        $page->call('push');

        expect(Assessment::where('exam_id', $this->exam->id)->count())->toBe(1)
            ->and(Grade::where('student_id', $this->student->id)->value('score'))->toBe(25.0)
            ->and(Grade::count())->toBe(1);
    });
});

describe('penyusunan dan layar', function () {
    it('wizard: langkah divalidasi, soal acak per tag, terbit butuh soal dan sasaran', function () {
        app(SaveQuestion::class)->handle($this->bank, ['type' => 'essay', 'body' => 'Bertag', 'tags' => 'bab1']);
        $this->actingAs($this->ustadzUser);

        Livewire::test(ExamList::class)->call('createExam');
        $exam = Exam::where('title', 'Ujian baru')->first();
        expect($exam->created_by)->toBe($this->ustadzUser->id);

        $wizard = Livewire::test(ExamWizard::class, ['exam' => $exam])
            ->set('title', 'UAS')->set('subject_id', (string) $this->subject->id)->set('start_at', '2026-11-02T10:00')->set('end_at', '2026-11-02T09:00')
            ->call('next')->assertHasErrors('end_at')->assertSet('step', 1)
            ->set('end_at', '2026-11-02T12:00')->call('next')->assertHasNoErrors()->assertSet('step', 2)
            ->set('bank_id', (string) $this->bank->id)->set('random_tag', 'bab1')->set('random_count', '5')->call('addRandom')->assertHasErrors('random_count');

        expect($exam->questions()->count())->toBe(1);

        $wizard->call('next')->call('next')->assertSet('step', 4)
            ->call('publish')->assertHasErrors('publish')->assertSee('Belum ada sasaran')
            ->set('classrooms', [(string) $this->classroom->id])
            ->call('publish')->assertHasNoErrors();

        expect($exam->fresh()->status)->toBe('published')->and($exam->fresh()->start_at->toIso8601String())->toBe('2026-11-02T03:00:00+00:00')
            ->and($this->studentUser->inAppNotifications()->where('title', 'Ujian baru: UAS')->exists())->toBeTrue();

        $this->get(route('ustadz.exams.edit', $exam))->assertNotFound();
    });

    it('ustadz lain tidak bisa membuka ujian; santri lain tidak bisa mempersiapkan', function () {
        $this->actingAs($this->otherUstadz);
        $this->get(route('ustadz.exams.monitor', $this->exam))->assertForbidden();
        Livewire::test(ExamList::class)->assertDontSee('UTS');

        $this->actingAs($this->outsiderUser)->get(route('santri.exams.prepare', $this->exam))->assertForbidden();
        Livewire::test(StudentExams::class)->assertDontSee('UTS');
    });

    it('santri: daftar, persiapan wajib centang, kode salah tampil, mulai mengarah ke halaman ujian', function () {
        $this->exam->update(['access_code' => 'ABC']);
        $this->actingAs($this->studentUser);

        Livewire::test(StudentExams::class)->assertSee('UTS');

        Livewire::test(ExamPrepare::class, ['exam' => $this->exam])
            ->call('start')->assertHasErrors('honest')
            ->set('honest', true)->set('code', 'SALAH')->call('start')->assertHasErrors('code')->assertSee('Kode akses salah')
            ->set('code', 'ABC')->call('start')->assertHasNoErrors()->assertRedirect(route('exam.take', ExamAttempt::first()));
    });

    it('monitor: ditutup mengumpulkan semua yang berjalan; terputus setelah 90 detik', function () {
        $attempt = startAttempt($this);
        $this->actingAs($this->ustadzUser);

        $this->travel(2)->minutes();
        Livewire::test(ExamMonitor::class, ['exam' => $this->exam])->assertSee('Terputus');

        Livewire::test(ExamMonitor::class, ['exam' => $this->exam])->call('closeExam')->assertSee('Selesai');
        expect($attempt->fresh()->isOpen())->toBeFalse()->and($attempt->fresh()->wasAutoSubmitted())->toBeTrue()->and($this->exam->fresh()->status)->toBe('closed');
    });

    it('rute: ekspor hasil, gambar soal hanya untuk peserta, modul mati', function () {
        $this->actingAs($this->ustadzUser)->get(route('exam.results-export', $this->exam))->assertOk();
        $this->actingAs($this->otherUstadz)->get(route('exam.results-export', $this->exam))->assertForbidden();

        Storage::disk('local')->put('question-media/a.jpg', 'x');
        $this->mcq->update(['media_path' => 'question-media/a.jpg']);
        $this->actingAs($this->studentUser)->get(route('exam.media', $this->mcq))->assertNotFound();
        startAttempt($this);
        $this->get(route('exam.media', $this->mcq))->assertOk();
        $this->actingAs($this->outsiderUser)->get(route('exam.media', $this->mcq))->assertNotFound();

        Module::where('module_key', 'exam')->update(['is_enabled' => false]);
        app(ModuleRegistry::class)->forget();
        $this->actingAs($this->studentUser)->get(route('santri.exams'))->assertForbidden();
    });
});
