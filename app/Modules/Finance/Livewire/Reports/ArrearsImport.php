<?php

namespace App\Modules\Finance\Livewire\Reports;

use App\Modules\Core\Models\Student;
use App\Modules\Finance\Actions\CreateManualInvoice;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

#[Layout('components.layouts.staff', ['active' => 'arrears'])]
#[Title('Impor Tunggakan Awal')]
class ArrearsImport extends Component
{
    use WithFileUploads;

    public const HEADINGS = ['nis', 'keterangan', 'nominal', 'jatuh_tempo'];

    public $file = null;

    /** @var list<array{row: int, student_id: int|null, name: string|null, description: string|null, amount: int, due: string|null, errors: list<string>}>|null */
    #[Locked]
    public ?array $rows = null;

    #[Locked]
    public ?int $imported = null;

    public function check(): void
    {
        $this->resetErrorBag();
        $this->validate(['file' => 'required|file|mimes:xlsx|max:10240'], ['file.required' => 'Pilih berkas Excel dulu.', 'file.mimes' => 'Berkas harus berformat .xlsx.']);

        try {
            $sheet = Excel::toArray(new class implements ToArray, WithHeadingRow
            {
                public function array(array $array): void {}
            }, $this->file)[0] ?? [];
        } catch (Throwable $exception) {
            report($exception);
            $this->addError('file', 'Berkas tidak bisa dibaca. Gunakan template yang tersedia.');

            return;
        }

        $this->rows = [];

        foreach ($sheet as $index => $raw) {
            if (collect($raw)->filter(fn ($value) => filled($value))->isEmpty()) {
                continue;
            }

            $this->rows[] = $this->parse($raw, $index + 2);
        }

        if ($this->rows === []) {
            $this->rows = null;
            $this->addError('file', 'Berkas tidak berisi data.');
        }
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array{row: int, student_id: int|null, name: string|null, description: string|null, amount: int, due: string|null, errors: list<string>}
     */
    private function parse(array $raw, int $line): array
    {
        $errors = [];
        $student = Student::query()->where('nis', trim((string) ($raw['nis'] ?? '')))->first();

        if ($student === null) {
            $errors[] = 'NIS tidak ditemukan.';
        }

        $description = trim((string) ($raw['keterangan'] ?? ''));
        if ($description === '') {
            $errors[] = 'Keterangan wajib diisi.';
        }

        $amount = (int) preg_replace('/\D/', '', (string) ($raw['nominal'] ?? ''));
        if ($amount <= 0) {
            $errors[] = 'Nominal harus lebih dari nol.';
        }

        $due = null;
        try {
            $value = $raw['jatuh_tempo'] ?? null;
            $due = match (true) {
                blank($value) => null,
                is_numeric($value) => CarbonImmutable::instance(ExcelDate::excelToDateTimeObject((float) $value))->toDateString(),
                preg_match('#^\d{1,2}/\d{1,2}/\d{4}$#', trim((string) $value)) === 1 => CarbonImmutable::createFromFormat('!d/m/Y', trim((string) $value))->toDateString(),
                default => CarbonImmutable::parse((string) $value)->toDateString(),
            };
        } catch (Throwable) {
            $due = null;
        }

        if ($due === null) {
            $errors[] = 'Jatuh tempo belum benar. Gunakan format 31/12/2026.';
        }

        return ['row' => $line, 'student_id' => $student?->id, 'name' => $student?->name, 'description' => $description, 'amount' => $amount, 'due' => $due, 'errors' => $errors];
    }

    public function import(CreateManualInvoice $action): void
    {
        abort_if($this->rows === null, 422);
        $count = 0;

        foreach ($this->rows as $row) {
            if ($row['errors'] !== []) {
                continue;
            }

            $action->handle(Student::query()->findOrFail($row['student_id']), [['description' => $row['description'], 'amount' => $row['amount']]], $row['due'], auth()->id());
            $count++;
        }

        $this->imported = $count;
        $this->rows = null;
    }

    public function startOver(): void
    {
        $this->reset('file', 'rows', 'imported');
        $this->resetErrorBag();
    }

    public function render(): View
    {
        return view('finance.reports.arrears-import');
    }
}
