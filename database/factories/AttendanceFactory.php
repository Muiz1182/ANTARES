<?php

namespace Database\Factories;

use App\Enums\AttendanceSourceEnum;
use App\Enums\AttendanceStatusEnum;
use App\Models\Attendance;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attendance>
 */
class AttendanceFactory extends Factory
{
    protected $model = Attendance::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'attendable_type' => Student::class,
            'attendable_id' => Student::factory(),
            'date' => now('Asia/Jakarta')->toDateString(),
            'check_in_at' => now('Asia/Jakarta')->setTime(6, 45, 0),
            'check_out_at' => null,
            'status' => AttendanceStatusEnum::Present,
            'source' => AttendanceSourceEnum::Nfc,
            'device_id' => null,
            'note' => null,
            'created_by' => null,
        ];
    }
}
