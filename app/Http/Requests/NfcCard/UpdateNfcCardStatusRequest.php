<?php

namespace App\Http\Requests\NfcCard;

use App\Enums\NfcCardStatusEnum;
use App\Models\NfcCard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateNfcCardStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var NfcCard|null $card */
        $card = $this->route('nfc_card');

        return $card !== null && ($this->user()?->can('update', $card) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => [
                'required',
                'string',
                Rule::in(NfcCardStatusEnum::values()),
            ],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
