<?php

namespace App\Http\Requests\User;

use App\Enums\RoleEnum;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', User::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'nip' => ['nullable', 'string', 'max:32', Rule::unique('users', 'nip')],
            'phone' => ['nullable', 'string', 'max:32'],
            'role' => ['required', 'string', Rule::in(RoleEnum::values())],
            'password' => ['required', 'string', 'min:8'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
