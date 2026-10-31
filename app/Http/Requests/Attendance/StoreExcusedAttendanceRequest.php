<?php

namespace App\Http\Requests\Attendance;

use App\Enums\AttendanceStatusEnum;
use App\Models\Attendance;
use App\Models\Student;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExcusedAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }

        $studentId = $this->input('student_id');
        $student = is_numeric($studentId)
            ? Student::with('classroom')->find((int) $studentId)
            : null;

        return $user->can('excuse', [Attendance::class, $student]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'student_id' => [
                'required',
                'integer',
                Rule::exists('students', 'id')->whereNull('deleted_at'),
            ],
            'status' => [
                'required',
                'string',
                Rule::in([
                    AttendanceStatusEnum::Permission->value,
                    AttendanceStatusEnum::Sick->value,
                    AttendanceStatusEnum::Dispensation->value,
                ]),
            ],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'note' => ['nullable', 'string', 'max:500'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:2048'],
        ];
    }
}
