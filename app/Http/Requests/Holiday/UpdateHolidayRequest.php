<?php

namespace App\Http\Requests\Holiday;

use App\Models\Holiday;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateHolidayRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Holiday $holiday */
        $holiday = $this->route('holiday');

        return $this->user()?->can('update', $holiday) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Holiday $holiday */
        $holiday = $this->route('holiday');

        return [
            'date' => ['required', 'date', Rule::unique('holidays', 'date')->ignore($holiday->id)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }
}
