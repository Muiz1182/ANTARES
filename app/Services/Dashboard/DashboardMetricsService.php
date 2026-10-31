<?php

namespace App\Services\Dashboard;

use App\Enums\AttendanceStatusEnum;
use App\Enums\ScanResultEnum;
use App\Models\Attendance;
use App\Models\AttendanceLog;
use App\Models\Classroom;
use App\Models\Student;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;

class DashboardMetricsService
{
    /**
     * Build complete dashboard metrics and live state for the given user and date.
     *
     * @return array<string, mixed>
     */
    public function getDashboardData(
        User $user,
        ?string $dateString = null,
        ?int $filterClassroomId = null,
        bool $includeTrend = true,
    ): array {
        $targetDate = $dateString !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateString) === 1
            ? CarbonImmutable::createFromFormat('Y-m-d', $dateString, 'Asia/Jakarta')->startOfDay()
            : CarbonImmutable::now('Asia/Jakarta')->startOfDay();

        $date = $targetDate->toDateString();

        $classrooms = Classroom::query()
            ->visibleTo($user)
            ->with('homeroomTeacher:id,name')
            ->orderBy('level')
            ->orderBy('name')
            ->get();

        $visibleClassroomIds = $classrooms->pluck('id')->all();

        $activeStudents = Student::query()
            ->visibleTo($user)
            ->where('is_active', true)
            ->when(
                $filterClassroomId !== null && $filterClassroomId > 0 && in_array($filterClassroomId, $visibleClassroomIds, true),
                static function ($q) use ($filterClassroomId): void {
                    $q->where('classroom_id', $filterClassroomId);
                }
            )
            ->with([
                'classroom:id,name,level',
                'attendances' => static function ($q) use ($date): void {
                    $q->where('date', $date);
                },
            ])
            ->orderBy('classroom_id')
            ->orderBy('name')
            ->get();

        $counts = [
            'total_active_students' => 0,
            'present' => 0,
            'late' => 0,
            'permission' => 0,
            'sick' => 0,
            'dispensation' => 0,
            'excused' => 0,
            'absent' => 0,
            'not_yet_arrived' => 0,
        ];

        $classMap = [];
        foreach ($classrooms as $classroom) {
            if ($filterClassroomId !== null && $filterClassroomId > 0 && $classroom->id !== $filterClassroomId) {
                continue;
            }
            $classMap[$classroom->id] = [
                'classroom_id' => $classroom->id,
                'classroom_name' => $classroom->name,
                'level' => $classroom->level,
                'homeroom_teacher_name' => $classroom->homeroomTeacher?->name,
                'total_students' => 0,
                'present' => 0,
                'late' => 0,
                'permission' => 0,
                'sick' => 0,
                'dispensation' => 0,
                'excused' => 0,
                'absent' => 0,
                'not_yet_arrived' => 0,
                'attendance_rate' => 0.0,
            ];
        }

        $notYetArrivedStudents = [];

        foreach ($activeStudents as $student) {
            $counts['total_active_students']++;
            $cId = $student->classroom_id;

            if ($cId !== null && isset($classMap[$cId])) {
                $classMap[$cId]['total_students']++;
            }

            /** @var Attendance|null $att */
            $att = $student->attendances->first();

            if ($att === null) {
                $counts['not_yet_arrived']++;
                if ($cId !== null && isset($classMap[$cId])) {
                    $classMap[$cId]['not_yet_arrived']++;
                }

                if (count($notYetArrivedStudents) < 100) {
                    $notYetArrivedStudents[] = [
                        'id' => $student->id,
                        'nis' => $student->nis,
                        'name' => $student->name,
                        'classroom_id' => $student->classroom_id,
                        'classroom_name' => $student->classroom?->name ?? '-',
                    ];
                }

                continue;
            }

            $statusVal = $att->status instanceof AttendanceStatusEnum
                ? $att->status->value
                : (string) $att->status;

            switch ($statusVal) {
                case AttendanceStatusEnum::Present->value:
                    $counts['present']++;
                    if ($cId !== null && isset($classMap[$cId])) {
                        $classMap[$cId]['present']++;
                    }
                    break;
                case AttendanceStatusEnum::Late->value:
                    $counts['late']++;
                    if ($cId !== null && isset($classMap[$cId])) {
                        $classMap[$cId]['late']++;
                    }
                    break;
                case AttendanceStatusEnum::Permission->value:
                    $counts['permission']++;
                    $counts['excused']++;
                    if ($cId !== null && isset($classMap[$cId])) {
                        $classMap[$cId]['permission']++;
                        $classMap[$cId]['excused']++;
                    }
                    break;
                case AttendanceStatusEnum::Sick->value:
                    $counts['sick']++;
                    $counts['excused']++;
                    if ($cId !== null && isset($classMap[$cId])) {
                        $classMap[$cId]['sick']++;
                        $classMap[$cId]['excused']++;
                    }
                    break;
                case AttendanceStatusEnum::Dispensation->value:
                    $counts['dispensation']++;
                    $counts['excused']++;
                    if ($cId !== null && isset($classMap[$cId])) {
                        $classMap[$cId]['dispensation']++;
                        $classMap[$cId]['excused']++;
                    }
                    break;
                case AttendanceStatusEnum::Absent->value:
                    $counts['absent']++;
                    if ($cId !== null && isset($classMap[$cId])) {
                        $classMap[$cId]['absent']++;
                    }
                    break;
                default:
                    $counts['not_yet_arrived']++;
                    if ($cId !== null && isset($classMap[$cId])) {
                        $classMap[$cId]['not_yet_arrived']++;
                    }
                    break;
            }
        }

        foreach ($classMap as $cId => $row) {
            if ($row['total_students'] > 0) {
                $attended = $row['present'] + $row['late'] + $row['dispensation'];
                $classMap[$cId]['attendance_rate'] = round(($attended / $row['total_students']) * 100, 1);
            }
        }

        $recentTaps = $this->getRecentTaps($user, $date, $visibleClassroomIds, $filterClassroomId);

        $trend30Days = $includeTrend
            ? $this->getTrend30Days($user, $targetDate, $visibleClassroomIds, $filterClassroomId)
            : [];

        return [
            'date' => $date,
            'generated_at' => CarbonImmutable::now('Asia/Jakarta')->format('H:i:s'),
            'counts' => $counts,
            'class_breakdown' => array_values($classMap),
            'not_yet_arrived_students' => $notYetArrivedStudents,
            'recent_taps' => $recentTaps,
            'trend_30_days' => $trend30Days,
        ];
    }

    /**
     * @param  list<int>  $visibleClassroomIds
     * @return list<array<string, mixed>>
     */
    private function getRecentTaps(
        User $user,
        string $date,
        array $visibleClassroomIds,
        ?int $filterClassroomId = null,
    ): array {
        $startOfDay = CarbonImmutable::createFromFormat('Y-m-d', $date, 'Asia/Jakarta')->startOfDay();
        $endOfDay = $startOfDay->endOfDay();

        $logs = AttendanceLog::query()
            ->with(['device:id,name', 'nfcCard.cardable'])
            ->whereBetween('scanned_at', [$startOfDay, $endOfDay])
            ->orderByDesc('scanned_at')
            ->orderByDesc('id')
            ->limit(30)
            ->get();

        $items = [];
        foreach ($logs as $log) {
            $cardable = $log->nfcCard?->cardable;
            $student = $cardable instanceof Student ? $cardable : null;

            if ($student !== null) {
                if (! in_array($student->classroom_id, $visibleClassroomIds, true)) {
                    continue;
                }
                if ($filterClassroomId !== null && $filterClassroomId > 0 && $student->classroom_id !== $filterClassroomId) {
                    continue;
                }
                $student->loadMissing('classroom:id,name');
            } elseif (! $user->can('viewAny', Classroom::class)) {
                // Scoped homeroom teachers only see taps from their own students
                continue;
            }

            $resultEnum = $log->result instanceof ScanResultEnum
                ? $log->result
                : ScanResultEnum::tryFrom((string) $log->result);

            $items[] = [
                'id' => $log->id,
                'uid_raw' => $log->uid_raw,
                'uid_normalized' => $log->uid_normalized,
                'result' => $resultEnum?->value ?? (string) $log->result,
                'message' => $resultEnum?->message() ?? (string) $log->result,
                'sound_tone' => $resultEnum?->soundTone() ?? 'error',
                'ok' => $resultEnum?->isOk() ?? false,
                'scanned_at' => $log->scanned_at?->setTimezone('Asia/Jakarta')->toIso8601String(),
                'scanned_time' => $log->scanned_at?->setTimezone('Asia/Jakarta')->format('H:i:s'),
                'device_name' => $log->device?->name ?? 'Pos NFC',
                'student' => $student !== null
                    ? [
                        'id' => $student->id,
                        'nis' => $student->nis,
                        'name' => $student->name,
                        'classroom' => $student->classroom?->name,
                    ]
                    : null,
            ];

            if (count($items) >= 15) {
                break;
            }
        }

        return $items;
    }

    /**
     * @param  list<int>  $visibleClassroomIds
     * @return list<array<string, mixed>>
     */
    private function getTrend30Days(
        User $user,
        CarbonImmutable $endDate,
        array $visibleClassroomIds,
        ?int $filterClassroomId = null,
    ): array {
        $startDate = $endDate->subDays(29)->startOfDay();

        $visibleStudentIds = Student::query()
            ->visibleTo($user)
            ->where('is_active', true)
            ->when(
                $filterClassroomId !== null && $filterClassroomId > 0 && in_array($filterClassroomId, $visibleClassroomIds, true),
                static function ($q) use ($filterClassroomId): void {
                    $q->where('classroom_id', $filterClassroomId);
                }
            )
            ->pluck('id')
            ->all();

        $attendances = count($visibleStudentIds) > 0
            ? Attendance::query()
                ->where('attendable_type', Student::class)
                ->whereIn('attendable_id', $visibleStudentIds)
                ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
                ->get(['date', 'status'])
            : collect();

        $dailyBuckets = [];
        $period = CarbonPeriod::create($startDate, '1 day', $endDate);
        foreach ($period as $day) {
            $dObj = CarbonImmutable::instance($day)->setTimezone('Asia/Jakarta');
            $dStr = $dObj->toDateString();
            $dailyBuckets[$dStr] = [
                'date' => $dStr,
                'label' => $dObj->format('d/m'),
                'present' => 0,
                'late' => 0,
                'excused' => 0,
                'absent' => 0,
            ];
        }

        foreach ($attendances as $att) {
            $dStr = (string) $att->date;
            if (! isset($dailyBuckets[$dStr])) {
                continue;
            }

            $statusVal = $att->status instanceof AttendanceStatusEnum
                ? $att->status->value
                : (string) $att->status;

            match ($statusVal) {
                AttendanceStatusEnum::Present->value => $dailyBuckets[$dStr]['present']++,
                AttendanceStatusEnum::Late->value => $dailyBuckets[$dStr]['late']++,
                AttendanceStatusEnum::Permission->value,
                AttendanceStatusEnum::Sick->value,
                AttendanceStatusEnum::Dispensation->value => $dailyBuckets[$dStr]['excused']++,
                AttendanceStatusEnum::Absent->value => $dailyBuckets[$dStr]['absent']++,
                default => null,
            };
        }

        return array_values($dailyBuckets);
    }
}
