<?php

namespace App\Http\Resources;

use App\Models\Attendance;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Student
 */
class ClassTodayStudentAttendanceResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Student $student */
        $student = $this->resource;

        /** @var Attendance|null $attendance */
        $attendance = $student->relationLoaded('attendances')
            ? $student->attendances->first()
            : null;

        return [
            'id' => $student->id,
            'nis' => $student->nis,
            'name' => $student->name,
            'gender' => $student->gender?->value,
            'gender_label' => $student->gender?->label(),
            'photo_url' => $student->photoUrl(),
            'classroom' => $student->relationLoaded('classroom') && $student->classroom
                ? [
                    'id' => $student->classroom->id,
                    'name' => $student->classroom->name,
                    'level' => $student->classroom->level,
                ]
                : null,
            'attendance' => $attendance !== null
                ? (new AttendanceResource($attendance))->resolve($request)
                : null,
        ];
    }
}
