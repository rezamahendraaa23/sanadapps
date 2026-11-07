<?php

namespace App\Modules\Academic\Models;

use App\Modules\Core\Models\Teacher;
use App\Modules\Core\Models\User;

final class TeachingScope
{
    public static function teacher(User $user): ?Teacher
    {
        return Teacher::query()->where('user_id', $user->id)->first();
    }

    /**
     * @return list<int>
     */
    public static function classroomIds(User $user): array
    {
        $teacher = self::teacher($user);

        return $teacher === null ? [] : TeachingAssignment::query()->where('teacher_id', $teacher->id)->pluck('classroom_id')->unique()->values()->all();
    }
}
