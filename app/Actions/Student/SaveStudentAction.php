<?php

namespace App\Actions\Student;

use App\Models\Student;
use App\Services\Student\StudentPhotoService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class SaveStudentAction
{
    public function __construct(
        private readonly StudentPhotoService $photoService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, ?UploadedFile $photo = null, ?Student $student = null): Student
    {
        return DB::transaction(function () use ($data, $photo, $student): Student {
            $payload = [
                'nis' => trim((string) $data['nis']),
                'nisn' => isset($data['nisn']) && trim((string) $data['nisn']) !== '' ? trim((string) $data['nisn']) : null,
                'name' => trim((string) $data['name']),
                'gender' => (string) $data['gender'],
                'classroom_id' => $data['classroom_id'] ?? null,
                'parent_name' => isset($data['parent_name']) && trim((string) $data['parent_name']) !== '' ? trim((string) $data['parent_name']) : null,
                'parent_phone' => isset($data['parent_phone']) && trim((string) $data['parent_phone']) !== '' ? trim((string) $data['parent_phone']) : null,
                'is_active' => (bool) $data['is_active'],
            ];

            if ($photo !== null) {
                $payload['photo_path'] = $this->photoService->store($photo, $student?->photo_path);
            }

            if ($student) {
                $student->update($payload);

                return $student->refresh();
            }

            return Student::create($payload);
        });
    }
}
