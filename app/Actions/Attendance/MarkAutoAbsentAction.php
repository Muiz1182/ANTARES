<?php

namespace App\Actions\Attendance;

use App\Enums\AttendanceSourceEnum;
use App\Enums\AttendanceStatusEnum;
use App\Models\Attendance;
use App\Models\AttendanceRule;
use App\Models\Holiday;
use App\Models\Student;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class MarkAutoAbsentAction
{
    public function execute(
        CarbonInterface|string|null $evaluatedAt = null,
        bool $ignoreCutoffTime = false,
    ): int {
        $evalTime = $evaluatedAt instanceof CarbonInterface
            ? Carbon::instance($evaluatedAt)->setTimezone('Asia/Jakarta')
            : ($evaluatedAt !== null && trim((string) $evaluatedAt) !== ''
                ? Carbon::parse((string) $evaluatedAt)->setTimezone('Asia/Jakarta')
                : Carbon::now('Asia/Jakarta'));

        $dateString = $evalTime->toDateString();

        if (Holiday::query()->where('date', $dateString)->exists()) {
            return 0;
        }

        $rule = AttendanceRule::query()
            ->where('day_of_week', $evalTime->isoWeekday())
            ->first();

        if ($rule === null || ! $rule->is_school_day) {
            return 0;
        }

        $cutoffTime = strlen(trim($rule->absent_cutoff)) === 5
            ? trim($rule->absent_cutoff).':00'
            : trim($rule->absent_cutoff);

        if (! $ignoreCutoffTime && $evalTime->format('H:i:s') < $cutoffTime) {
            return 0;
        }

        return DB::transaction(function () use ($dateString): int {
            $existingStudentIds = Attendance::query()
                ->where('attendable_type', Student::class)
                ->where('date', $dateString)
                ->pluck('attendable_id')
                ->all();

            $missingStudents = Student::query()
                ->where('is_active', true)
                ->when(count($existingStudentIds) > 0, static function ($query) use ($existingStudentIds): void {
                    $query->whereNotIn('id', $existingStudentIds);
                })
                ->get(['id']);

            $createdCount = 0;

            foreach ($missingStudents as $student) {
                $attendance = Attendance::query()->firstOrCreate(
                    [
                        'attendable_type' => Student::class,
                        'attendable_id' => $student->id,
                        'date' => $dateString,
                    ],
                    [
                        'check_in_at' => null,
                        'check_out_at' => null,
                        'status' => AttendanceStatusEnum::Absent,
                        'source' => AttendanceSourceEnum::System,
                        'device_id' => null,
                        'note' => null,
                        'created_by' => null,
                    ]
                );

                if ($attendance->wasRecentlyCreated) {
                    $createdCount++;
                }
            }

            return $createdCount;
        });
    }
}
