<?php

namespace App\Actions\Device;

use App\Models\Device;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RegenerateDeviceTokenAction
{
    /**
     * @return array{device: Device, plain_token: string}
     */
    public function execute(Device $device): array
    {
        return DB::transaction(function () use ($device): array {
            $plainToken = 'antares_pos_'.Str::random(36);

            $device->update([
                'token_hash' => Device::hashToken($plainToken),
                'is_active' => true,
            ]);

            return [
                'device' => $device->fresh(),
                'plain_token' => $plainToken,
            ];
        });
    }
}
