<?php

namespace App\Console\Commands;

use App\Actions\Attendance\MarkAutoAbsentAction;
use Carbon\Carbon;
use Illuminate\Console\Command;

class MarkAutoAbsentCommand extends Command
{
    protected $signature = 'attendance:mark-absent
        {--date= : Tanggal evaluasi (YYYY-MM-DD)}
        {--force-time : Abaikan pengecekan jam absent_cutoff}';

    protected $description = 'Tandai siswa aktif yang belum memiliki catatan kehadiran sebagai Alpa setelah jam cutoff pada hari sekolah efektif';

    public function handle(MarkAutoAbsentAction $action): int
    {
        $dateOption = $this->option('date');
        $forceTime = (bool) $this->option('force-time');

        $evaluatedAt = is_string($dateOption) && trim($dateOption) !== ''
            ? Carbon::parse($dateOption, 'Asia/Jakarta')->setTimeFrom(Carbon::now('Asia/Jakarta'))
            : Carbon::now('Asia/Jakarta');

        $count = $action->execute(
            evaluatedAt: $evaluatedAt,
            ignoreCutoffTime: $forceTime,
        );

        $this->info("Selesai memproses status Alpa otomatis: {$count} siswa ditandai Alpa.");

        return self::SUCCESS;
    }
}
