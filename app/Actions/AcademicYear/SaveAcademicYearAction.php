<?php

namespace App\Actions\AcademicYear;

use App\Models\AcademicYear;
use Illuminate\Support\Facades\DB;

class SaveAcademicYearAction
{
    /**
     * @param  array{name: string, start_date: string, end_date: string, is_active: bool}  $data
     */
    public function execute(array $data, ?AcademicYear $academicYear = null): AcademicYear
    {
        return DB::transaction(function () use ($data, $academicYear): AcademicYear {
            if ($data['is_active']) {
                AcademicYear::query()
                    ->when($academicYear, fn ($query) => $query->where('id', '!=', $academicYear->id))
                    ->where('is_active', true)
                    ->update(['is_active' => false]);
            }

            if ($academicYear) {
                $academicYear->update($data);

                return $academicYear->refresh();
            }

            return AcademicYear::create($data);
        });
    }
}
