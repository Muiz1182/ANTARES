<?php

namespace App\Policies;

use App\Enums\PermissionEnum;
use App\Models\Device;
use App\Models\User;

class DevicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionEnum::DevicesManage->value);
    }

    public function view(User $user, Device $device): bool
    {
        return $user->can(PermissionEnum::DevicesManage->value);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionEnum::DevicesManage->value);
    }

    public function update(User $user, Device $device): bool
    {
        return $user->can(PermissionEnum::DevicesManage->value);
    }

    public function delete(User $user, Device $device): bool
    {
        return $user->can(PermissionEnum::DevicesManage->value);
    }
}

