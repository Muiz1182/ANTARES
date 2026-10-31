<?php

namespace App\Actions\AttendanceRule;

use App\Models\AttendanceRule;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Support\Facades\DB;

class UpdateAttendanceRulesAction
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $rulesData
     */
    public function execute(array $rulesData, ?User $actor = null): void
    {
        DB::transaction(function () use ($rulesData, $actor): void {
            foreach ($rulesData as $item) {
                $dayOfWeek = (int) $item['day_of_week'];

                $existing = AttendanceRule::query()
                    ->where('day_of_week', $dayOfWeek)
                    ->first();

                $before = $existing !== null ? [
                    'day_of_week' => $existing->day_of_week,
                    'is_school_day' => (bool) $existing->is_school_day,
                    'check_in_start' => substr((string) $existing->check_in_start, 0, 5),
                    'late_after' => substr((string) $existing->late_after, 0, 5),
                    'absent_cutoff' => substr((string) $existing->absent_cutoff, 0, 5),
                    'check_out_start' => substr((string) $existing->check_out_start, 0, 5),
                    'debounce_seconds' => (int) $existing->debounce_seconds,
                ] : null;

                $updated = AttendanceRule::query()->updateOrCreate(
                    ['day_of_week' => $dayOfWeek],
                    [
                        'is_school_day' => (bool) $item['is_school_day'],
                        'check_in_start' => ((string) $item['check_in_start']).':00',
                        'late_after' => ((string) $item['late_after']).':00',
                        'absent_cutoff' => ((string) $item['absent_cutoff']).':00',
                        'check_out_start' => ((string) $item['check_out_start']).':00',
                        'debounce_seconds' => (int) $item['debounce_seconds'],
                    ]
                );

                $after = [
                    'day_of_week' => $updated->day_of_week,
                    'is_school_day' => (bool) $updated->is_school_day,
                    'check_in_start' => substr((string) $updated->check_in_start, 0, 5),
                    'late_after' => substr((string) $updated->late_after, 0, 5),
                    'absent_cutoff' => substr((string) $updated->absent_cutoff, 0, 5),
                    'check_out_start' => substr((string) $updated->check_out_start, 0, 5),
                    'debounce_seconds' => (int) $updated->debounce_seconds,
                ];

                if ($before !== $after) {
                    $this->auditLogService->record(
                        action: 'attendance_rule.updated',
                        subject: $updated,
                        before: $before,
                        after: $after,
                        reason: 'Pembaruan aturan jam absensi hari ke-'.$dayOfWeek,
                        actor: $actor,
                    );
                }
            }
        });
    }
}
