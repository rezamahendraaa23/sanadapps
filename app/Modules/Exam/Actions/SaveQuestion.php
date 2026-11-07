<?php

namespace App\Modules\Exam\Actions;

use App\Modules\Exam\Models\Question;
use App\Modules\Exam\Models\QuestionBank;
use App\Modules\Exam\Support\ExamException;
use App\Modules\Exam\Support\SafeHtml;

class SaveQuestion
{
    /**
     * Kunci divalidasi terhadap opsi, agar soal tanpa jawaban benar tidak lolos.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(QuestionBank $bank, array $data, ?Question $question = null): Question
    {
        $type = (string) ($data['type'] ?? '');

        if (! isset(Question::TYPES[$type])) {
            throw new ExamException('Jenis soal tidak dikenal.');
        }

        $body = SafeHtml::clean((string) ($data['body'] ?? ''));

        if (trim(strip_tags($body)) === '') {
            throw new ExamException('Isi soal wajib diisi.');
        }

        $points = (float) ($data['points'] ?? 1);

        if ($points <= 0) {
            throw new ExamException('Poin harus lebih dari nol.');
        }

        [$options, $key] = $this->normalize($type, (array) ($data['options'] ?? []), $data['answer_key'] ?? null);

        $values = [
            'bank_id' => $bank->id,
            'type' => $type,
            'body' => $body,
            'direction' => ($data['direction'] ?? 'ltr') === 'rtl' ? 'rtl' : 'ltr',
            'options' => $options,
            'answer_key' => $key,
            'points' => $points,
            'difficulty' => filled($data['difficulty'] ?? null) ? max(1, min(3, (int) $data['difficulty'])) : null,
            'tags' => array_values(array_filter(array_map('trim', is_array($data['tags'] ?? null) ? $data['tags'] : explode(',', (string) ($data['tags'] ?? ''))))),
        ];

        if (array_key_exists('media_path', $data)) {
            $values['media_path'] = $data['media_path'];
        }

        if ($question !== null) {
            $question->update($values);

            return $question;
        }

        return Question::create($values);
    }

    /**
     * @param  list<string>  $options  Teks opsi berurutan.
     * @return array{0: list<array{key: string, text: string}>|null, 1: list<string>|null}
     */
    private function normalize(string $type, array $options, mixed $key): array
    {
        $key = is_array($key) ? array_values(array_filter($key, 'filled')) : (filled($key) ? [(string) $key] : []);

        if ($type === 'essay') {
            return [null, null];
        }

        if ($type === 'short') {
            $accepted = array_values(array_filter(array_map('trim', count($key) === 1 ? explode('|', $key[0]) : $key)));

            if ($accepted === []) {
                throw new ExamException('Isi minimal satu jawaban yang diterima.');
            }

            return [null, $accepted];
        }

        if ($type === 'true_false') {
            if (count($key) !== 1 || ! in_array($key[0], ['true', 'false'], true)) {
                throw new ExamException('Pilih kunci benar atau salah.');
            }

            return [null, $key];
        }

        $texts = array_values(array_filter(array_map(fn ($text) => trim((string) $text), $options), 'filled'));

        if (count($texts) < 2) {
            throw new ExamException('Isi minimal dua opsi.');
        }

        $list = [];

        foreach ($texts as $index => $text) {
            $list[] = ['key' => chr(65 + $index), 'text' => $text];
        }

        $keys = array_column($list, 'key');

        if ($key === [] || array_diff($key, $keys) !== [] || ($type === 'mcq' && count($key) !== 1)) {
            throw new ExamException($type === 'mcq' ? 'Pilih satu kunci jawaban.' : 'Pilih kunci jawaban yang benar.');
        }

        sort($key);

        return [$list, $key];
    }
}
