<?php

namespace Database\Factories;

use App\Enums\ScanResultEnum;
use App\Models\AttendanceLog;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceLog>
 */
class AttendanceLogFactory extends Factory
{
    protected $model = AttendanceLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $uid = strtoupper(fake()->regexify('[0-9A-F]{8}'));

        return [
            'nfc_card_id' => null,
            'attendable_type' => Student::class,
            'attendable_id' => null,
            'uid_raw' => $uid,
            'uid_normalized' => $uid,
            'device_id' => null,
            'scanned_at' => now('Asia/Jakarta'),
            'result' => ScanResultEnum::SuccessIn,
            'idempotency_key' => null,
            'response_payload' => null,
        ];
    }
}

