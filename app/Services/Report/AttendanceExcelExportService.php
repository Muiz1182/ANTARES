<?php

namespace App\Services\Report;

use App\Models\Classroom;
use App\Models\SchoolSetting;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;

class AttendanceExcelExportService
{
    public function __construct(
        private readonly AttendanceReportService $reportService,
    ) {}

    /**
     * Generate a temporary .xlsx file containing the monthly class matrix report.
     */
    public function generateMonthlyClassXlsx(Classroom $classroom, string $month): string
    {
        $matrix = $this->reportService->getMonthlyClassMatrix($classroom, $month);
        $setting = SchoolSetting::current();

        $tempPath = sys_get_temp_dir()
            .DIRECTORY_SEPARATOR
            .'rekap_absensi_'
            .Str::slug($classroom->name)
            .'_'
            .$matrix['month']
            .'_'
            .Str::uuid()->toString()
            .'.xlsx';

        $writer = new XlsxWriter;
        $writer->openToFile($tempPath);

        $writer->addRow(Row::fromValues([$setting->school_name]));
        $writer->addRow(Row::fromValues([
            'REKAPITULASI KEHADIRAN BULANAN KELAS '.$matrix['classroom']['name'],
        ]));
        $writer->addRow(Row::fromValues([
            'Periode: '.$matrix['month_label'],
            'Wali Kelas: '.($matrix['classroom']['homeroom_teacher_name'] ?? '-'),
            'Zona Waktu: '.$setting->timezone,
        ]));
        $writer->addRow(Row::fromValues([]));

        $headerRow = ['No', 'NIS', 'Nama Siswa', 'L/P'];
        foreach ($matrix['days'] as $day) {
            $headerRow[] = (string) $day['day_number'];
        }
        array_push($headerRow, 'H', 'T', 'I', 'S', 'A', 'D', '% Hadir');
        $writer->addRow(Row::fromValues($headerRow));

        foreach ($matrix['rows'] as $index => $row) {
            $values = [
                $index + 1,
                $row['nis'],
                $row['name'],
                $row['gender'],
            ];

            foreach ($matrix['days'] as $day) {
                $dStr = $day['date'];
                $values[] = $row['cells'][$dStr] ?? '-';
            }

            $values[] = $row['totals']['H'];
            $values[] = $row['totals']['T'];
            $values[] = $row['totals']['I'];
            $values[] = $row['totals']['S'];
            $values[] = $row['totals']['A'];
            $values[] = $row['totals']['D'];
            $values[] = $row['totals']['attendance_rate'].'%';

            $writer->addRow(Row::fromValues($values));
        }

        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues([
            'Ringkasan Kelas',
            'Hadir (H): '.$matrix['class_totals']['H'],
            'Terlambat (T): '.$matrix['class_totals']['T'],
            'Izin (I): '.$matrix['class_totals']['I'],
            'Sakit (S): '.$matrix['class_totals']['S'],
            'Alpa (A): '.$matrix['class_totals']['A'],
            'Dispensasi (D): '.$matrix['class_totals']['D'],
        ]));

        $writer->close();

        return $tempPath;
    }
}

