<?php

namespace App\Http\Requests\Setting;

use App\Models\SchoolSetting;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSchoolSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', SchoolSetting::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'school_name' => ['required', 'string', 'max:150'],
            'school_address' => ['nullable', 'string', 'max:255'],
            'principal_name' => ['nullable', 'string', 'max:150'],
            'principal_nip' => ['nullable', 'string', 'max:40'],
            'logo' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:2048'],
            'remove_logo' => ['nullable', 'boolean'],
        ];
    }
}

