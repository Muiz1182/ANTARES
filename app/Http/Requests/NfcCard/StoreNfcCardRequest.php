<?php

namespace App\Http\Requests\NfcCard;

use App\Models\NfcCard;
use App\Support\NfcUidNormalizer;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNfcCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', NfcCard::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('uid') && is_string($this->input('uid'))) {
            $normalized = NfcUidNormalizer::tryNormalize($this->input('uid'));
            if ($normalized !== null) {
                $this->merge(['uid' => $normalized]);
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
            'uid' => [
                'required',
                'string',
                'max:32',
                static function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_string($value) || ! NfcUidNormalizer::isValid($value)) {
                        $fail('Format UID kartu NFC tidak valid.');
                    }
                },
                Rule::unique('nfc_cards', 'uid'),
            ],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
