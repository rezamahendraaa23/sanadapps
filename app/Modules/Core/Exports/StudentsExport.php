<?php

namespace App\Modules\Core\Exports;

use App\Modules\Core\Models\Student;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class StudentsExport implements FromQuery, WithHeadings, WithMapping
{
    public function __construct(private User $user) {}

    public function query(): Builder
    {
        return Student::query()->visibleTo($this->user)->with(['classroom:id,name', 'dormitory:id,name'])->orderBy('name');
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return ['NIS', 'NISN', 'Nama', 'Jenis kelamin', 'Kelas', 'Asrama', 'Status'];
    }

    /**
     * @param  Student  $student
     * @return list<string|null>
     */
    public function map($student): array
    {
        return [$student->nis, $student->nisn, $student->name, $student->gender, $student->classroom?->name, $student->dormitory?->name, $student->status];
    }
}
