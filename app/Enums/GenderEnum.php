<?php

namespace App\Enums;

enum GenderEnum: string
{
    case Male = 'L';
    case Female = 'P';

    public function label(): string
    {
        return __('ui.genders.'.$this->value);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
