<?php

namespace App\Http\Requests\Classroom;

use App\Models\Classroom;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClassroomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Classroom::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'academic_year_id' => ['required', 'integer', Rule::exists('academic_years', 'id')],
            'name' => [
                'required',
                'string',
                'max:50',
                Rule::unique('classrooms', 'name')->where('academic_year_id', $this->input('academic_year_id')),
            ],
            'level' => ['required', 'integer', Rule::in([7, 8, 9])],
            'homeroom_teacher_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
        ];
    }
}
