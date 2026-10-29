<?php

namespace App\Policies;

use App\Enums\PermissionEnum;
use App\Models\AcademicYear;
use App\Models\User;

class AcademicYearPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionEnum::MasterManage->value)
            || $user->can(PermissionEnum::ReportsView->value);
    }

    public function view(User $user, AcademicYear $academicYear): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionEnum::MasterManage->value);
    }

    public function update(User $user, AcademicYear $academicYear): bool
    {
        return $user->can(PermissionEnum::MasterManage->value);
    }

    public function delete(User $user, AcademicYear $academicYear): bool
    {
        return $user->can(PermissionEnum::MasterManage->value);
    }
}
