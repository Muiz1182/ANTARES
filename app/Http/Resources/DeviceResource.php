<?php

namespace App\Http\Resources;

use App\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Device
 */
class DeviceResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'location' => $this->location,
            'is_active' => (bool) $this->is_active,
            'last_seen_at' => $this->last_seen_at?->setTimezone('Asia/Jakarta')->toIso8601String(),
            'last_seen_at_formatted' => $this->last_seen_at?->setTimezone('Asia/Jakarta')->format('d/m/Y H:i:s'),
        ];
    }
}
