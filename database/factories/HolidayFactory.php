<?php

namespace Database\Factories;

use App\Models\Holiday;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Holiday>
 */
class HolidayFactory extends Factory
{
    protected $model = Holiday::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'date' => fake()->unique()->dateTimeBetween('2026-01-01', '2027-12-31')->format('Y-m-d'),
            'name' => 'Hari Libur '.fake()->words(2, true),
            'description' => fake()->sentence(),
        ];
    }
}
