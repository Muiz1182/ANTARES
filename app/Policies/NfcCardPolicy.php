<?php

namespace App\Policies;

use App\Enums\PermissionEnum;
use App\Models\NfcCard;
use App\Models\User;

class NfcCardPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionEnum::CardsManage->value);
    }

    public function view(User $user, NfcCard $nfcCard): bool
    {
        return $user->can(PermissionEnum::CardsManage->value);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionEnum::CardsManage->value);
    }

    public function update(User $user, NfcCard $nfcCard): bool
    {
        return $user->can(PermissionEnum::CardsManage->value);
    }

    public function delete(User $user, NfcCard $nfcCard): bool
    {
        return $user->can(PermissionEnum::CardsManage->value);
    }
}

