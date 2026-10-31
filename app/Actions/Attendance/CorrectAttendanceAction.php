<?php

namespace App\Actions\Attendance;

use App\Enums\AttendanceSourceEnum;
use App\Enums\AttendanceStatusEnum;
use App\Models\Attendance;
use App\Models\Student;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class CorrectAttendanceAction
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, User $actor): Attendance
    {
        return DB::transaction(function () use ($data, $actor): Attendance {
            /** @var Student $student */
            $student = Student::query()->findOrFail((int) $data['student_id']);
            $dateString = (string) $data['date'];
            $status = AttendanceStatusEnum::from((string) $data['status']);

            $checkInAt = isset($data['check_in_time']) && trim((string) $data['check_in_time']) !== ''
                ? CarbonImmutable::createFromFormat('Y-m-d H:i', $dateString.' '.trim((string) $data['check_in_time']), 'Asia/Jakarta')
                : null;

            $checkOutAt = isset($data['check_out_time']) && trim((string) $data['check_out_time']) !== ''
                ? CarbonImmutable::createFromFormat('Y-m-d H:i', $dateString.' '.trim((string) $data['check_out_time']), 'Asia/Jakarta')
                : null;

            $note = isset($data['note']) && trim((string) $data['note']) !== ''
                ? trim((string) $data['note'])
                : null;

            $reason = trim((string) $data['reason']);

            /** @var Attendance|null $existing */
            $existing = Attendance::query()
                ->where('attendable_type', Student::class)
                ->where('attendable_id', $student->id)
                ->where('date', $dateString)
                ->lockForUpdate()
                ->first();

            $before = $existing !== null ? $this->snapshot($existing) : null;

            if ($existing !== null) {
                $existing->update([
                    'status' => $status,
                    'check_in_at' => $checkInAt,
                    'check_out_at' => $checkOutAt,
                    'source' => AttendanceSourceEnum::Manual,
                    'note' => $note,
                    'created_by' => $actor->id,
                ]);
                $attendance = $existing->fresh();
            } else {
                $attendance = Attendance::query()->create([
                    'attendable_type' => Student::class,
                    'attendable_id' => $student->id,
                    'date' => $dateString,
                    'status' => $status,
                    'check_in_at' => $checkInAt,
                    'check_out_at' => $checkOutAt,
                    'source' => AttendanceSourceEnum::Manual,
                    'note' => $note,
                    'created_by' => $actor->id,
                ]);
            }

            $this->auditLogService->record(
                action: 'attendance.corrected',
                subject: $attendance,
                before: $before,
                after: $this->snapshot($attendance),
                reason: $reason,
                actor: $actor,
            );

            return $attendance;
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
