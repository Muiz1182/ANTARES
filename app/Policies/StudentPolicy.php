<?php

namespace App\Policies;

use App\Enums\PermissionEnum;
use App\Models\Student;
use App\Models\User;

class StudentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionEnum::MasterManage->value)
            || $user->can(PermissionEnum::ReportsView->value)
            || $user->can(PermissionEnum::AttendanceViewAll->value)
            || $user->can(PermissionEnum::AttendanceViewOwnClass->value);
    }

    public function view(User $user, Student $student): bool
    {
        if (
            $user->can(PermissionEnum::MasterManage->value)
            || $user->can(PermissionEnum::ReportsView->value)
            || $user->can(PermissionEnum::AttendanceViewAll->value)
        ) {
            return true;
        }

        if (! $user->can(PermissionEnum::AttendanceViewOwnClass->value)) {
            return false;
        }

        return $student->classroom !== null
            && $student->classroom->homeroom_teacher_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionEnum::MasterManage->value);
    }

    public function update(User $user, Student $student): bool
    {
        return $user->can(PermissionEnum::MasterManage->value);
    }

    public function delete(User $user, Student $student): bool
    {
        return $user->can(PermissionEnum::MasterManage->value);
    }

    public function import(User $user): bool
    {
        return $user->can(PermissionEnum::MasterManage->value);
    }
}
