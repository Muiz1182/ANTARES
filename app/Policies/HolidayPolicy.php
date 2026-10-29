<?php

namespace App\Policies;

use App\Enums\PermissionEnum;
use App\Models\Holiday;
use App\Models\User;

class HolidayPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionEnum::MasterManage->value)
            || $user->can(PermissionEnum::ReportsView->value);
    }

    public function view(User $user, Holiday $holiday): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionEnum::MasterManage->value);
    }

    public function update(User $user, Holiday $holiday): bool
    {
        return $user->can(PermissionEnum::MasterManage->value);
    }

    public function delete(User $user, Holiday $holiday): bool
    {
        return $user->can(PermissionEnum::MasterManage->value);
    }
}
