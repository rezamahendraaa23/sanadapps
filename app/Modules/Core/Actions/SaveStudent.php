<?php

namespace App\Modules\Core\Actions;

use App\Modules\Core\Models\AcademicYear;
use App\Modules\Core\Models\Student;
use App\Modules\Core\Models\StudentEnrollment;
use App\Modules\Core\Models\User;
use App\Support\SchoolDate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SaveStudent
{
    public function __construct(private GenerateNis $nis) {}

    /**
     * Santri baru langsung punya akun masuk memakai NIS; perpindahan kelas tercatat di student_enrollments.
     *
     * @param  array<string, mixed>  $data
     * @return array{Student, string|null} Santri dan sandi awal bila akun baru dibuat.
     */
    public function handle(array $data, ?Student $student = null, bool $issuePassword = true): array
    {
        return DB::transaction(function () use ($data, $student, $issuePassword): array {
            $password = null;

            if ($student === null) {
                $data['nis'] = filled($data['nis'] ?? null) ? $data['nis'] : $this->nis->handle();
                $data['entry_date'] ??= SchoolDate::today()->toDateString();
                $password = $issuePassword ? Str::password(10, symbols: false) : null;

                $user = User::create(['name' => $data['name'], 'username' => $data['nis'], 'type' => 'student', 'password' => $password]);
                $user->assignRole('santri');

                $student = Student::create([...$data, 'user_id' => $user->id]);
                $this->openEnrollment($student);

                return [$student, $password];
            }

            $classChanged = array_key_exists('classroom_id', $data) && (int) $data['classroom_id'] !== (int) $student->classroom_id;
            $student->update($data);

            if ($student->wasChanged('nis') || $student->wasChanged('name')) {
                $student->user?->update(['username' => $student->nis, 'name' => $student->name]);
            }

            if ($classChanged) {
                $this->moveEnrollment($student);
            }

            return [$student, $password];
        });
    }

    public function moveEnrollment(Student $student, string $closingStatus = 'moved', ?string $batch = null): void
    {
        StudentEnrollment::query()->where('student_id', $student->id)->whereNull('ended_at')->get()
            ->each->update(['ended_at' => now(), 'status' => $closingStatus, 'batch_id' => $batch]);

        $this->openEnrollment($student, $batch);
    }

    private function openEnrollment(Student $student, ?string $batch = null): void
    {
        if ($student->classroom_id === null) {
            return;
        }

        StudentEnrollment::create([
            'student_id' => $student->id,
            'classroom_id' => $student->classroom_id,
            'academic_year_id' => AcademicYear::query()->where('is_active', true)->value('id'),
            'started_at' => now(),
            'batch_id' => $batch,
        ]);
    }
}
