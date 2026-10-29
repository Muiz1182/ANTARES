<?php

namespace App\Http\Requests\AcademicYear;

use App\Models\AcademicYear;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAcademicYearRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var AcademicYear $academicYear */
        $academicYear = $this->route('academic_year');

        return $this->user()?->can('update', $academicYear) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var AcademicYear $academicYear */
        $academicYear = $this->route('academic_year');

        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('academic_years', 'name')->ignore($academicYear->id)],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
