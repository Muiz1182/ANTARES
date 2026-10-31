<?php

namespace Database\Factories;

use App\Enums\NfcCardStatusEnum;
use App\Models\NfcCard;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NfcCard>
 */
class NfcCardFactory extends Factory
{
    protected $model = NfcCard::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uid' => strtoupper(fake()->unique()->regexify('[0-9A-F]{8}')),
            'cardable_type' => Student::class,
            'cardable_id' => Student::factory(),
            'status' => NfcCardStatusEnum::Active,
            'issued_at' => now(),
            'deactivated_at' => null,
            'replaced_by_card_id' => null,
            'issued_by' => null,
            'notes' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'status' => NfcCardStatusEnum::Inactive,
            'deactivated_at' => now(),
        ]);
    }

    public function lost(): static
    {
        return $this->state(fn () => [
            'status' => NfcCardStatusEnum::Lost,
            'deactivated_at' => now(),
        ]);
    }
}
