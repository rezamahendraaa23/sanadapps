<?php

namespace App\Console\Commands;

use App\Modules\Academic\Models\Subject;
use App\Modules\Core\Models\Classroom;
use App\Modules\Core\Models\Student;
use App\Modules\Core\Models\Teacher;
use App\Modules\Core\Models\Term;
use App\Modules\Core\Models\User;
use App\Modules\Exam\Actions\RunAttempt;
use App\Modules\Exam\Actions\SaveQuestion;
use App\Modules\Exam\Models\Exam;
use App\Modules\Exam\Models\ExamTarget;
use App\Modules\Exam\Models\QuestionBank;
use App\Modules\Pos\Models\Merchant;
use App\Modules\Wallet\Actions\IssueCard;
use App\Modules\Wallet\Actions\RecordCashTopup;
use App\Modules\Wallet\Models\Device;
use Illuminate\Console\Command;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/**
 * Menyiapkan data uji beban serentak di staging untuk scripts/load/*.js (k6): santri BEBAN-*,
 * kartu bersaldo, merchant, perangkat kasir, ujian yang sudah dimulai, dan sesi login santri.
 * Tidak pernah berjalan di production.
 */
class LoadPrepare extends Command
{
    protected $signature = 'sanad:load-prepare {--students=200} {--output=storage/app/load.json}';

    protected $description = 'Siapkan data uji beban k6 (hanya staging/lokal)';

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->error('Perintah ini tidak boleh dijalankan di production.');

            return self::FAILURE;
        }
        if (! in_array(config('session.driver'), ['redis', 'database', 'file'], true)) {
            $this->error('Sesi harus tersimpan di server (redis, database, atau file) agar k6 bisa memakai cookie.');

            return self::FAILURE;
        }
        $term = Term::query()->where('is_active', true)->first();
        if (! $term) {
            $this->error('Aktifkan satu semester dulu.');

            return self::FAILURE;
        }

        $run = now()->format('His');
        $count = max(1, (int) $this->option('students'));
        $classroom = Classroom::create(['name' => 'BEBAN-'.$run]);
        $subject = Subject::query()->firstOrCreate(['code' => 'BEBAN'], ['name' => 'Uji Beban']);
        $staff = User::create(['name' => 'Ustadz Beban '.$run, 'email' => 'beban-'.$run.'@uji.test', 'type' => 'staff']);
        $teacher = Teacher::create(['user_id' => $staff->id, 'name' => $staff->name]);
        $bank = QuestionBank::create(['name' => 'Beban '.$run, 'subject_id' => $subject->id, 'owner_teacher_id' => $teacher->id]);
        $questions = collect(range(1, 10))->map(fn (int $i) => app(SaveQuestion::class)->handle($bank, ['type' => 'mcq', 'body' => 'Soal beban '.$i, 'options' => ['A', 'B', 'C', 'D'], 'answer_key' => 'B', 'points' => 1]));
        $exam = Exam::create(['title' => 'Uji Beban '.$run, 'subject_id' => $subject->id, 'term_id' => $term->id, 'duration_minutes' => 120, 'start_at' => now()->subMinutes(5), 'end_at' => now()->addHours(3), 'status' => 'published', 'created_by' => $staff->id, 'show_result_mode' => 'after_close']);
        $questions->each(fn ($question, $order) => $exam->questions()->attach($question->id, ['sort_order' => $order]));
        ExamTarget::create(['exam_id' => $exam->id, 'classroom_id' => $classroom->id]);

        $merchant = Merchant::create(['name' => 'Kantin Beban '.$run, 'payment_modes' => ['card', 'qr'], 'commission_percent' => 0]);
        $deviceKey = Str::random(64);
        Device::create(['name' => 'Kasir Beban '.$run, 'type' => 'reader_usb', 'scopes' => ['canteen'], 'api_key_hash' => hash('sha256', $deviceKey)]);

        $attempts = [];
        $cards = [];
        $bar = $this->output->createProgressBar($count);
        foreach (range(1, $count) as $i) {
            $nis = 'BEBAN-'.$run.'-'.$i;
            $user = tap(User::create(['name' => 'Santri Beban '.$i, 'username' => $nis, 'type' => 'student']))->assignRole('santri');
            $student = Student::create(['nis' => $nis, 'name' => 'Santri Beban '.$i, 'gender' => 'L', 'classroom_id' => $classroom->id, 'user_id' => $user->id]);
            $uid = strtoupper(substr(hash('sha256', $nis), 0, 14));
            app(IssueCard::class)->handle($student, $uid);
            app(RecordCashTopup::class)->handle($student, 1_000_000, $staff->id);
            $attempt = app(RunAttempt::class)->start($exam, $student, null, '10.0.0.1', 'k6');
            [$cookie, $csrf] = $this->sessionCookie($user);
            $attempts[] = ['uuid' => $attempt->uuid, 'token' => $attempt->session_token, 'cookie' => $cookie, 'csrf' => $csrf];
            $cards[] = $uid;
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();

        $file = base_path($this->option('output'));
        file_put_contents($file, json_encode([
            'session_cookie' => config('session.cookie'),
            'questions' => $questions->pluck('id')->all(),
            'attempts' => $attempts,
            'device_key' => $deviceKey,
            'merchant' => $merchant->uuid,
            'cards' => $cards,
        ], JSON_PRETTY_PRINT));
        $this->info('Data beban siap: '.$count.' santri. Berkas: '.$file.' (berisi kunci rahasia, hapus setelah uji).');

        return self::SUCCESS;
    }

    /**
     * @return array{0: string, 1: string} cookie terenkripsi dan token CSRF sesi
     */
    private function sessionCookie(User $user): array
    {
        $session = app('session')->driver();
        $session->setId(Str::random(40));
        $session->put('_token', $csrf = Str::random(40));
        $session->put(auth()->guard('web')->getName(), $user->getAuthIdentifier());
        $session->save();

        return [Crypt::encrypt(CookieValuePrefix::create(config('session.cookie'), app('encrypter')->getKey()).$session->getId(), false), $csrf];
    }
}
