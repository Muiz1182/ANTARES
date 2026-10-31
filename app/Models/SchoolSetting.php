<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class SchoolSetting extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'school_name',
        'school_address',
        'principal_name',
        'principal_nip',
        'logo_path',
        'timezone',
    ];

    public static function current(): self
    {
        if (! Schema::hasTable('school_settings')) {
            return new self([
                'school_name' => 'SMP Negeri 1 Nusantara',
                'school_address' => 'Jl. Pendidikan No. 1, Jakarta',
                'principal_name' => 'Drs. Hendra Wijaya, M.Pd.',
                'principal_nip' => '197502182000031001',
                'logo_path' => null,
                'timezone' => 'Asia/Jakarta',
            ]);
        }

        /** @var self|null $setting */
        $setting = self::query()->first();

        if ($setting !== null) {
            return $setting;
        }

        return self::query()->create([
            'school_name' => 'SMP Negeri 1 Nusantara',
            'school_address' => 'Jl. Pendidikan No. 1, Jakarta',
            'principal_name' => 'Drs. Hendra Wijaya, M.Pd.',
            'principal_nip' => '197502182000031001',
            'logo_path' => null,
            'timezone' => 'Asia/Jakarta',
        ]);
    }

    public function logoUrl(): ?string
    {
        if ($this->logo_path === null || $this->logo_path === '') {
            return null;
        }

        return Storage::disk('public')->url($this->logo_path);
    }
}

