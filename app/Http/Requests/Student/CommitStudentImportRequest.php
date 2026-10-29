<?php

namespace App\Http\Requests\Student;

use App\Models\Student;
use Illuminate\Foundation\Http\FormRequest;

class CommitStudentImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('import', Student::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'import_token' => ['required', 'string', 'uuid'],
        ];
    }
}
