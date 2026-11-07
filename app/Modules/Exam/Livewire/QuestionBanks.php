<?php

namespace App\Modules\Exam\Livewire;

use App\Modules\Academic\Models\Subject;
use App\Modules\Academic\Models\TeachingScope;
use App\Modules\Exam\Actions\SaveQuestion;
use App\Modules\Exam\Models\Question;
use App\Modules\Exam\Models\QuestionBank;
use App\Modules\Exam\Support\ExamException;
use App\Modules\Exam\Support\SafeHtml;
use App\Modules\Finance\Actions\CompressProofImage;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

#[Title('Bank Soal')]
class QuestionBanks extends Component
{
    use StaffPortalLayout, WithFileUploads;

    public const HEADINGS = ['jenis', 'soal', 'arah', 'opsi_a', 'opsi_b', 'opsi_c', 'opsi_d', 'opsi_e', 'kunci', 'poin', 'tingkat', 'tag'];

    #[Url(as: 'bank', except: '')]
    public string $bankUuid = '';

    public string $bank_name = '';

    public string $bank_subject = '';

    #[Locked]
    public ?int $editing = null;

    public string $type = 'mcq';

    public string $body = '';

    public string $direction = 'ltr';

    /** @var list<string> */
    public array $options = ['', '', '', ''];

    /** @var list<string> */
    public array $keys = [];

    public string $singleKey = '';

    public string $points = '1';

    public string $difficulty = '';

    public string $tags = '';

    public $media = null;

    public $importFile = null;

    /** @var list<array{row: int, errors: list<string>}>|null */
    #[Locked]
    public ?array $importErrors = null;

    #[Locked]
    public ?int $imported = null;

    private function bank(): ?QuestionBank
    {
        return Str::isUuid($this->bankUuid) ? QuestionBank::query()->accessibleBy(auth()->user())->where('uuid', $this->bankUuid)->first() : null;
    }

    public function saveBank(): void
    {
        $this->resetErrorBag();
        $this->validate(['bank_name' => 'required|string|max:100', 'bank_subject' => 'required|exists:subjects,id'], [], ['bank_name' => 'nama bank', 'bank_subject' => 'mapel']);

        $bank = QuestionBank::create(['name' => $this->bank_name, 'subject_id' => (int) $this->bank_subject, 'owner_teacher_id' => TeachingScope::teacher(auth()->user())?->id]);
        $this->bankUuid = $bank->uuid;
        $this->reset('bank_name', 'bank_subject');
        $this->dispatch('close-modal', 'form-bank');
    }

    public function create(): void
    {
        abort_if($this->bank() === null, 403);
        $this->reset('editing', 'type', 'body', 'direction', 'options', 'keys', 'singleKey', 'points', 'difficulty', 'tags', 'media');
        $this->resetErrorBag();
        $this->dispatch('open-modal', 'form-soal');
    }

    public function edit(string $uuid): void
    {
        $question = $this->bank()?->questions()->where('uuid', $uuid)->firstOrFail();
        abort_if($question === null, 403);
        $this->editing = $question->id;
        $texts = collect($question->options ?? [])->pluck('text')->all();
        $key = $question->answer_key ?? [];
        $this->fill([
            'type' => $question->type,
            'body' => $question->body,
            'direction' => $question->direction,
            'options' => $texts === [] ? ['', '', '', ''] : $texts,
            'keys' => $question->type === 'multi' ? $key : [],
            'singleKey' => in_array($question->type, ['mcq', 'true_false'], true) ? ($key[0] ?? '') : ($question->type === 'short' ? implode('|', $key) : ''),
            'points' => (string) (float) $question->points,
            'difficulty' => (string) $question->difficulty,
            'tags' => implode(', ', $question->tags ?? []),
            'media' => null,
        ]);
        $this->resetErrorBag();
        $this->dispatch('open-modal', 'form-soal');
    }

    public function addOption(): void
    {
        if (count($this->options) < 8) {
            $this->options[] = '';
        }
    }

    public function removeOption(int $index): void
    {
        if (count($this->options) > 2 && isset($this->options[$index])) {
            unset($this->options[$index]);
            $this->options = array_values($this->options);
            $this->reset('keys', 'singleKey');
        }
    }

    public function save(SaveQuestion $action, CompressProofImage $images): void
    {
        $bank = $this->bank();
        abort_if($bank === null, 403);
        $this->resetErrorBag();

        if ($this->media) {
            $this->validate(['media' => 'image|max:5120'], [], ['media' => 'gambar']);
        }

        $data = [
            'type' => $this->type,
            'body' => $this->body,
            'direction' => $this->direction,
            'options' => $this->options,
            'answer_key' => $this->type === 'multi' ? $this->keys : $this->singleKey,
            'points' => str_replace(',', '.', $this->points),
            'difficulty' => $this->difficulty,
            'tags' => $this->tags,
        ];

        if ($this->media) {
            $data['media_path'] = $images->store($this->media, 'question-media');
        }

        try {
            $action->handle($bank, $data, $this->editing ? $bank->questions()->findOrFail($this->editing) : null);
        } catch (ExamException $exception) {
            $this->addError('body', $exception->getMessage());

            return;
        }

        $this->dispatch('close-modal', 'form-soal');
        $this->dispatch('toast', message: 'Soal tersimpan.', tone: 'success');
    }

    public function remove(string $uuid): void
    {
        $this->bank()?->questions()->where('uuid', $uuid)->firstOrFail()->delete();
    }

    /**
     * Impor xlsx: baris yang valid masuk, yang salah dilaporkan per baris (EXM-02).
     */
    public function import(SaveQuestion $action): void
    {
        $bank = $this->bank();
        abort_if($bank === null, 403);
        $this->resetErrorBag();
        $this->validate(['importFile' => 'required|file|mimes:xlsx|max:10240'], ['importFile.required' => 'Pilih berkas Excel dulu.', 'importFile.mimes' => 'Berkas harus berformat .xlsx.']);

        try {
            $rows = Excel::toArray(new class implements ToArray, WithHeadingRow
            {
                public function array(array $array): void {}
            }, $this->importFile)[0] ?? [];
        } catch (Throwable $exception) {
            report($exception);
            $this->addError('importFile', 'Berkas tidak bisa dibaca. Gunakan template yang tersedia.');

            return;
        }

        $types = ['pg' => 'mcq', 'mcq' => 'mcq', 'kompleks' => 'multi', 'multi' => 'multi', 'bs' => 'true_false', 'benar_salah' => 'true_false', 'true_false' => 'true_false', 'isian' => 'short', 'short' => 'short', 'esai' => 'essay', 'essay' => 'essay'];
        $errors = [];
        $count = 0;

        foreach ($rows as $index => $raw) {
            if (collect($raw)->filter(fn ($value) => filled($value))->isEmpty()) {
                continue;
            }

            $type = $types[Str::lower(trim((string) ($raw['jenis'] ?? '')))] ?? null;
            $key = trim((string) ($raw['kunci'] ?? ''));

            try {
                if ($type === null) {
                    throw new ExamException('Jenis tidak dikenal.');
                }

                $action->handle($bank, [
                    'type' => $type,
                    'body' => (string) ($raw['soal'] ?? ''),
                    'direction' => Str::lower((string) ($raw['arah'] ?? '')) === 'rtl' ? 'rtl' : 'ltr',
                    'options' => [$raw['opsi_a'] ?? '', $raw['opsi_b'] ?? '', $raw['opsi_c'] ?? '', $raw['opsi_d'] ?? '', $raw['opsi_e'] ?? ''],
                    'answer_key' => match ($type) {
                        'multi' => array_map('trim', explode(',', Str::upper($key))),
                        'mcq' => Str::upper($key),
                        'true_false' => in_array(Str::lower($key), ['benar', 'true', 'b'], true) ? 'true' : (in_array(Str::lower($key), ['salah', 'false', 's'], true) ? 'false' : ''),
                        default => $key,
                    },
                    'points' => filled($raw['poin'] ?? null) ? $raw['poin'] : 1,
                    'difficulty' => $raw['tingkat'] ?? null,
                    'tags' => (string) ($raw['tag'] ?? ''),
                ]);
                $count++;
            } catch (ExamException $exception) {
                $errors[] = ['row' => $index + 2, 'errors' => [$exception->getMessage()]];
            }
        }

        $this->imported = $count;
        $this->importErrors = $errors;
        $this->reset('importFile');
    }

    public function render(): View
    {
        $bank = $this->bank();
        [$layout, $params] = $this->examLayout('Bank Soal');

        return view('exam.banks', [
            'banks' => QuestionBank::query()->accessibleBy(auth()->user())->with('subject:id,name')->withCount('questions')->orderBy('name')->get(),
            'bank' => $bank,
            'questions' => $bank?->questions()->latest('id')->get() ?? collect(),
            'subjects' => Subject::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all(),
            'types' => Question::TYPES,
            'preview' => SafeHtml::clean($this->body),
            'examsUrl' => $this->examRoute('index'),
        ])->layout($layout, $params);
    }
}
