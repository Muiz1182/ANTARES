<?php

namespace App\Http\Requests\Student;

use App\Enums\GenderEnum;
use App\Models\Student;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Student $student */
        $student = $this->route('student');

        return $this->user()?->can('update', $student) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Student $student */
        $student = $this->route('student');

        return [
            'nis' => ['required', 'string', 'max:32', Rule::unique('students', 'nis')->ignore($student->id)],
            'nisn' => ['nullable', 'string', 'max:32', Rule::unique('students', 'nisn')->ignore($student->id)],
            'name' => ['required', 'string', 'max:255'],
            'gender' => ['required', 'string', Rule::in(GenderEnum::values())],
            'classroom_id' => ['nullable', 'integer', Rule::exists('classrooms', 'id')],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'parent_name' => ['nullable', 'string', 'max:255'],
            'parent_phone' => ['nullable', 'string', 'max:32'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
