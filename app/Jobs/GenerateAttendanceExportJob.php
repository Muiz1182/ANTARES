<?php

namespace App\Jobs;

use App\Models\Classroom;
use App\Services\Report\AttendanceExcelExportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

class GenerateAttendanceExportJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $classroomId,
        public readonly string $month,
        public readonly string $storageRelativePath,
    ) {}

    public function handle(AttendanceExcelExportService $excelExportService): void
    {
        $classroom = Classroom::query()->find($this->classroomId);
        if ($classroom === null) {
            return;
        }

        $tempPath = $excelExportService->generateMonthlyClassXlsx($classroom, $this->month);

        if (is_file($tempPath)) {
            $contents = file_get_contents($tempPath);
            if ($contents !== false) {
                Storage::disk('local')->put($this->storageRelativePath, $contents);
            }
            @unlink($tempPath);
        }
    }
}

