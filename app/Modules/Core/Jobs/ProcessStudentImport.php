<?php

namespace App\Modules\Core\Jobs;

use App\Modules\Core\Actions\ReadStudentImport;
use App\Modules\Core\Actions\SaveGuardian;
use App\Modules\Core\Actions\SaveStudent;
use App\Modules\Core\Models\Import;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcessStudentImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(public Import $import) {}

    public function handle(ReadStudentImport $reader, SaveStudent $students, SaveGuardian $guardians): void
    {
        $this->import->update(['status' => 'processing']);

        $rows = $reader->handle($this->import->file_path);
        $success = 0;
        $errors = [];

        foreach ($rows as $row) {
            if ($row['errors'] !== []) {
                $errors[] = ['row' => $row['row'], 'name' => $row['student']['name'], 'messages' => $row['errors']];

                continue;
            }

            try {
                // Satu baris gagal tidak membatalkan baris lain.
                DB::transaction(function () use ($row, $students, $guardians): void {
                    $data = array_filter($row['student'], fn ($value) => $value !== null);
                    [$student] = $students->handle($data, null, issuePassword: false);

                    if ($row['guardian'] !== null) {
                        $guardian = $guardians->handle(['name' => $row['guardian']['name'], 'phone_wa' => $row['guardian']['phone_wa']]);
                        $guardian->students()->syncWithoutDetaching([$student->id => [
                            'relation' => $row['guardian']['relation'],
                            'is_primary' => true,
                            'is_payer' => true,
                        ]]);
                    }
                });
                $success++;
            } catch (Throwable $exception) {
                report($exception);
                $errors[] = ['row' => $row['row'], 'name' => $row['student']['name'], 'messages' => ['Baris ini gagal disimpan. Periksa datanya lalu coba lagi.']];
            }
        }

        $this->import->update([
            'status' => 'completed',
            'total_rows' => count($rows),
            'success_rows' => $success,
            'failed_rows' => count($errors),
            'errors' => $errors,
        ]);

        Storage::disk('local')->delete($this->import->file_path);
    }

    public function failed(Throwable $exception): void
    {
        $this->import->update(['status' => 'failed', 'errors' => [['row' => 0, 'name' => null, 'messages' => ['Berkas tidak bisa diproses. Unggah ulang memakai template terbaru.']]]]);
    }
}
