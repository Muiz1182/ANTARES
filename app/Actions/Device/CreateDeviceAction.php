<?php

namespace App\Actions\Device;

use App\Models\Device;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateDeviceAction
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{device: Device, plain_token: string}
     */
    public function execute(array $data): array
    {
        return DB::transaction(function () use ($data): array {
            $plainToken = 'antares_pos_'.Str::random(36);

            $device = Device::query()->create([
                'name' => (string) $data['name'],
                'location' => isset($data['location']) && $data['location'] !== '' ? (string) $data['location'] : null,
                'token_hash' => Device::hashToken($plainToken),
                'is_active' => true,
            ]);

            return [
                'device' => $device,
                'plain_token' => $plainToken,
            ];
        });
    }
}

