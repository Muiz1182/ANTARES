<?php

namespace App\Actions\Classroom;

use App\Models\Classroom;

class DeleteClassroomAction
{
    public function execute(Classroom $classroom): void
    {
        $classroom->delete();
    }
}
