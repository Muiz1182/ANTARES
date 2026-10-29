<?php

namespace App\Http\Resources;

use App\Enums\RoleEnum;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Holiday;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class AuthUserResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;
        $roleNames = $user->getRoleNames()->values()->all();

        $roleLabels = array_map(static function (string $roleName): string {
            $enum = RoleEnum::tryFrom($roleName);

            return $enum ? $enum->label() : $roleName;
        }, $roleNames);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'nip' => $user->nip,
            'phone' => $user->phone,
            'is_active' => (bool) $user->is_active,
            'roles' => $roleNames,
            'role_labels' => $roleLabels,
            'permissions' => $user->getAllPermissions()->pluck('name')->values()->all(),
            'can' => [
                'manage_users' => $user->can('viewAny', User::class),
                'manage_master' => $user->can('create', Student::class),
                'view_classrooms' => $user->can('viewAny', Classroom::class),
                'view_students' => $user->can('viewAny', Student::class),
                'view_academic_years' => $user->can('viewAny', AcademicYear::class),
                'view_holidays' => $user->can('viewAny', Holiday::class),
            ],
        ];
    }
}
