<?php

namespace Database\Factories;

use App\Enums\GenderEnum;
use App\Models\Classroom;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    protected $model = Student::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nis' => fake()->unique()->numerify('26####'),
            'nisn' => fake()->unique()->numerify('00########'),
            'name' => fake()->name(),
            'gender' => fake()->randomElement(GenderEnum::values()),
            'classroom_id' => Classroom::factory(),
            'photo_path' => null,
            'parent_name' => fake()->name(),
            'parent_phone' => fake()->numerify('08##########'),
            'is_active' => true,
        ];
    }
}
