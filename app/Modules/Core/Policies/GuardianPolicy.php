<?php

namespace App\Modules\Core\Policies;

use App\Modules\Core\Models\Guardian;
use App\Modules\Core\Models\User;

class GuardianPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('students.view-all');
    }

    public function view(User $user, Guardian $guardian): bool
    {
        return $user->can('students.view-all') || $guardian->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->can('guardians.manage');
    }

    public function update(User $user, Guardian $guardian): bool
    {
        return $user->can('guardians.manage');
    }

    public function delete(User $user, Guardian $guardian): bool
    {
        return $user->can('guardians.manage');
    }
}
