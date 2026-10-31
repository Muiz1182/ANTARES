<?php

namespace Database\Seeders;

use App\Models\AttendanceRule;
use Illuminate\Database\Seeder;

class AttendanceRuleSeeder extends Seeder
{
    public function run(): void
    {
        for ($day = 1; $day <= 7; $day++) {
            AttendanceRule::updateOrCreate(
                ['day_of_week' => $day],
                [
                    'is_school_day' => $day <= 5,
                    'check_in_start' => '06:00:00',
                    'late_after' => '07:00:00',
                    'check_out_start' => '14:00:00',
                    'absent_cutoff' => '10:00:00',
                    'debounce_seconds' => 30,
                ]
            );
        }
    }
}
