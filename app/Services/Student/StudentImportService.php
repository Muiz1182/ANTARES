<?php

namespace App\Services\Student;

use App\Enums\GenderEnum;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Student;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;

class StudentImportService
{
    /**
     * @var list<string>
     */
    public const HEADERS = [
        'nis',
        'nisn',
        'name',
        'gender',
        'classroom',
        'parent_name',
        'parent_phone',
    ];

    public function generateTemplateFile(string $format = 'xlsx'): string
    {
        $extension = strtolower($format) === 'csv' ? 'csv' : 'xlsx';
        $tempPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'template_impor_siswa_'.Str::uuid()->toString().'.'.$extension;

        $writer = $extension === 'csv' ? new CsvWriter : new XlsxWriter;
        $writer->openToFile($tempPath);
        $writer->addRow(Row::fromValues(self::HEADERS));
        $writer->addRow(Row::fromValues([
            '260001',
            '0012345678',
            'Ahmad Fauzi',
            'L',
            '7A',
            'Hendra Fauzi',
            '081234567890',
        ]));
        $writer->close();

        return $tempPath;
    }

    /**
     * @return array{
     *     import_token: string,
     *     total_rows: int,
     *     valid_count: int,
     *     error_count: int,
     *     valid_rows: list<array<string, mixed>>,
     *     errors: list<array{row: int, nis: string, name: string, messages: list<string>}>
     * }
     */
    public function preview(UploadedFile $file, int $userId): array
    {
        $rows = $this->readRows($file);

        $activeYear = AcademicYear::query()->active()->first();
        $classroomsQuery = Classroom::query();
        if ($activeYear) {
            $classroomsQuery->where('academic_year_id', $activeYear->id);
        }

        /** @var array<string, int> $classroomMap */
        $classroomMap = [];
        foreach ($classroomsQuery->get(['id', 'name']) as $classroom) {
            $classroomMap[strtoupper(trim($classroom->name))] = $classroom->id;
        }

        /** @var array<string, bool> $existingNis */
        $existingNis = array_fill_keys(
            Student::withTrashed()->pluck('nis')->map(fn ($v) => trim((string) $v))->all(),
            true
        );

        /** @var array<string, bool> $existingNisn */
        $existingNisn = array_fill_keys(
            Student::withTrashed()->whereNotNull('nisn')->pluck('nisn')->map(fn ($v) => trim((string) $v))->all(),
            true
        );

        $seenNisInFile = [];
        $seenNisnInFile = [];
        $validRows = [];
        $errors = [];
        $totalDataRows = 0;

        foreach ($rows as $index => $cells) {
            $rowNumber = $index + 1;

            if ($rowNumber === 1 && $this->isHeaderRow($cells)) {
                continue;
            }

            if ($this->isEmptyRow($cells)) {
                continue;
            }

            $totalDataRows++;

            $nis = trim((string) ($cells[0] ?? ''));
            $nisn = trim((string) ($cells[1] ?? ''));
            $name = trim((string) ($cells[2] ?? ''));
            $gender = strtoupper(trim((string) ($cells[3] ?? '')));
            $classroomName = trim((string) ($cells[4] ?? ''));
            $parentName = trim((string) ($cells[5] ?? ''));
            $parentPhone = trim((string) ($cells[6] ?? ''));

            $rowMessages = [];

            if ($nis === '') {
                $rowMessages[] = 'NIS wajib diisi.';
            } elseif (strlen($nis) > 32) {
                $rowMessages[] = 'NIS maksimal 32 karakter.';
            } elseif (isset($existingNis[$nis])) {
                $rowMessages[] = "NIS {$nis} sudah terdaftar di database.";
            } elseif (isset($seenNisInFile[$nis])) {
                $rowMessages[] = "NIS {$nis} duplikat dengan baris {$seenNisInFile[$nis]} di dalam berkas.";
            } else {
                $seenNisInFile[$nis] = $rowNumber;
            }

            if ($nisn !== '') {
                if (strlen($nisn) > 32) {
                    $rowMessages[] = 'NISN maksimal 32 karakter.';
                } elseif (isset($existingNisn[$nisn])) {
                    $rowMessages[] = "NISN {$nisn} sudah terdaftar di database.";
                } elseif (isset($seenNisnInFile[$nisn])) {
                    $rowMessages[] = "NISN {$nisn} duplikat dengan baris {$seenNisnInFile[$nisn]} di dalam berkas.";
                } else {
                    $seenNisnInFile[$nisn] = $rowNumber;
                }
            }

            if ($name === '') {
                $rowMessages[] = 'Nama siswa wajib diisi.';
            }

            if (! in_array($gender, GenderEnum::values(), true)) {
                $rowMessages[] = 'Jenis kelamin harus L atau P.';
            }

            $classroomId = null;
            if ($classroomName === '') {
                $rowMessages[] = 'Nama kelas wajib diisi.';
            } else {
                $lookupKey = strtoupper($classroomName);
                if (! isset($classroomMap[$lookupKey])) {
                    $rowMessages[] = "Kelas {$classroomName} tidak ditemukan pada tahun ajaran aktif.";
                } else {
                    $classroomId = $classroomMap[$lookupKey];
                }
            }

            if ($rowMessages !== []) {
                $errors[] = [
                    'row' => $rowNumber,
                    'nis' => $nis,
                    'name' => $name,
                    'messages' => $rowMessages,
                ];

                continue;
            }

            $validRows[] = [
                'row' => $rowNumber,
                'nis' => $nis,
                'nisn' => $nisn !== '' ? $nisn : null,
                'name' => $name,
                'gender' => $gender,
                'classroom_id' => $classroomId,
                'classroom_name' => $classroomName,
                'parent_name' => $parentName !== '' ? $parentName : null,
                'parent_phone' => $parentPhone !== '' ? $parentPhone : null,
            ];
        }

        $importToken = Str::uuid()->toString();

        Cache::put(
            $this->cacheKey($userId, $importToken),
            $validRows,
            now()->addMinutes(30)
        );

        return [
            'import_token' => $importToken,
            'total_rows' => $totalDataRows,
            'valid_count' => count($validRows),
            'error_count' => count($errors),
            'valid_rows' => array_slice($validRows, 0, 100),
            'errors' => $errors,
        ];
    }

    public function commit(string $importToken, int $userId): int
    {
        $key = $this->cacheKey($userId, $importToken);

        /** @var list<array<string, mixed>>|null $validRows */
        $validRows = Cache::pull($key);

        if (! is_array($validRows) || $validRows === []) {
            throw new DomainException('Sesi pratinjau impor tidak ditemukan atau tidak memiliki baris valid.');
        }

        return DB::transaction(function () use ($validRows): int {
            $nisList = array_column($validRows, 'nis');
            $nisnList = array_values(array_filter(array_column($validRows, 'nisn')));

            $alreadyExists = Student::withTrashed()
                ->whereIn('nis', $nisList)
                ->when($nisnList !== [], fn ($q) => $q->orWhereIn('nisn', $nisnList))
                ->exists();

            if ($alreadyExists) {
                throw new DomainException('Terdapat NIS atau NISN yang sudah terdaftar. Silakan ulangi pratinjau impor.');
            }

            $now = now();
            $records = array_map(static fn (array $row): array => [
                'nis' => $row['nis'],
                'nisn' => $row['nisn'],
                'name' => $row['name'],
                'gender' => $row['gender'],
                'classroom_id' => $row['classroom_id'],
                'photo_path' => null,
                'parent_name' => $row['parent_name'],
                'parent_phone' => $row['parent_phone'],
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ], $validRows);

            foreach (array_chunk($records, 250) as $chunk) {
                Student::insert($chunk);
            }

            return count($records);
        });
    }

    /**
     * @return list<list<mixed>>
     */
    private function readRows(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $reader = $extension === 'xlsx' ? new XlsxReader : new CsvReader;

        $reader->open($file->getRealPath());

        $rows = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = $row->toArray();
            }
            break;
        }

        $reader->close();

        return $rows;
    }

    /**
     * @param  list<mixed>  $cells
     */
    private function isHeaderRow(array $cells): bool
    {
        $firstCell = strtolower(trim((string) ($cells[0] ?? '')));

        return $firstCell === 'nis';
    }

    /**
     * @param  list<mixed>  $cells
     */
    private function isEmptyRow(array $cells): bool
    {
        foreach ($cells as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    private function cacheKey(int $userId, string $importToken): string
    {
        return "student_import:{$userId}:{$importToken}";
    }
}
