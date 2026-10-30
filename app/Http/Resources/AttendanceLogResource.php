<?php

namespace App\Http\Resources;

use App\Models\AttendanceLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AttendanceLog
 */
class AttendanceLogResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $payload = is_array($this->response_payload) ? $this->response_payload : [];

        return [
            'id' => $this->id,
            'uid_raw' => $this->uid_raw,
            'uid_normalized' => $this->uid_normalized,
            'result' => $this->result?->value,
            'message' => $this->result?->message(),
            'sound_tone' => $this->result?->soundTone(),
            'ok' => $this->result?->isOk() ?? false,
            'scanned_at' => $this->scanned_at?->setTimezone('Asia/Jakarta')->toIso8601String(),
            'scanned_time_formatted' => $this->scanned_at?->setTimezone('Asia/Jakarta')->format('H:i:s'),
            'student' => $payload['student'] ?? null,
            'attendance' => $payload['attendance'] ?? null,
        ];
    }
}

