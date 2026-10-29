<?php

namespace App\Http\Resources;

use App\Models\Classroom;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Classroom
 */
class ClassroomResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'academic_year_id' => $this->academic_year_id,
            'academic_year_name' => $this->whenLoaded('academicYear', fn (): ?string => $this->academicYear?->name),
            'name' => $this->name,
            'level' => $this->level,
            'homeroom_teacher_id' => $this->homeroom_teacher_id,
            'homeroom_teacher_name' => $this->whenLoaded('homeroomTeacher', fn (): ?string => $this->homeroomTeacher?->name),
            'students_count' => $this->whenCounted('students'),
        ];
    }
}
