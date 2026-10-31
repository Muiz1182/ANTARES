<?php

namespace App\Http\Requests\Report;

use App\Enums\PermissionEnum;
use App\Models\Classroom;
use Illuminate\Foundation\Http\FormRequest;

class AttendanceReportFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }

        $canViewAllReports = $user->can(PermissionEnum::ReportsView->value);
        $canViewOwnClass = $user->can(PermissionEnum::AttendanceViewOwnClass->value);

        if (! $canViewAllReports && ! $canViewOwnClass) {
            return false;
        }

        $classroomId = $this->input('classroom_id');
        if ($classroomId !== null && $classroomId !== '' && is_numeric($classroomId)) {
            $classroom = Classroom::query()->find((int) $classroomId);
            if ($classroom === null) {
                return false;
            }

            if (! $canViewAllReports && $classroom->homeroom_teacher_id !== $user->id) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'classroom_id' => ['nullable', 'integer', 'exists:classrooms,id'],
            'month' => ['nullable', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'student_id' => ['nullable', 'integer', 'exists:students,id'],
        ];
    }
}

