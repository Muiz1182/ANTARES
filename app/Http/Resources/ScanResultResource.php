<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScanResultResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $data */
        $data = is_array($this->resource) ? $this->resource : (array) $this->resource;

        return [
            'ok' => (bool) ($data['ok'] ?? false),
            'result' => (string) ($data['result'] ?? ''),
            'message' => (string) ($data['message'] ?? ''),
            'sound_tone' => (string) ($data['sound_tone'] ?? 'error'),
            'scanned_at' => $data['scanned_at'] ?? null,
            'uid_normalized' => $data['uid_normalized'] ?? null,
            'student' => $data['student'] ?? null,
            'attendance' => $data['attendance'] ?? null,
        ];
    }
}
