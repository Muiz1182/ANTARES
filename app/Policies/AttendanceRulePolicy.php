<?php

namespace App\Policies;

use App\Enums\PermissionEnum;
use App\Models\AttendanceRule;
use App\Models\User;

class AttendanceRulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionEnum::RulesManage->value)
            || $user->can(PermissionEnum::ReportsView->value);
    }

    public function update(User $user, ?AttendanceRule $rule = null): bool
    {
        return $user->can(PermissionEnum::RulesManage->value);
    }
}
