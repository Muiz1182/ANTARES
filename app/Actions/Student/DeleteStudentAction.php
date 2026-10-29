<?php

namespace App\Actions\Student;

use App\Models\Student;

class DeleteStudentAction
{
    public function execute(Student $student): void
    {
        $student->delete();
    }
}
