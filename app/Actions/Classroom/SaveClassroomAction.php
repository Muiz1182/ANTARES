<?php

namespace App\Actions\Classroom;

use App\Models\Classroom;

class SaveClassroomAction
{
    /**
     * @param  array{academic_year_id: int, name: string, level: int, homeroom_teacher_id?: int|null}  $data
     */
    public function execute(array $data, ?Classroom $classroom = null): Classroom
    {
        $payload = [
            'academic_year_id' => (int) $data['academic_year_id'],
            'name' => trim($data['name']),
            'level' => (int) $data['level'],
            'homeroom_teacher_id' => $data['homeroom_teacher_id'] ?? null,
        ];

        if ($classroom) {
            $classroom->update($payload);

            return $classroom->refresh();
        }

        return Classroom::create($payload);
    }
}
