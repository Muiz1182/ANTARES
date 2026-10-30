<?php

namespace Database\Factories;

use App\Models\AttendanceRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceRule>
 */
class AttendanceRuleFactory extends Factory
{
    protected $model = AttendanceRule::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'day_of_week' => fake()->unique()->numberBetween(1, 7),
            'is_school_day' => true,
            'check_in_start' => '06:00:00',
            'late_after' => '07:00:00',
            'check_out_start' => '14:00:00',
            'absent_cutoff' => '10:00:00',
            'debounce_seconds' => 30,
        ];
    }
}

