<?php

namespace App\Enums;

enum ScanResultEnum: string
{
    case SuccessIn = 'success_in';
    case SuccessOut = 'success_out';
    case Duplicate = 'duplicate';
    case AlreadyCheckedIn = 'already_checked_in';
    case AlreadyCheckedOut = 'already_checked_out';
    case NoCheckin = 'no_checkin';
    case BlockedExcused = 'blocked_excused';
    case UnknownCard = 'unknown_card';
    case InactiveCard = 'inactive_card';
    case LostCard = 'lost_card';
    case StudentInactive = 'student_inactive';
    case NoSchoolDay = 'no_school_day';

    public function isOk(): bool
    {
        return $this === self::SuccessIn || $this === self::SuccessOut;
    }

    public function message(): string
    {
        return __('ui.scan_results.'.$this->value);
    }

    public function soundTone(): string
    {
        return match ($this) {
            self::SuccessIn, self::SuccessOut => 'success',
            self::Duplicate, self::AlreadyCheckedIn, self::AlreadyCheckedOut => 'duplicate',
            self::LostCard, self::BlockedExcused => 'warning',
            self::NoCheckin, self::UnknownCard, self::InactiveCard, self::StudentInactive, self::NoSchoolDay => 'error',
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

