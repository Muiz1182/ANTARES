<?php

namespace App\Actions\Attendance;

use App\Enums\AttendanceSourceEnum;
use App\Enums\AttendanceStatusEnum;
use App\Models\Attendance;
use App\Models\AttendanceRule;
use App\Models\Holiday;
use App\Models\Student;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class StoreExcusedAttendanceAction
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, User $actor, ?UploadedFile $attachment = null): int
    {
        return DB::transaction(function () use ($data, $actor, $attachment): int {
            /** @var Student $student */
            $student = Student::query()->findOrFail((int) $data['student_id']);
            $status = AttendanceStatusEnum::from((string) $data['status']);

            $startDate = CarbonImmutable::createFromFormat('Y-m-d', (string) $data['start_date'], 'Asia/Jakarta')->startOfDay();
            $endDateString = isset($data['end_date']) && $data['end_date'] !== ''
                ? (string) $data['end_date']
                : (string) $data['start_date'];
            $endDate = CarbonImmutable::createFromFormat('Y-m-d', $endDateString, 'Asia/Jakarta')->startOfDay();

            $attachmentPath = $attachment?->store('attendance-attachments', 'public');
            $note = isset($data['note']) && trim((string) $data['note']) !== ''
                ? trim((string) $data['note'])
                : null;

            $isMultiDay = ! $startDate->equalTo($endDate);
            $rulesByDay = AttendanceRule::query()->get()->keyBy('day_of_week');
            $holidayDates = Holiday::query()
                ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
                ->pluck('date')
                ->map(fn ($d): string => $d instanceof \DateTimeInterface ? $d->format('Y-m-d') : (string) $d)
                ->flip();

            $period = CarbonPeriod::create($startDate, '1 day', $endDate);
            $processedCount = 0;

            foreach ($period as $currentDate) {
                $dateObj = CarbonImmutable::instance($currentDate)->setTimezone('Asia/Jakarta');
                $dateString = $dateObj->toDateString();

                if ($isMultiDay) {
                    $rule = $rulesByDay->get($dateObj->isoWeekday());
                    $isSchoolDay = $rule !== null ? $rule->is_school_day : ($dateObj->isoWeekday() <= 5);

                    if (! $isSchoolDay || $holidayDates->has($dateString)) {
                        continue;
                    }
                }

                /** @var Attendance|null $existing */
                $existing = Attendance::query()
                    ->where('attendable_type', Student::class)
                    ->where('attendable_id', $student->id)
                    ->where('date', $dateString)
                    ->lockForUpdate()
                    ->first();

                $before = $existing !== null ? $this->snapshot($existing) : null;

                $payload = [
                    'status' => $status,
                    'check_in_at' => null,
                    'check_out_at' => null,
                    'check_in_device_id' => null,
                    'check_out_device_id' => null,
                    'source' => AttendanceSourceEnum::Manual,
                    'note' => $note,
                    'created_by' => $actor->id,
                ];

                if ($attachmentPath !== null) {
                    $payload['attachment_path'] = $attachmentPath;
                } elseif ($existing === null) {
                    $payload['attachment_path'] = null;
                }

                if ($existing !== null) {
                    $existing->update($payload);
                    $attendance = $existing->fresh();
                } else {
                    $attendance = Attendance::query()->create([
                        'attendable_type' => Student::class,
                        'attendable_id' => $student->id,
                        'date' => $dateString,
                        ...$payload,
                    ]);
                }

                $this->auditLogService->record(
                    action: 'attendance.excused',
                    subject: $attendance,
                    before: $before,
                    after: $this->snapshot($attendance),
                    reason: $note,
                    actor: $actor,
                );

                $processedCount++;
            }

            return $processedCount;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Attendance $attendance): array
    {
        return [
            'date' => $attendance->date,
            'status' => $attendance->status instanceof AttendanceStatusEnum
                ? $attendance->status->value
                : (string) $attendance->status,
            'check_in_at' => $attendance->check_in_at?->setTimezone('Asia/Jakarta')->format('H:i:s'),
            'check_out_at' => $attendance->check_out_at?->setTimezone('Asia/Jakarta')->format('H:i:s'),
            'source' => $attendance->source instanceof AttendanceSourceEnum
                ? $attendance->source->value
                : (string) $attendance->source,
            'note' => $attendance->note,
            'attachment_path' => $attendance->attachment_path,
        ];
    }
}
