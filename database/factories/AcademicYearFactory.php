<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AcademicYear>
 */
class AcademicYearFactory extends Factory
{
    protected $model = AcademicYear::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startYear = fake()->unique()->numberBetween(2020, 2035);

        return [
            'name' => sprintf('%d/%d', $startYear, $startYear + 1),
            'start_date' => sprintf('%d-07-15', $startYear),
            'end_date' => sprintf('%d-06-20', $startYear + 1),
            'is_active' => false,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'is_active' => true,
        ]);
    }
}
