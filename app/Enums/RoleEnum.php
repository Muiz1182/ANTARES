<?php

namespace App\Enums;

enum RoleEnum: string
{
    case SuperAdmin = 'super_admin';
    case AdminTu = 'admin_tu';
    case Piket = 'piket';
    case WaliKelas = 'wali_kelas';
    case KepalaSekolah = 'kepala_sekolah';

    public function label(): string
    {
        return __('ui.roles.'.$this->value);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
