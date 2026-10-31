<?php

namespace App\Policies;

use App\Enums\PermissionEnum;
use App\Models\User;

class SchoolSettingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionEnum::UsersManage->value);
    }

    public function update(User $user): bool
    {
        return $user->can(PermissionEnum::UsersManage->value);
    }
}

