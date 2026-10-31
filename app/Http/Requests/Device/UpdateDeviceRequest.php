<?php

namespace App\Http\Requests\Device;

use App\Models\Device;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Device|null $device */
        $device = $this->route('device');

        return $device !== null && ($this->user()?->can('update', $device) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'location' => ['nullable', 'string', 'max:150'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
