<?php

namespace App\Policies;

use App\Enums\PermissionEnum;
use App\Models\Classroom;
use App\Models\User;

class ClassroomPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionEnum::MasterManage->value)
            || $user->can(PermissionEnum::ReportsView->value)
            || $user->can(PermissionEnum::AttendanceViewOwnClass->value);
    }

    public function view(User $user, Classroom $classroom): bool
    {
        if (
            $user->can(PermissionEnum::MasterManage->value)
            || $user->can(PermissionEnum::ReportsView->value)
        ) {
            return true;
        }

        return $user->can(PermissionEnum::AttendanceViewOwnClass->value)
            && $classroom->homeroom_teacher_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionEnum::MasterManage->value);
    }

    public function update(User $user, Classroom $classroom): bool
    {
        return $user->can(PermissionEnum::MasterManage->value);
    }

    public function delete(User $user, Classroom $classroom): bool
    {
        return $user->can(PermissionEnum::MasterManage->value);
    }
}
