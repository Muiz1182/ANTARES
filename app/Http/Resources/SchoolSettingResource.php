<?php

namespace App\Http\Resources;

use App\Models\SchoolSetting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SchoolSetting
 */
class SchoolSettingResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'school_name' => $this->school_name,
            'school_address' => $this->school_address,
            'principal_name' => $this->principal_name,
            'principal_nip' => $this->principal_nip,
            'logo_url' => $this->logoUrl(),
            'timezone' => $this->timezone ?: 'Asia/Jakarta',
        ];
    }
}

