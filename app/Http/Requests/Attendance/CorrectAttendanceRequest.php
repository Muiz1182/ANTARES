<?php

namespace App\Http\Requests\Attendance;

use App\Enums\AttendanceStatusEnum;
use App\Models\Attendance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CorrectAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('correct', Attendance::class) ?? false;
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
            'date' => ['required', 'date_format:Y-m-d'],
            'status' => [
                'required',
                'string',
                Rule::in(AttendanceStatusEnum::values()),
            ],
            'check_in_time' => ['nullable', 'date_format:H:i'],
            'check_out_time' => ['nullable', 'date_format:H:i'],
            'note' => ['nullable', 'string', 'max:500'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }
}
