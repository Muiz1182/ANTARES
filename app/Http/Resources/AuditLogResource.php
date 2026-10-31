<?php

namespace App\Http\Resources;

use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AuditLog
 */
class AuditLogResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var AuditLog $log */
        $log = $this->resource;

        $actionKey = 'ui.audit_actions.'.$log->action;
        $actionLabel = __($actionKey);
        if ($actionLabel === $actionKey) {
            $actionLabel = $log->action;
        }

        $subjectSummary = class_basename($log->subject_type).' #'.$log->subject_id;
        if ($log->relationLoaded('subject') && $log->subject instanceof Attendance) {
            $attendance = $log->subject;
            $studentName = $attendance->relationLoaded('attendable') && $attendance->attendable instanceof Student
                ? $attendance->attendable->name.' ('.$attendance->attendable->nis.')'
                : 'Siswa #'.$attendance->attendable_id;
            $subjectSummary = $studentName.' - '.$attendance->date;
        }

        return [
            'id' => $log->id,
            'action' => $log->action,
            'action_label' => $actionLabel,
            'subject_type' => class_basename($log->subject_type),
            'subject_id' => $log->subject_id,
            'subject_summary' => $subjectSummary,
            'reason' => $log->reason,
            'before' => $log->before,
            'after' => $log->after,
            'created_at' => $log->created_at?->setTimezone('Asia/Jakarta')->format('Y-m-d H:i:s'),
            'actor' => $log->relationLoaded('user') && $log->user
                ? [
                    'id' => $log->user->id,
                    'name' => $log->user->name,
                    'email' => $log->user->email,
                ]
                : null,
        ];
    }
}
