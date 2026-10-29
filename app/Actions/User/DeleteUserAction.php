<?php

namespace App\Actions\User;

use App\Models\User;
use DomainException;

class DeleteUserAction
{
    public function execute(User $actor, User $target): void
    {
        if ($actor->id === $target->id) {
            throw new DomainException(__('ui.messages.user_self_delete_blocked'));
        }

        $target->delete();
    }
}
