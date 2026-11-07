<?php

namespace App\Modules\Exam\Actions;

use App\Modules\Admission\Models\Applicant;
use App\Modules\Core\Models\Student;
use App\Modules\Exam\Models\Exam;
use App\Modules\Exam\Models\ExamAttempt;
use App\Modules\Exam\Models\ExamEvent;
use App\Modules\Exam\Models\Question;
use App\Modules\Exam\Support\AttemptGone;
use App\Modules\Exam\Support\ExamException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RunAttempt
{
    /** Jawaban yang tertahan di HP saat waktu habis masih diterima selama ini setelah batas. */
    public const LATE_SYNC_MINUTES = 10;

    public function __construct(private GradeAttempt $grader) {}

    /**
     * Mulai, atau lanjut di perangkat ini. Attempt yang sedang berjalan diambil alih dengan token baru,
     * sehingga perangkat lama berhenti menyimpan (PRD Sistem 8.8).
     */
    public function start(Exam $exam, Student $student, ?string $code, ?string $ip, ?string $userAgent): ExamAttempt
    {
        $this->assertTargeted($exam, $student);

        return $this->open($exam, 'student_id', $student->id, $code, $ip, $userAgent);
    }

    /**
     * Ujian seleksi tanpa akun santri (EXM-12).
     */
    public function startForApplicant(Exam $exam, Applicant $applicant, ?string $code, ?string $ip, ?string $userAgent): ExamAttempt
    {
        if ($exam->audience !== 'applicant' || $applicant->status !== 'in_selection' || ! $applicant->period->steps()->where('exam_id', $exam->id)->exists()) {
            throw new ExamException('Ujian ini bukan untuk Anda.');
        }

        return $this->open($exam, 'applicant_id', $applicant->id, $code, $ip, $userAgent);
    }

    private function open(Exam $exam, string $column, int $ownerId, ?string $code, ?string $ip, ?string $userAgent): ExamAttempt
    {
        if (! $exam->isOpen()) {
            throw new ExamException($exam->status !== 'published' ? 'Ujian ini tidak dibuka.' : ($exam->start_at->isFuture() ? 'Ujian belum dimulai.' : 'Waktu ujian sudah berakhir.'));
        }

        if (filled($exam->access_code) && ! hash_equals((string) $exam->access_code, trim((string) $code))) {
            throw new ExamException('Kode akses salah.');
        }

        try {
            return DB::transaction(function () use ($exam, $column, $ownerId, $ip, $userAgent): ExamAttempt {
                $active = ExamAttempt::query()->where('exam_id', $exam->id)->where($column, $ownerId)->where('status', 'in_progress')->lockForUpdate()->first();

                if ($active !== null) {
                    if ($active->deadline_at->isPast()) {
                        $this->finish($active, 'auto_submitted');
                    } else {
                        $active->update(['session_token' => Str::random(64), 'ip' => $ip, 'user_agent' => $userAgent]);

                        return $active;
                    }
                }

                $used = ExamAttempt::query()->where('exam_id', $exam->id)->where($column, $ownerId)->count();

                if ($used >= $exam->max_attempts) {
                    throw new ExamException('Batas percobaan ujian ini sudah habis.');
                }

                $questions = $exam->questions()->get();
                $order = $questions->pluck('id')->all();

                if ($exam->shuffle_questions) {
                    shuffle($order);
                }

                $options = [];

                foreach ($questions as $question) {
                    if ($question->hasOptions()) {
                        $keys = array_column($question->optionList(), 'key');

                        if ($exam->shuffle_options && $question->type !== 'true_false') {
                            shuffle($keys);
                        }

                        $options[$question->id] = $keys;
                    }
                }

                return ExamAttempt::create([
                    'exam_id' => $exam->id,
                    $column => $ownerId,
                    'attempt_no' => $used + 1,
                    'started_at' => now(),
                    // Batas selalu dari server: durasi atau akhir jendela, mana yang lebih dulu.
                    'deadline_at' => min(now()->addMinutes($exam->duration_minutes)->toImmutable(), $exam->end_at),
                    'question_order' => $order,
                    'option_order' => $options,
                    'ip' => $ip,
                    'user_agent' => $userAgent,
                    'last_seen_at' => now(),
                    'session_token' => Str::random(64),
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            // Dua ketukan bersamaan: yang kalah memakai attempt yang baru dibuat.
            return ExamAttempt::query()->where('exam_id', $exam->id)->where($column, $ownerId)->where('status', 'in_progress')->firstOrFail();
        }
    }

    /**
     * Sinkron batch: hanya client_seq yang lebih besar yang menimpa, jadi kirim ulang dan urutan terbalik aman.
     *
     * @param  list<array{question_id: mixed, answer: mixed, client_seq: mixed}>  $batch
     * @return array{max_client_seq: int, server_time: string, deadline_at: string}
     */
    public function saveAnswers(ExamAttempt $attempt, string $token, array $batch): array
    {
        $this->assertWritable($attempt, $token);
        $allowed = array_flip($attempt->question_order);
        $max = 0;
        $late = $attempt->deadline_at->isPast();

        foreach ($batch as $row) {
            $questionId = (int) ($row['question_id'] ?? 0);
            $seq = (int) ($row['client_seq'] ?? 0);

            if (! isset($allowed[$questionId]) || $seq < 1) {
                continue;
            }

            $max = max($max, $seq);

            DB::statement(
                'INSERT INTO exam_answers (uuid, attempt_id, question_id, answer, client_seq, answered_at, created_at, updated_at)
                 VALUES (?, ?, ?, ?::jsonb, ?, ?, ?, ?)
                 ON CONFLICT (attempt_id, question_id) DO UPDATE
                 SET answer = EXCLUDED.answer, client_seq = EXCLUDED.client_seq, answered_at = EXCLUDED.answered_at, updated_at = EXCLUDED.updated_at
                 WHERE exam_answers.client_seq < EXCLUDED.client_seq AND exam_answers.graded_by IS NULL',
                [(string) Str::uuid7(), $attempt->id, $questionId, json_encode(['value' => $row['answer'] ?? null], JSON_UNESCAPED_UNICODE), $seq, now()->toIso8601ZuluString('microsecond'), now()->toIso8601ZuluString('microsecond'), now()->toIso8601ZuluString('microsecond')],
            );
        }

        ExamAttempt::query()->whereKey($attempt->id)->update(['last_seen_at' => now()]);

        if ($late && $batch !== []) {
            $this->recordEvent($attempt, 'reconnect', ['late_sync' => true, 'answers' => count($batch)]);

            if (! $attempt->fresh()->isOpen()) {
                $this->grader->handle($attempt->fresh());
            }
        }

        return ['max_client_seq' => (int) max($max, (int) ExamAttempt::query()->whereKey($attempt->id)->first()?->answers()->max('client_seq')), 'server_time' => now()->toIso8601ZuluString('millisecond'), 'deadline_at' => $attempt->deadline_at->toIso8601ZuluString('millisecond')];
    }

    public function submit(ExamAttempt $attempt, string $token): ExamAttempt
    {
        $this->assertWritable($attempt, $token);

        DB::transaction(function () use ($attempt): void {
            $locked = ExamAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();

            if ($locked->isOpen()) {
                $this->finish($locked, $locked->deadline_at->isPast() ? 'auto_submitted' : 'submitted');
            }
        });

        return $attempt->refresh();
    }

    /**
     * Job tiap menit: attempt yang lewat batas ditutup (EXM-07).
     */
    public function autoSubmitExpired(): int
    {
        $count = 0;

        ExamAttempt::query()->where('status', 'in_progress')->where('deadline_at', '<=', now())->each(function (ExamAttempt $attempt) use (&$count): void {
            DB::transaction(function () use ($attempt, &$count): void {
                $locked = ExamAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();

                if ($locked->isOpen()) {
                    $this->finish($locked, 'auto_submitted');
                    $count++;
                }
            });
        });

        return $count;
    }

    /**
     * @param  array<string, mixed>|null  $meta
     */
    public function recordEvent(ExamAttempt $attempt, string $type, ?array $meta = null): void
    {
        if (! in_array($type, ['blur', 'focus', 'offline', 'online', 'reconnect'], true)) {
            return;
        }

        ExamEvent::create(['attempt_id' => $attempt->id, 'type' => $type, 'occurred_at' => now(), 'meta' => $meta]);

        if ($type === 'blur') {
            ExamAttempt::query()->whereKey($attempt->id)->increment('blur_count');
        }

        ExamAttempt::query()->whereKey($attempt->id)->update(['last_seen_at' => now()]);
    }

    private function finish(ExamAttempt $locked, string $status): void
    {
        $locked->update(['status' => $status, 'submitted_at' => now()]);
        $this->grader->handle($locked);
    }

    /**
     * @throws AttemptGone
     */
    private function assertWritable(ExamAttempt $attempt, string $token): void
    {
        if (! hash_equals($attempt->session_token, $token)) {
            throw new AttemptGone('taken_over', 'Ujian dilanjutkan di perangkat lain.');
        }

        $limit = $attempt->deadline_at->addMinutes(self::LATE_SYNC_MINUTES);

        $collectedEarly = ! $attempt->isOpen() && ($attempt->submitted_at === null || $attempt->submitted_at->lessThan($attempt->deadline_at));

        if ($limit->isPast() || $collectedEarly) {
            throw new AttemptGone('closed', 'Ujian ini sudah dikumpulkan.');
        }
    }

    private function assertTargeted(Exam $exam, Student $student): void
    {
        if (! Exam::query()->whereKey($exam->id)->forStudent($student)->exists()) {
            throw new ExamException('Ujian ini bukan untuk Anda.');
        }
    }

    /**
     * Soal untuk halaman ujian. Kunci jawaban tidak pernah ikut.
     *
     * @return list<array<string, mixed>>
     */
    public function publicQuestions(ExamAttempt $attempt): array
    {
        $questions = $attempt->exam->questions()->withTrashed()->get()->keyBy('id');

        return collect($attempt->question_order)->map(function (int $id) use ($questions, $attempt) {
            /** @var Question $question */
            $question = $questions[$id];
            $options = collect($question->optionList())->keyBy('key');

            return [
                'id' => $question->id,
                'type' => $question->type,
                'body' => $question->body,
                'direction' => $question->direction,
                'media' => $question->media_path ? route($attempt->applicant_id ? 'ppdb.exam.media' : 'exam.media', $question) : null,
                'points' => (float) ($question->pivot->points_override ?? $question->points),
                'options' => collect($attempt->option_order[$id] ?? [])->map(fn (string $key) => ['key' => $key, 'text' => $options[$key]['text'] ?? ''])->values()->all(),
            ];
        })->values()->all();
    }
}
