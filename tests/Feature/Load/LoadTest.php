<?php

use App\Modules\Academic\Models\Subject;
use App\Modules\Core\Models\AcademicYear;
use App\Modules\Core\Models\Classroom;
use App\Modules\Core\Models\Module;
use App\Modules\Core\Models\Student;
use App\Modules\Core\Models\Teacher;
use App\Modules\Core\Models\Term;
use App\Modules\Core\Models\User;
use App\Modules\Core\Support\ModuleRegistry;
use App\Modules\Exam\Actions\RunAttempt;
use App\Modules\Exam\Actions\SaveQuestion;
use App\Modules\Exam\Models\Exam;
use App\Modules\Exam\Models\ExamAnswer;
use App\Modules\Exam\Models\ExamAttempt;
use App\Modules\Exam\Models\ExamTarget;
use App\Modules\Exam\Models\QuestionBank;
use App\Modules\Pos\Actions\CheckoutOrder;
use App\Modules\Pos\Models\Merchant;
use App\Modules\Pos\Models\MerchantLedgerEntry;
use App\Modules\Pos\Models\MerchantProduct;
use App\Modules\Pos\Models\PosOrder;
use App\Modules\Wallet\Actions\IssueCard;
use App\Modules\Wallet\Actions\ReconcileWallets;
use App\Modules\Wallet\Actions\RecordCashTopup;
use App\Modules\Wallet\Models\Wallet;
use Database\Seeders\FinanceSeeder;
use Database\Seeders\ModuleSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Uji beban satu proses (PRD Sistem §11, UAT 6 dan 14): mengukur waktu proses server per
 * permintaan untuk 200 santri. Beban serentak sungguhan dijalankan di staging dengan
 * scripts/load/*.js (k6), lihat docs/RUNBOOK.md. Jalankan: pest --group=load
 */
const LOAD_STUDENTS = 200;

/**
 * @param  list<float>  $samples  milidetik
 * @return array{p50: float, p95: float, max: float, n: int}
 */
function loadStats(array $samples): array
{
    sort($samples);
    $at = fn (float $q) => $samples[(int) min(count($samples) - 1, floor($q * count($samples)))];

    return ['p50' => round($at(0.5), 1), 'p95' => round($at(0.95), 1), 'max' => round(end($samples), 1), 'n' => count($samples)];
}

function loadReport(string $label, array $stats, int $queries): void
{
    fwrite(STDERR, sprintf("\n[beban] %s: n=%d p50=%.1f ms p95=%.1f ms maks=%.1f ms, kueri per permintaan=%d\n", $label, $stats['n'], $stats['p50'], $stats['p95'], $stats['max'], $queries));
}

beforeEach(function () {
    (new ModuleSeeder)->run();
    (new RoleSeeder)->run();
    (new FinanceSeeder)->run();
    Module::whereIn('module_key', ['exam', 'wallet', 'card', 'pos'])->update(['is_enabled' => true]);
    app(ModuleRegistry::class)->forget();
    Storage::fake('local');
    Queue::fake();
});

it('ujian: 200 santri mulai, autosave 5 kali, kumpulkan; tidak ada jawaban hilang dan p95 autosave < 300 ms', function () {
    $this->travelTo('2026-11-02 01:00:00');
    $year = AcademicYear::create(['name' => '2026', 'start_date' => '2026-07-01', 'end_date' => '2027-06-30', 'is_active' => true]);
    $term = Term::create(['academic_year_id' => $year->id, 'name' => 'G', 'start_date' => '2026-07-01', 'end_date' => '2026-12-31', 'is_active' => true]);
    $classroom = Classroom::create(['name' => '7A']);
    $subject = Subject::create(['code' => 'F', 'name' => 'Fiqih']);
    $ustadz = tap(User::create(['name' => 'Ustadz', 'email' => 'u@uji.test', 'type' => 'staff']))->assignRole('ustadz');
    $teacher = Teacher::create(['user_id' => $ustadz->id, 'name' => 'Ustadz']);
    $bank = QuestionBank::create(['name' => 'Bank', 'subject_id' => $subject->id, 'owner_teacher_id' => $teacher->id]);
    $questions = collect(range(1, 5))->map(fn (int $i) => app(SaveQuestion::class)->handle($bank, ['type' => 'mcq', 'body' => 'Soal '.$i, 'options' => ['A', 'B', 'C', 'D'], 'answer_key' => 'B', 'points' => 1]));
    $exam = Exam::create(['title' => 'UAS', 'subject_id' => $subject->id, 'term_id' => $term->id, 'duration_minutes' => 60, 'start_at' => '2026-11-02 00:30:00', 'end_at' => '2026-11-02 03:00:00', 'status' => 'published', 'created_by' => $ustadz->id, 'show_result_mode' => 'immediately']);
    $questions->each(fn ($question, $order) => $exam->questions()->attach($question->id, ['sort_order' => $order]));
    ExamTarget::create(['exam_id' => $exam->id, 'classroom_id' => $classroom->id]);

    $students = collect(range(1, LOAD_STUDENTS))->map(function (int $i) use ($classroom) {
        $user = tap(User::create(['name' => 'Santri '.$i, 'username' => 'S'.$i, 'type' => 'student']))->assignRole('santri');

        return Student::create(['nis' => 'S'.$i, 'name' => 'Santri '.$i, 'gender' => 'L', 'classroom_id' => $classroom->id, 'user_id' => $user->id]);
    });

    $start = [];
    $attempts = $students->map(function (Student $student) use ($exam, &$start) {
        $t = hrtime(true);
        $attempt = app(RunAttempt::class)->start($exam, $student, null, '10.0.0.1', 'beban');
        $start[] = (hrtime(true) - $t) / 1e6;

        return $attempt;
    });

    $save = [];
    $queries = 0;
    foreach ($questions as $round => $question) {
        foreach ($attempts as $index => $attempt) {
            $this->actingAs($students[$index]->user);
            DB::flushQueryLog();
            DB::enableQueryLog();
            $t = hrtime(true);
            $this->postJson(route('exam.answers', $attempt), ['token' => $attempt->session_token, 'batch' => [['question_id' => $question->id, 'answer' => 'B', 'client_seq' => $round + 1]]])->assertOk();
            $save[] = (hrtime(true) - $t) / 1e6;
            $queries = max($queries, count(DB::getQueryLog()));
            DB::disableQueryLog();
        }
    }

    $submit = [];
    foreach ($attempts as $index => $attempt) {
        $this->actingAs($students[$index]->user);
        $t = hrtime(true);
        $this->postJson(route('exam.submit', $attempt), ['token' => $attempt->session_token])->assertOk();
        $submit[] = (hrtime(true) - $t) / 1e6;
    }

    loadReport('ujian mulai', loadStats($start), 0);
    loadReport('ujian autosave (HTTP)', $saveStats = loadStats($save), $queries);
    loadReport('ujian kumpulkan (HTTP)', loadStats($submit), 0);

    expect(ExamAnswer::count())->toBe(LOAD_STUDENTS * 5)
        ->and(ExamAttempt::whereNotNull('submitted_at')->count())->toBe(LOAD_STUDENTS)
        ->and(ExamAttempt::where('score', 100)->count())->toBe(LOAD_STUDENTS)
        ->and($saveStats['p95'])->toBeLessThan(300);
})->group('load');

it('kasir: 600 belanja kartu dari 200 santri plus 100 kirim ulang; saldo dan ledger konsisten, p95 < 300 ms', function () {
    $this->travelTo('2026-11-02 03:00:00');
    $bendahara = tap(User::create(['name' => 'Bendahara', 'email' => 'b@uji.test', 'type' => 'staff']))->assignRole('bendahara');
    $kasir = tap(User::create(['name' => 'Kasir', 'email' => 'k@uji.test', 'type' => 'staff']))->assignRole('kasir');
    $merchant = Merchant::create(['name' => 'Koperasi', 'payment_modes' => ['card'], 'commission_percent' => 2]);
    $merchant->cashiers()->attach($kasir->id);
    $product = MerchantProduct::create(['merchant_id' => $merchant->id, 'name' => 'Roti', 'price' => 5000, 'cost_price' => 3000]);

    foreach (range(1, LOAD_STUDENTS) as $i) {
        $student = Student::create(['nis' => 'S'.$i, 'name' => 'Santri '.$i, 'gender' => 'L']);
        app(IssueCard::class)->handle($student, sprintf('%08X', 0xA0000000 + $i));
        app(RecordCashTopup::class)->handle($student, 100000, $bendahara->id);
    }

    $checkout = app(CheckoutOrder::class);
    $samples = [];
    $keys = [];
    $queries = 0;
    foreach (range(1, 3) as $round) {
        foreach (range(1, LOAD_STUDENTS) as $i) {
            $key = (string) Str::uuid();
            $keys[] = [$i, $key];
            DB::flushQueryLog();
            DB::enableQueryLog();
            $t = hrtime(true);
            $result = $checkout->handle($merchant, [$product->id => 1], null, sprintf('%08X', 0xA0000000 + $i), null, $key, $kasir->id);
            $samples[] = (hrtime(true) - $t) / 1e6;
            $queries = max($queries, count(DB::getQueryLog()));
            DB::disableQueryLog();
            expect($result->ok)->toBeTrue($result->code.': '.$result->message);
        }
    }

    // Kasir menekan ulang (jaringan putus): kunci yang sama tidak boleh memotong dua kali.
    foreach (array_slice($keys, 0, 100) as [$i, $key]) {
        $checkout->handle($merchant, [$product->id => 1], null, sprintf('%08X', 0xA0000000 + $i), null, $key, $kasir->id);
    }

    loadReport('kasir belanja kartu', $stats = loadStats($samples), $queries);

    $spent = 3 * 5000;
    expect(PosOrder::count())->toBe(LOAD_STUDENTS * 3)
        ->and((int) Wallet::sum('balance'))->toBe(LOAD_STUDENTS * (100000 - $spent))
        ->and(app(ReconcileWallets::class)->handle())->toBe([])
        ->and((int) MerchantLedgerEntry::where('merchant_id', $merchant->id)->sum('amount'))->toBe($merchant->balance())
        ->and($merchant->balance())->toBe(LOAD_STUDENTS * 3 * (5000 - $merchant->commissionFor(5000)))
        ->and($stats['p95'])->toBeLessThan(300);
})->group('load');

it('sanad:load-prepare menyiapkan data k6 yang benar-benar bisa dipakai, dan menolak production', function () {
    $this->travelTo('2026-11-02 01:00:00');
    config(['session.driver' => 'file']);
    $year = AcademicYear::create(['name' => '2026', 'start_date' => '2026-07-01', 'end_date' => '2027-06-30', 'is_active' => true]);
    Term::create(['academic_year_id' => $year->id, 'name' => 'G', 'start_date' => '2026-07-01', 'end_date' => '2026-12-31', 'is_active' => true]);
    $output = 'storage/framework/testing/load-'.getmypid().'.json';

    $this->artisan('sanad:load-prepare', ['--students' => 3, '--output' => $output])->assertSuccessful();
    $data = json_decode(file_get_contents(base_path($output)), true);
    unlink(base_path($output));
    expect($data['attempts'])->toHaveCount(3)->and($data['cards'])->toHaveCount(3);

    $attempt = $data['attempts'][0];
    // Guard auth sudah terikat ke penyimpan sesi array milik test; ikat ulang ke penyimpan berkas.
    $this->app->forgetInstance('session.store');
    app('auth')->forgetGuards();
    $this->withCredentials()->withUnencryptedCookie($data['session_cookie'], $attempt['cookie'])
        ->postJson('/ujian/'.$attempt['uuid'].'/jawaban', ['token' => $attempt['token'], 'batch' => [['question_id' => $data['questions'][0], 'answer' => 'B', 'client_seq' => 1]]])
        ->assertOk();

    $this->withToken($data['device_key'])->postJson('/api/v1/device/wallet/charge', ['card_uid' => $data['cards'][0], 'amount' => 2000, 'merchant_id' => $data['merchant'], 'idempotency_key' => 'k1'])
        ->assertOk()->assertJson(['ok' => true, 'balance' => 998000]);

    app()->detectEnvironment(fn () => 'production');
    $this->artisan('sanad:load-prepare')->assertFailed();
})->group('load');
