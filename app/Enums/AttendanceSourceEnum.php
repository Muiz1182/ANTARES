<?php

namespace App\Enums;

enum AttendanceSourceEnum: string
{
    case Nfc = 'nfc';
    case Manual = 'manual';
    case System = 'system';

    public function label(): string
    {
        return match ($this) {
            self::Nfc => __('ui.attendance_sources.nfc'),
            self::Manual => __('ui.attendance_sources.manual'),
            self::System => __('ui.attendance_sources.system'),
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
