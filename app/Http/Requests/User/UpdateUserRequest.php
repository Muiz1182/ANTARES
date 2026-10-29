<?php

namespace App\Http\Requests\User;

use App\Enums\RoleEnum;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User $managedUser */
        $managedUser = $this->route('user');

        return $this->user()?->can('update', $managedUser) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var User $managedUser */
        $managedUser = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($managedUser->id)],
            'nip' => ['nullable', 'string', 'max:32', Rule::unique('users', 'nip')->ignore($managedUser->id)],
            'phone' => ['nullable', 'string', 'max:32'],
            'role' => ['required', 'string', Rule::in(RoleEnum::values())],
            'password' => ['nullable', 'string', 'min:8'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
