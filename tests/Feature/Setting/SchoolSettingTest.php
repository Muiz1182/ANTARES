<?php

use App\Enums\RoleEnum;
use App\Models\AuditLog;
use App\Models\SchoolSetting;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SchoolSettingSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->seed([
        RolePermissionSeeder::class,
        SchoolSettingSeeder::class,
    ]);
});

test('super_admin can view and update school settings including logo while other roles receive 403', function (): void {
    Storage::fake('public');

    $superAdmin = User::factory()->create();
    $superAdmin->assignRole(RoleEnum::SuperAdmin->value);

    $adminTu = User::factory()->create();
    $adminTu->assignRole(RoleEnum::AdminTu->value);

    $kepsek = User::factory()->create();
    $kepsek->assignRole(RoleEnum::KepalaSekolah->value);

    $this->actingAs($superAdmin)
        ->get(route('settings.edit'))
        ->assertOk();

    $logoFile = UploadedFile::fake()->create('logo-sekolah.png', 64, 'image/png');

    $this->actingAs($superAdmin)
        ->post(route('settings.update'), [
            'school_name' => 'SMP Negeri 2 Harapan Bangsa',
            'school_address' => 'Jl. Merdeka Raya No. 45, Bandung',
            'principal_name' => 'Dr. Suryadi, M.Pd.',
            'principal_nip' => '197805122003121002',
            'logo' => $logoFile,
        ])
        ->assertRedirect(route('settings.edit'))
        ->assertSessionHasNoErrors();

    $setting = SchoolSetting::current();
    expect($setting->school_name)->toBe('SMP Negeri 2 Harapan Bangsa')
        ->and($setting->school_address)->toBe('Jl. Merdeka Raya No. 45, Bandung')
        ->and($setting->principal_name)->toBe('Dr. Suryadi, M.Pd.')
        ->and($setting->logo_path)->not->toBeNull();

    Storage::disk('public')->assertExists($setting->logo_path);

    expect(
        AuditLog::query()
            ->where('action', 'school_setting.updated')
            ->where('user_id', $superAdmin->id)
            ->exists()
    )->toBeTrue();

    // Non-super_admin roles receive 403
    $this->actingAs($adminTu)
        ->get(route('settings.edit'))
        ->assertForbidden();

    $this->actingAs($kepsek)
        ->post(route('settings.update'), [
            'school_name' => 'SMP Tidak Sah',
        ])
        ->assertForbidden();
});

