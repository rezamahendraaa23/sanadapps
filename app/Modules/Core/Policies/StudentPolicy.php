<?php

namespace App\Modules\Core\Policies;

use App\Modules\Core\Models\Student;
use App\Modules\Core\Models\User;

/**
 * Kepemilikan dicek di sini, bukan di UI: wali hanya anaknya, santri hanya dirinya (PRD Sistem 10).
 */
class StudentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAny(['students.view-all', 'students.view-own-class', 'students.view-own-dormitory']);
    }

    public function view(User $user, Student $student): bool
    {
        return Student::query()->visibleTo($user)->whereKey($student->getKey())->exists();
    }

    public function create(User $user): bool
    {
        return $user->can('students.manage');
    }

    public function update(User $user, Student $student): bool
    {
        return $user->can('students.manage');
    }

    public function delete(User $user, Student $student): bool
    {
        return $user->can('students.manage');
    }
}
