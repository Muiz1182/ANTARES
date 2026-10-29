<?php

namespace App\Policies;

use App\Enums\PermissionEnum;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionEnum::UsersManage->value);
    }

    public function view(User $user, User $model): bool
    {
        return $user->can(PermissionEnum::UsersManage->value);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionEnum::UsersManage->value);
    }

    public function update(User $user, User $model): bool
    {
        return $user->can(PermissionEnum::UsersManage->value);
    }

    public function delete(User $user, User $model): bool
    {
        return $user->can(PermissionEnum::UsersManage->value) && $user->id !== $model->id;
    }
}
