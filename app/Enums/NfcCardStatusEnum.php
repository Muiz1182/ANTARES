<?php

namespace App\Enums;

enum NfcCardStatusEnum: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Lost = 'lost';

    public function label(): string
    {
        return match ($this) {
            self::Active => __('ui.card_statuses.active'),
            self::Inactive => __('ui.card_statuses.inactive'),
            self::Lost => __('ui.card_statuses.lost'),
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

