<?php

namespace App\Http\Requests\AttendanceRule;

use App\Models\AttendanceRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateAttendanceRulesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', AttendanceRule::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $rules = $this->input('rules');
        if (! is_array($rules)) {
            return;
        }

        foreach ($rules as $index => $item) {
            if (! is_array($item)) {
                continue;
            }
            foreach (['check_in_start', 'late_after', 'absent_cutoff', 'check_out_start'] as $timeField) {
                if (isset($item[$timeField]) && is_string($item[$timeField])) {
                    $rules[$index][$timeField] = substr(trim($item[$timeField]), 0, 5);
                }
            }
        }

        $this->merge(['rules' => $rules]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'rules' => ['required', 'array', 'size:7'],
            'rules.*.day_of_week' => ['required', 'integer', 'between:1,7', 'distinct'],
            'rules.*.is_school_day' => ['required', 'boolean'],
            'rules.*.check_in_start' => ['required', 'date_format:H:i'],
            'rules.*.late_after' => ['required', 'date_format:H:i'],
            'rules.*.absent_cutoff' => ['required', 'date_format:H:i'],
            'rules.*.check_out_start' => ['required', 'date_format:H:i'],
            'rules.*.debounce_seconds' => ['required', 'integer', 'min:0', 'max:600'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $rules = $this->input('rules', []);
                if (! is_array($rules)) {
                    return;
                }

                foreach ($rules as $index => $item) {
                    if (! is_array($item)) {
                        continue;
                    }

                    $checkIn = (string) ($item['check_in_start'] ?? '');
                    $lateAfter = (string) ($item['late_after'] ?? '');
                    $absentCutoff = (string) ($item['absent_cutoff'] ?? '');
                    $checkOut = (string) ($item['check_out_start'] ?? '');

                    if ($checkIn === '' || $lateAfter === '' || $absentCutoff === '' || $checkOut === '') {
                        continue;
                    }

                    if ($checkIn > $lateAfter) {
                        $validator->errors()->add(
                            "rules.{$index}.late_after",
                            'Batas terlambat harus sama atau setelah jam mulai masuk.',
                        );
                    }

                    if ($lateAfter >= $absentCutoff) {
                        $validator->errors()->add(
                            "rules.{$index}.absent_cutoff",
                            'Batas alpa otomatis harus setelah batas terlambat.',
                        );
                    }

                    if ($absentCutoff >= $checkOut) {
                        $validator->errors()->add(
                            "rules.{$index}.check_out_start",
                            'Jam mulai pulang harus setelah batas alpa otomatis.',
                        );
                    }
                }
            },
        ];
    }
}
