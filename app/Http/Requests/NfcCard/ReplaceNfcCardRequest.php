<?php

namespace App\Http\Requests\NfcCard;

use App\Enums\NfcCardStatusEnum;
use App\Models\NfcCard;
use App\Support\NfcUidNormalizer;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReplaceNfcCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', NfcCard::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('new_uid') && is_string($this->input('new_uid'))) {
            $normalized = NfcUidNormalizer::tryNormalize($this->input('new_uid'));
            if ($normalized !== null) {
                $this->merge(['new_uid' => $normalized]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'student_id' => [
                'required',
                'integer',
                Rule::exists('students', 'id')->whereNull('deleted_at'),
            ],
            'new_uid' => [
                'required',
                'string',
                'max:32',
                static function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_string($value) || ! NfcUidNormalizer::isValid($value)) {
                        $fail('Format UID kartu NFC baru tidak valid.');
                    }
                },
                Rule::unique('nfc_cards', 'uid'),
            ],
            'previous_status' => [
                'required',
                'string',
                Rule::in([NfcCardStatusEnum::Inactive->value, NfcCardStatusEnum::Lost->value]),
            ],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
