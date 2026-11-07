<?php

namespace App\Modules\Exam\Actions;

use App\Modules\Admission\Actions\ScoreApplicant;
use App\Modules\Exam\Models\ExamAnswer;
use App\Modules\Exam\Models\ExamAttempt;
use App\Modules\Exam\Models\Question;
use Illuminate\Support\Facades\DB;

class GradeAttempt
{
    /**
     * Koreksi otomatis lalu hitung nilai. Nilai = poin diperoleh / poin maksimal x 100 (PRD Sistem 8.8).
     * Skor esai yang sudah dikoreksi manual tidak disentuh.
     */
    public function handle(ExamAttempt $attempt): void
    {
        DB::transaction(function () use ($attempt): void {
            $exam = $attempt->exam;
            $questions = $exam->questions()->withTrashed()->get();
            $answers = ExamAnswer::query()->where('attempt_id', $attempt->id)->lockForUpdate()->get()->keyBy('question_id');
            $maximum = 0.0;
            $earned = 0.0;
            $pending = false;

            foreach ($questions as $question) {
                $points = (float) ($question->pivot->points_override ?? $question->points);
                $maximum += $points;
                $answer = $answers[$question->id] ?? null;

                if ($question->type === 'essay') {
                    if ($answer === null || blank($answer->answer['value'] ?? null)) {
                        if ($answer !== null && $answer->graded_by === null) {
                            $answer->update(['score' => 0, 'is_correct' => false]);
                        }

                        continue;
                    }

                    if ($answer->graded_by === null) {
                        $pending = true;
                    } else {
                        $earned += (float) $answer->score;
                    }

                    continue;
                }

                if ($answer === null) {
                    continue;
                }

                $correct = self::isCorrect($question, $answer->answer['value'] ?? null);
                $score = $correct ? $points : 0.0;
                $answer->update(['is_correct' => $correct, 'score' => $score]);
                $earned += $score;
            }

            $attempt->update([
                'score' => $maximum > 0 ? round($earned / $maximum * 100, 2) : 0,
                'status' => $pending ? ($attempt->status === 'graded' ? 'submitted' : $attempt->status) : 'graded',
            ]);

            app(ScoreApplicant::class)->syncFromExam($attempt);
        });
    }

    public static function isCorrect(Question $question, mixed $value): bool
    {
        $key = $question->answer_key ?? [];

        return match ($question->type) {
            'mcq', 'true_false' => is_string($value) && [$value] === $key,
            'multi' => is_array($value) && self::sameSet($value, $key),
            'short' => is_string($value) && in_array(self::normalize($value), array_map(self::normalize(...), $key), true),
            default => false,
        };
    }

    /**
     * @param  list<mixed>  $a
     * @param  list<mixed>  $b
     */
    private static function sameSet(array $a, array $b): bool
    {
        sort($a);
        sort($b);

        return $a === $b;
    }

    /**
     * Spasi dan huruf besar diabaikan; harakat Arab opsional.
     */
    public static function normalize(string $text): string
    {
        $text = (string) preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{0640}]/u', '', $text);

        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $text)));
    }
}
