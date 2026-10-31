<?php

namespace App\Services\Report;

use App\Models\Classroom;
use App\Models\SchoolSetting;
use Carbon\CarbonImmutable;

class AttendancePdfExportService
{
    public function __construct(
        private readonly AttendanceReportService $reportService,
    ) {}

    /**
     * Generate a valid PDF 1.4 binary string for the monthly class recap.
     */
    public function generateMonthlyClassPdfContent(Classroom $classroom, string $month): string
    {
        $matrix = $this->reportService->getMonthlyClassMatrix($classroom, $month);
        $setting = SchoolSetting::current();
        $printedAt = CarbonImmutable::now('Asia/Jakarta')->format('Y-m-d H:i').' WIB';

        $lines = [];
        $lines[] = strtoupper($setting->school_name);
        if ($setting->school_address) {
            $lines[] = $setting->school_address;
        }
        $lines[] = str_repeat('=', 96);
        $lines[] = 'REKAPITULASI KEHADIRAN BULANAN KELAS '.$matrix['classroom']['name'].' (TINGKAT '.$matrix['classroom']['level'].')';
        $lines[] = sprintf(
            'Periode: %-22s | Wali Kelas: %-28s | Dicetak: %s',
            $matrix['month_label'],
            $matrix['classroom']['homeroom_teacher_name'] ?? '-',
            $printedAt
        );
        $lines[] = str_repeat('-', 96);
        $lines[] = sprintf(
            '%-4s %-12s %-32s %-4s | %4s %4s %4s %4s %4s %4s | %7s',
            'No',
            'NIS',
            'Nama Siswa',
            'L/P',
            'H',
            'T',
            'I',
            'S',
            'A',
            'D',
            '% Hadir'
        );
        $lines[] = str_repeat('-', 96);

        foreach ($matrix['rows'] as $index => $row) {
            $lines[] = sprintf(
                '%-4d %-12s %-32s %-4s | %4d %4d %4d %4d %4d %4d | %6.1f%%',
                $index + 1,
                mb_substr((string) $row['nis'], 0, 12),
                mb_substr((string) $row['name'], 0, 32),
                (string) $row['gender'],
                $row['totals']['H'],
                $row['totals']['T'],
                $row['totals']['I'],
                $row['totals']['S'],
                $row['totals']['A'],
                $row['totals']['D'],
                $row['totals']['attendance_rate']
            );
        }

        $lines[] = str_repeat('-', 96);
        $lines[] = sprintf(
            'TOTAL KELAS %-43s | %4d %4d %4d %4d %4d %4d |',
            $matrix['classroom']['name'],
            $matrix['class_totals']['H'],
            $matrix['class_totals']['T'],
            $matrix['class_totals']['I'],
            $matrix['class_totals']['S'],
            $matrix['class_totals']['A'],
            $matrix['class_totals']['D']
        );
        $lines[] = str_repeat('=', 96);
        $lines[] = 'Keterangan Kode: H = Hadir, T = Terlambat, I = Izin, S = Sakit, A = Alpa, D = Dispensasi';
        $lines[] = '';
        $lines[] = sprintf(
            '%-48s %s',
            'Mengetahui, Kepala Sekolah',
            'Wali Kelas '.$matrix['classroom']['name']
        );
        $lines[] = '';
        $lines[] = '';
        $lines[] = sprintf(
            '%-48s %s',
            $setting->principal_name ?? '..............................',
            $matrix['classroom']['homeroom_teacher_name'] ?? '..............................'
        );
        $lines[] = sprintf(
            'NIP: %-43s NIP: %s',
            $setting->principal_nip ?? '-',
            $matrix['classroom']['homeroom_teacher_nip'] ?? '-'
        );

        return $this->buildPdfDocument($lines);
    }

    /**
     * Build a minimal, standards-compliant PDF 1.4 document using Courier font.
     *
     * @param  list<string>  $lines
     */
    private function buildPdfDocument(array $lines): string
    {
        $chunks = array_chunk($lines, 42);
        if (count($chunks) === 0) {
            $chunks = [['Tidak ada data.']];
        }

        $objects = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>';

        $pageObjectIds = [];
        $nextId = 4;

        foreach ($chunks as $pageLines) {
            $pageId = $nextId++;
            $contentId = $nextId++;
            $pageObjectIds[] = $pageId;

            $streamLines = [
                'BT',
                '/F1 9 Tf',
                '36 800 Td',
                '12 TL',
            ];

            foreach ($pageLines as $line) {
                $escaped = $this->escapePdfText($line);
                $streamLines[] = "({$escaped}) '";
            }

            $streamLines[] = 'ET';
            $streamData = implode("\n", $streamLines);
            $length = strlen($streamData);

            $objects[$contentId] = "<< /Length {$length} >>\nstream\n{$streamData}\nendstream";
            $objects[$pageId] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 842] /Resources << /Font << /F1 3 0 R >> >> /Contents {$contentId} 0 R >>";
        }

        $kids = implode(' ', array_map(static fn (int $id): string => "{$id} 0 R", $pageObjectIds));
        $pageCount = count($pageObjectIds);
        $objects[2] = "<< /Type /Pages /Kids [ {$kids} ] /Count {$pageCount} >>";

        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "{$id} 0 obj\n{$body}\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $totalObjects = count($objects) + 1;

        $pdf .= "xref\n0 {$totalObjects}\n";
        $pdf .= "0000000000 65535 f \n";

        for ($i = 1; $i < $totalObjects; $i++) {
            $offset = $offsets[$i] ?? 0;
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        $pdf .= "trailer\n<< /Size {$totalObjects} /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xrefOffset}\n%%EOF\n";

        return $pdf;
    }

    private function escapePdfText(string $text): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        $clean = $ascii !== false ? $ascii : $text;

        return str_replace(
            ['\\', '(', ')'],
            ['\\\\', '\\(', '\\)'],
            $clean
        );
    }
}

