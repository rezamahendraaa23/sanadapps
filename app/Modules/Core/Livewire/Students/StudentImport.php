<?php

namespace App\Modules\Core\Livewire\Students;

use App\Modules\Core\Actions\ReadStudentImport;
use App\Modules\Core\Jobs\ProcessStudentImport;
use App\Modules\Core\Models\Import;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Throwable;

#[Layout('components.layouts.staff', ['active' => 'students'])]
#[Title('Impor Santri')]
class StudentImport extends Component
{
    use WithFileUploads;

    public $file = null;

    #[Locked]
    public ?string $storedPath = null;

    /** @var array{total: int, valid: int, invalid: int, errors: list<array<string, mixed>>}|null */
    #[Locked]
    public ?array $preview = null;

    #[Locked]
    public ?int $importId = null;

    /** Dipakai sebagai popup di halaman Santri. */
    #[Locked]
    public bool $inModal = false;

    public function check(ReadStudentImport $reader): void
    {
        $this->resetErrorBag();
        $this->validate(['file' => 'required|file|mimes:xlsx|max:10240'], [
            'file.required' => 'Pilih berkas Excel dulu.',
            'file.mimes' => 'Berkas harus berformat .xlsx. Gunakan template yang tersedia.',
            'file.max' => 'Berkas maksimal 10 MB.',
        ]);

        $this->storedPath = $this->file->store('imports', 'local');

        try {
            $rows = $reader->handle($this->storedPath);
        } catch (Throwable $exception) {
            report($exception);
            $this->addError('file', 'Berkas tidak bisa dibaca. Unggah ulang memakai template terbaru.');

            return;
        }

        $invalid = array_values(array_filter($rows, fn ($row) => $row['errors'] !== []));

        if ($rows === []) {
            $this->addError('file', 'Berkas tidak berisi data santri.');

            return;
        }

        $this->preview = [
            'total' => count($rows),
            'valid' => count($rows) - count($invalid),
            'invalid' => count($invalid),
            'errors' => array_map(fn ($row) => ['row' => $row['row'], 'name' => $row['student']['name'], 'messages' => $row['errors']], array_slice($invalid, 0, 50)),
        ];
    }

    public function start(): void
    {
        abort_if($this->storedPath === null || $this->preview === null, 422);

        $import = Import::create([
            'type' => 'students',
            'status' => 'queued',
            'file_path' => $this->storedPath,
            'total_rows' => $this->preview['total'],
            'created_by' => auth()->id(),
        ]);

        ProcessStudentImport::dispatch($import);

        $this->importId = $import->id;
    }

    public function startOver(): void
    {
        $this->reset('file', 'storedPath', 'preview', 'importId');
        $this->resetErrorBag();
    }

    public function render(): View
    {
        return view('core.students.import', [
            'import' => $this->importId ? Import::find($this->importId) : null,
        ]);
    }
}
