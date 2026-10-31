<?php

namespace App\Http\Resources;

use App\Enums\AttendanceSourceEnum;
use App\Enums\AttendanceStatusEnum;
use App\Models\Attendance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Attendance
 */
class AttendanceResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $status = $this->status instanceof AttendanceStatusEnum
            ? $this->status
            : AttendanceStatusEnum::tryFrom((string) $this->status);

        $source = $this->source instanceof AttendanceSourceEnum
            ? $this->source
            : AttendanceSourceEnum::tryFrom((string) $this->source);

        return [
            'id' => $this->id,
            'date' => $this->date,
            'status' => $status?->value ?? (string) $this->status,
            'status_label' => $status?->label() ?? (string) $this->status,
            'check_in_at' => $this->check_in_at?->setTimezone('Asia/Jakarta')->toIso8601String(),
            'check_in_time' => $this->check_in_at?->setTimezone('Asia/Jakarta')->format('H:i'),
            'check_out_at' => $this->check_out_at?->setTimezone('Asia/Jakarta')->toIso8601String(),
            'check_out_time' => $this->check_out_at?->setTimezone('Asia/Jakarta')->format('H:i'),
            'source' => $source?->value ?? (string) $this->source,
            'source_label' => $source?->label() ?? (string) $this->source,
            'note' => $this->note,
            'attachment_url' => $this->attachmentUrl(),
        ];
    }
}
