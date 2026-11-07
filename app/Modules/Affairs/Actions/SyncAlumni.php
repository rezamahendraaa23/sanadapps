<?php

namespace App\Modules\Affairs\Actions;

use App\Modules\Affairs\Models\AlumniProfile;
use App\Modules\Core\Models\Student;

class SyncAlumni
{
    /**
     * Santri yang lulus otomatis menjadi alumni (ALM-01). Kontak awal dari wali utama.
     */
    public function handle(): int
    {
        $count = 0;

        Student::query()->where('status', 'graduated')->whereDoesntHave('alumniProfile')->with(['guardians', 'enrollments'])->each(function (Student $student) use (&$count): void {
            $primary = $student->guardians->firstWhere('pivot.is_primary', true) ?? $student->guardians->first();
            AlumniProfile::create([
                'student_id' => $student->id,
                'graduated_on' => $student->enrollments->where('status', 'graduated')->max('ended_at')?->toDateString(),
                'contact_phone' => $primary?->phone_wa,
                'contact_email' => $primary?->email,
            ]);
            $count++;
        });

        return $count;
    }
}
