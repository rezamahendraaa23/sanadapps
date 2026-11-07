<?php

namespace App\Modules\Exam\Http;

use App\Modules\Exam\Actions\RunAttempt;
use App\Modules\Exam\Models\ExamAttempt;
use App\Modules\Exam\Models\Question;
use App\Modules\Exam\Support\AttemptGone;
use App\Modules\Exam\Support\SafeHtml;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Halaman ujian (S-05) dan API sinkronnya. Bukan Livewire: harus tetap berjalan saat putus koneksi.
 */
class AttemptController
{
    public function __construct(private RunAttempt $attempts) {}

    private function own(Request $request, ExamAttempt $attempt): void
    {
        abort_unless($request->user()->student?->id === $attempt->student_id, 403);
    }

    public function show(Request $request, ExamAttempt $attempt): View|RedirectResponse
    {
        $this->own($request, $attempt);

        if (! $attempt->isOpen()) {
            return redirect()->route('exam.done', $attempt);
        }

        if ($attempt->deadline_at->isPast()) {
            $this->attempts->autoSubmitExpired();

            return redirect()->route('exam.done', $attempt);
        }

        $attempt->load('exam');

        return view('exam.take', [
            'attempt' => $attempt,
            'config' => [
                'attempt' => $attempt->uuid,
                'token' => $attempt->session_token,
                'questions' => collect($this->attempts->publicQuestions($attempt))->map(fn (array $question) => [...$question, 'body' => SafeHtml::clean($question['body'])])->all(),
                'answers' => $attempt->answers()->get()->mapWithKeys(fn ($answer) => [$answer->question_id => $answer->answer['value'] ?? null]),
                'maxSeq' => (int) $attempt->answers()->max('client_seq'),
                'deadline' => $attempt->deadline_at->toIso8601ZuluString('millisecond'),
                'serverTime' => now()->toIso8601ZuluString('millisecond'),
                'urls' => [
                    'answers' => route('exam.answers', $attempt),
                    'submit' => route('exam.submit', $attempt),
                    'events' => route('exam.events', $attempt),
                    'done' => route('exam.done', $attempt),
                ],
            ],
        ]);
    }

    public function answers(Request $request, ExamAttempt $attempt): JsonResponse
    {
        $this->own($request, $attempt);
        $data = $request->validate(['token' => 'required|string', 'batch' => 'present|array|max:200', 'batch.*.question_id' => 'required|integer', 'batch.*.client_seq' => 'required|integer|min:1']);

        try {
            return response()->json(['ok' => true, ...$this->attempts->saveAnswers($attempt, $data['token'], $request->input('batch'))]);
        } catch (AttemptGone $gone) {
            return response()->json(['ok' => false, 'reason' => $gone->reason, 'message' => $gone->getMessage()], 409);
        }
    }

    public function submit(Request $request, ExamAttempt $attempt): JsonResponse
    {
        $this->own($request, $attempt);
        $data = $request->validate(['token' => 'required|string']);

        try {
            $this->attempts->submit($attempt, $data['token']);

            return response()->json(['ok' => true, 'done' => route('exam.done', $attempt)]);
        } catch (AttemptGone $gone) {
            return response()->json(['ok' => false, 'reason' => $gone->reason, 'message' => $gone->getMessage(), 'done' => route('exam.done', $attempt)], 409);
        }
    }

    public function events(Request $request, ExamAttempt $attempt): JsonResponse
    {
        $this->own($request, $attempt);
        $data = $request->validate(['events' => 'present|array|max:50', 'events.*' => 'string']);

        foreach ($data['events'] as $type) {
            $this->attempts->recordEvent($attempt, $type);
        }

        return response()->json(['ok' => true]);
    }

    public function done(Request $request, ExamAttempt $attempt): View
    {
        $this->own($request, $attempt);

        return view('exam.done', ['attempt' => $attempt->load('exam')]);
    }

    public function media(Request $request, Question $question): Response
    {
        $user = $request->user();
        $student = $user->student;
        $allowed = $user->can('exams.manage') || $user->can('exams.own')
            || ($student !== null && ExamAttempt::query()->where('student_id', $student->id)->whereHas('exam.questions', fn ($query) => $query->whereKey($question->id))->exists());

        abort_unless($allowed && $question->media_path && Storage::disk('local')->exists($question->media_path), 404);

        return Storage::disk('local')->response($question->media_path);
    }
}
