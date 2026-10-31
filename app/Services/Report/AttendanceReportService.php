<?php

namespace App\Services\Report;

use App\Enums\AttendanceSourceEnum;
use App\Enums\AttendanceStatusEnum;
use App\Models\Attendance;
use App\Models\AttendanceRule;
use App\Models\Classroom;
use App\Models\Holiday;
use App\Models\Student;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;

class AttendanceReportService
{
    /**
     * Map AttendanceStatusEnum value to single-character matrix code (H/T/I/S/A/D).
     */
    public static function statusToCode(string $status): string
    {
        return match ($status) {
            AttendanceStatusEnum::Present->value => 'H',
            AttendanceStatusEnum::Late->value => 'T',
            AttendanceStatusEnum::Permission->value => 'I',
            AttendanceStatusEnum::Sick->value => 'S',
            AttendanceStatusEnum::Absent->value => 'A',
            AttendanceStatusEnum::Dispensation->value => 'D',
            default => '-',
        };
    }

    /**
     * Build the monthly class matrix (students x dates with H/T/I/S/A/D and totals).
     *
     * @return array<string, mixed>
     */
    public function getMonthlyClassMatrix(Classroom $classroom, string $month): array
    {
        $validMonth = preg_match('/^\d{4}-\d{2}$/', $month) === 1
            ? $month
            : CarbonImmutable::now('Asia/Jakarta')->format('Y-m');

        $startOfMonth = CarbonImmutable::createFromFormat('Y-m-d', $validMonth.'-01', 'Asia/Jakarta')->startOfDay();
        $endOfMonth = $startOfMonth->endOfMonth()->startOfDay();

        $rulesByDay = AttendanceRule::query()->get()->keyBy('day_of_week');
        $holidaysByDate = Holiday::query()
            ->whereBetween('date', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
            ->get()
            ->keyBy(fn (Holiday $h): string => $h->date instanceof \DateTimeInterface ? $h->date->format('Y-m-d') : (string) $h->date);

        $days = [];
        $period = CarbonPeriod::create($startOfMonth, '1 day', $endOfMonth);
        foreach ($period as $dateItem) {
            $dObj = CarbonImmutable::instance($dateItem)->setTimezone('Asia/Jakarta');
            $dStr = $dObj->toDateString();
            $rule = $rulesByDay->get($dObj->isoWeekday());
            $isHoliday = $holidaysByDate->has($dStr);
            $isSchoolDay = ($rule !== null ? $rule->is_school_day : ($dObj->isoWeekday() <= 5)) && ! $isHoliday;

            $days[] = [
                'date' => $dStr,
                'day_number' => (int) $dObj->format('j'),
                'day_label' => (string) __('ui.weekdays.'.$dObj->isoWeekday()),
                'is_school_day' => $isSchoolDay,
                'is_holiday' => $isHoliday,
                'holiday_name' => $isHoliday ? $holidaysByDate->get($dStr)?->name : null,
            ];
        }

        $students = Student::query()
            ->where('classroom_id', $classroom->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $studentIds = $students->pluck('id')->all();

        $attendances = count($studentIds) > 0
            ? Attendance::query()
                ->where('attendable_type', Student::class)
                ->whereIn('attendable_id', $studentIds)
                ->whereBetween('date', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
                ->get()
            : collect();

        $attendanceLookup = [];
        foreach ($attendances as $att) {
            $statusVal = $att->status instanceof AttendanceStatusEnum
                ? $att->status->value
                : (string) $att->status;
            $attendanceLookup[$att->attendable_id][(string) $att->date] = $statusVal;
        }

        $classTotals = [
            'H' => 0,
            'T' => 0,
            'I' => 0,
            'S' => 0,
            'A' => 0,
            'D' => 0,
        ];

        $rows = [];
        foreach ($students as $student) {
            $totals = [
                'H' => 0,
                'T' => 0,
                'I' => 0,
                'S' => 0,
                'A' => 0,
                'D' => 0,
                'attended' => 0,
                'recorded_days' => 0,
                'attendance_rate' => 0.0,
            ];

            $cells = [];
            foreach ($days as $dayInfo) {
                $dStr = $dayInfo['date'];
                $statusVal = $attendanceLookup[$student->id][$dStr] ?? null;
                $code = $statusVal !== null ? self::statusToCode($statusVal) : '-';

                if (isset($totals[$code])) {
                    $totals[$code]++;
                    $totals['recorded_days']++;
                    $classTotals[$code]++;
                }

                $cells[$dStr] = $code;
            }

            $totals['attended'] = $totals['H'] + $totals['T'] + $totals['D'];
            if ($totals['recorded_days'] > 0) {
                $totals['attendance_rate'] = round(($totals['attended'] / $totals['recorded_days']) * 100, 1);
            }

            $rows[] = [
                'student_id' => $student->id,
                'nis' => $student->nis,
                'name' => $student->name,
                'gender' => $student->gender?->value ?? '-',
                'cells' => $cells,
                'totals' => $totals,
            ];
        }

        $classroom->loadMissing(['homeroomTeacher:id,name,nip', 'academicYear:id,name']);

        return [
            'month' => $validMonth,
            'month_label' => $startOfMonth->translatedFormat('F Y'),
            'start_date' => $startOfMonth->toDateString(),
            'end_date' => $endOfMonth->toDateString(),
            'classroom' => [
                'id' => $classroom->id,
                'name' => $classroom->name,
                'level' => $classroom->level,
                'academic_year_name' => $classroom->academicYear?->name,
                'homeroom_teacher_name' => $classroom->homeroomTeacher?->name,
                'homeroom_teacher_nip' => $classroom->homeroomTeacher?->nip,
            ],
            'days' => $days,
            'rows' => $rows,
            'class_totals' => $classTotals,
        ];
    }

    /**
     * Get top late and top absent students in the given date range and scope.
     *
     * @return array{top_late: list<array<string, mixed>>, top_absent: list<array<string, mixed>>}
     */
    public function getTopLateAndAbsent(
        User $user,
        string $dateFrom,
        string $dateTo,
        ?int $classroomId = null,
        int $limit = 15,
    ): array {
        $students = Student::query()
            ->visibleTo($user)
            ->where('is_active', true)
            ->when($classroomId !== null && $classroomId > 0, static function ($q) use ($classroomId): void {
                $q->where('classroom_id', $classroomId);
            })
            ->with('classroom:id,name')
            ->get(['id', 'nis', 'name', 'classroom_id']);

        if ($students->isEmpty()) {
            return [
                'top_late' => [],
                'top_absent' => [],
            ];
        }

        $studentMap = $students->keyBy('id');

        $attendances = Attendance::query()
            ->where('attendable_type', Student::class)
            ->whereIn('attendable_id', $studentMap->keys()->all())
            ->whereBetween('date', [$dateFrom, $dateTo])
            ->whereIn('status', [
                AttendanceStatusEnum::Late->value,
                AttendanceStatusEnum::Absent->value,
            ])
            ->get(['attendable_id', 'status']);

        $lateCounts = [];
        $absentCounts = [];

        foreach ($attendances as $att) {
            $sId = (int) $att->attendable_id;
            $statusVal = $att->status instanceof AttendanceStatusEnum
                ? $att->status->value
                : (string) $att->status;

            if ($statusVal === AttendanceStatusEnum::Late->value) {
                $lateCounts[$sId] = ($lateCounts[$sId] ?? 0) + 1;
            } elseif ($statusVal === AttendanceStatusEnum::Absent->value) {
                $absentCounts[$sId] = ($absentCounts[$sId] ?? 0) + 1;
            }
        }

        arsort($lateCounts);
        arsort($absentCounts);

        $topLate = [];
        foreach (array_slice($lateCounts, 0, $limit, true) as $sId => $count) {
            /** @var Student|null $st */
            $st = $studentMap->get($sId);
            if ($st === null) {
                continue;
            }
            $topLate[] = [
                'student_id' => $st->id,
                'nis' => $st->nis,
                'name' => $st->name,
                'classroom_name' => $st->classroom?->name ?? '-',
                'late_count' => $count,
            ];
        }

        $topAbsent = [];
        foreach (array_slice($absentCounts, 0, $limit, true) as $sId => $count) {
            /** @var Student|null $st */
            $st = $studentMap->get($sId);
            if ($st === null) {
                continue;
            }
            $topAbsent[] = [
                'student_id' => $st->id,
                'nis' => $st->nis,
                'name' => $st->name,
                'classroom_name' => $st->classroom?->name ?? '-',
                'absent_count' => $count,
            ];
        }

        return [
            'top_late' => $topLate,
            'top_absent' => $topAbsent,
        ];
    }

    /**
     * Get chronological attendance history for a specific student within a date range.
     *
     * @return list<array<string, mixed>>
     */
    public function getStudentHistory(
        Student $student,
        string $dateFrom,
        string $dateTo,
    ): array {
        $records = Attendance::query()
            ->where('attendable_type', Student::class)
            ->where('attendable_id', $student->id)
            ->whereBetween('date', [$dateFrom, $dateTo])
            ->orderByDesc('date')
            ->get();

        return $records->map(static function (Attendance $att): array {
            $statusEnum = $att->status instanceof AttendanceStatusEnum
                ? $att->status
                : AttendanceStatusEnum::tryFrom((string) $att->status);

            $sourceEnum = $att->source instanceof AttendanceSourceEnum
                ? $att->source
                : AttendanceSourceEnum::tryFrom((string) $att->source);

            return [
                'id' => $att->id,
                'date' => (string) $att->date,
                'status' => $statusEnum?->value ?? (string) $att->status,
                'status_label' => $statusEnum?->label() ?? (string) $att->status,
                'code' => self::statusToCode($statusEnum?->value ?? (string) $att->status),
                'check_in_time' => $att->check_in_at?->setTimezone('Asia/Jakarta')->format('H:i'),
                'check_out_time' => $att->check_out_at?->setTimezone('Asia/Jakarta')->format('H:i'),
                'source_label' => $sourceEnum?->label() ?? (string) $att->source,
                'note' => $att->note,
            ];
        })->values()->all();
    }
}

