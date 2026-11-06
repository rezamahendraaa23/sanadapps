<?php

namespace App\Modules\Core\Actions;

use App\Modules\Core\Models\Classroom;
use App\Modules\Core\Models\Dormitory;
use App\Modules\Core\Models\Student;
use App\Modules\Core\Support\StudentImportRow;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Facades\Excel;

class ReadStudentImport
{
    /**
     * Memeriksa semua baris tanpa menyimpan apa pun, untuk pratinjau dan juga sebelum eksekusi.
     *
     * @return list<array{row: int, student: array<string, mixed>, guardian: array<string, string>|null, errors: list<string>}>
     */
    public function handle(string $path, string $disk = 'local'): array
    {
        $sheet = Excel::toArray(new class implements ToArray, WithHeadingRow
        {
            public function array(array $array): void {}
        }, $path, $disk)[0] ?? [];

        $classrooms = Classroom::query()->pluck('id', 'name')->mapWithKeys(fn ($id, $name) => [mb_strtolower($name) => $id])->all();
        $dormitories = Dormitory::query()->pluck('id', 'name')->mapWithKeys(fn ($id, $name) => [mb_strtolower($name) => $id])->all();
        $existing = Student::withTrashed()->pluck('nis')->flip()->map(fn () => true)->all();

        $rows = [];
        $seen = [];

        foreach ($sheet as $index => $raw) {
            if (collect($raw)->filter(fn ($value) => filled($value))->isEmpty()) {
                continue;
            }

            $parsed = StudentImportRow::parse($raw, $classrooms, $dormitories, $existing);
            $nis = $parsed['student']['nis'];

            if ($nis !== null && isset($seen[$nis])) {
                $parsed['errors'][] = "NIS {$nis} ganda di baris {$seen[$nis]}.";
            }

            $line = $index + 2;
            if ($nis !== null) {
                $seen[$nis] ??= $line;
            }

            $rows[] = ['row' => $line, ...$parsed];
        }

        return $rows;
    }
}
