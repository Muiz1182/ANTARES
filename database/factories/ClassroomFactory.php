<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\Classroom;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Classroom>
 */
class ClassroomFactory extends Factory
{
    protected $model = Classroom::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $level = fake()->randomElement([7, 8, 9]);
        $section = fake()->unique()->lexify('??');

        return [
            'academic_year_id' => AcademicYear::factory(),
            'name' => $level.strtoupper($section),
            'level' => $level,
            'homeroom_teacher_id' => null,
        ];
    }
}
