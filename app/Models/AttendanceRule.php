<?php

namespace App\Models;

use Database\Factories\AttendanceRuleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceRule extends Model
{
    /** @use HasFactory<AttendanceRuleFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'day_of_week',
        'is_school_day',
        'check_in_start',
        'late_after',
        'check_out_start',
        'absent_cutoff',
        'debounce_seconds',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'is_school_day' => 'boolean',
            'debounce_seconds' => 'integer',
        ];
    }
}
