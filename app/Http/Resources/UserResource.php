<?php

namespace App\Http\Resources;

use App\Enums\RoleEnum;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $roleName = $this->roles->first()?->name;
        $roleEnum = $roleName ? RoleEnum::tryFrom($roleName) : null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'nip' => $this->nip,
            'phone' => $this->phone,
            'is_active' => (bool) $this->is_active,
            'role' => $roleName,
            'role_label' => $roleEnum?->label() ?? $roleName,
        ];
    }
}
