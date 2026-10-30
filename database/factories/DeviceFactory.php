<?php

namespace Database\Factories;

use App\Models\Device;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Device>
 */
class DeviceFactory extends Factory
{
    protected $model = Device::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Pos Absensi '.fake()->numberBetween(1, 20),
            'location' => 'Gerbang Utama',
            'token_hash' => Device::hashToken(Str::random(40)),
            'is_active' => true,
            'last_seen_at' => null,
        ];
    }

    public function withPlainToken(string $plainToken): static
    {
        return $this->state(fn () => [
            'token_hash' => Device::hashToken($plainToken),
        ]);
    }
}

