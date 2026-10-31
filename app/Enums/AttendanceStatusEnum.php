<?php

namespace App\Enums;

enum AttendanceStatusEnum: string
{
    case Present = 'present';
    case Late = 'late';
    case Permission = 'permission';
    case Sick = 'sick';
    case Absent = 'absent';
    case Dispensation = 'dispensation';

    public function label(): string
    {
        return match ($this) {
            self::Present => __('ui.attendance_statuses.present'),
            self::Late => __('ui.attendance_statuses.late'),
            self::Permission => __('ui.attendance_statuses.permission'),
            self::Sick => __('ui.attendance_statuses.sick'),
            self::Absent => __('ui.attendance_statuses.absent'),
            self::Dispensation => __('ui.attendance_statuses.dispensation'),
        };
    }

    public function isExcused(): bool
    {
        return in_array($this, [
            self::Permission,
            self::Sick,
            self::Dispensation,
        ], true);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
