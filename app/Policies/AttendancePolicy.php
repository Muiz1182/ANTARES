<?php

namespace App\Policies;

use App\Enums\PermissionEnum;
use App\Models\Classroom;
use App\Models\Student;
use App\Models\User;

class AttendancePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionEnum::AttendanceViewAll->value)
            || $user->can(PermissionEnum::AttendanceViewOwnClass->value);
    }

    public function viewClassroom(User $user, Classroom $classroom): bool
    {
        if ($user->can(PermissionEnum::AttendanceViewAll->value)) {
            return true;
        }

        return $user->can(PermissionEnum::AttendanceViewOwnClass->value)
            && $classroom->homeroom_teacher_id === $user->id;
    }

    public function excuse(User $user, ?Student $student = null): bool
    {
        if (! $user->can(PermissionEnum::AttendanceExcuse->value)) {
            return false;
        }

        if ($student === null || $user->can(PermissionEnum::AttendanceViewAll->value)) {
            return true;
        }

        $student->loadMissing('classroom');

        return $student->classroom !== null
            && $student->classroom->homeroom_teacher_id === $user->id;
    }

    public function correct(User $user): bool
    {
        return $user->can(PermissionEnum::AttendanceCorrect->value);
    }
}
