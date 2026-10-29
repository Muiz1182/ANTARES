<?php

namespace App\Actions\AcademicYear;

use App\Models\AcademicYear;
use DomainException;

class DeleteAcademicYearAction
{
    public function execute(AcademicYear $academicYear): void
    {
        if ($academicYear->is_active) {
            throw new DomainException(__('ui.messages.academic_year_active_delete_blocked'));
        }

        if ($academicYear->classrooms()->exists()) {
            throw new DomainException(__('ui.messages.academic_year_has_classrooms'));
        }

        $academicYear->delete();
    }
}
