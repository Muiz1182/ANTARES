<?php

namespace App\Actions\Device;

use App\Models\Device;
use Illuminate\Support\Facades\DB;

class UpdateDeviceAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(Device $device, array $data): Device
    {
        return DB::transaction(function () use ($device, $data): Device {
            $device->update([
                'name' => (string) $data['name'],
                'location' => isset($data['location']) && $data['location'] !== '' ? (string) $data['location'] : null,
                'is_active' => (bool) $data['is_active'],
            ]);

            return $device->fresh();
        });
    }
}

