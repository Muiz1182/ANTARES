<?php

namespace Database\Factories;

use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'action' => 'attendance.corrected',
            'subject_type' => Attendance::class,
            'subject_id' => 1,
            'reason' => 'Koreksi data absensi berdasarkan surat keterangan.',
            'before' => ['status' => 'absent'],
            'after' => ['status' => 'sick'],
        ];
    }
}
