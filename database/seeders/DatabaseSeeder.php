<?php

namespace Database\Seeders;

use App\Enums\NfcCardStatusEnum;
use App\Enums\RoleEnum;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Device;
use App\Models\Holiday;
use App\Models\NfcCard;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public const DEFAULT_DEV_DEVICE_TOKEN = 'dev-station-token-gerbang-utama-2026';

    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            AttendanceRuleSeeder::class,
            SchoolSettingSeeder::class,
        ]);

        $superAdmin = User::updateOrCreate(
            ['email' => 'superadmin@sekolah.sch.id'],
            [
                'name' => 'Administrator Sistem',
                'nip' => '198501012010011001',
                'phone' => '081200000001',
                'password' => Hash::make('password'),
                'is_active' => true,
            ]
        );
        $superAdmin->syncRoles([RoleEnum::SuperAdmin->value]);

        $adminTu = User::updateOrCreate(
            ['email' => 'tu@sekolah.sch.id'],
            [
                'name' => 'Staf Tata Usaha',
                'nip' => '198803122012012002',
                'phone' => '081200000002',
                'password' => Hash::make('password'),
                'is_active' => true,
            ]
        );
        $adminTu->syncRoles([RoleEnum::AdminTu->value]);

        $piket = User::updateOrCreate(
            ['email' => 'piket@sekolah.sch.id'],
            [
                'name' => 'Petugas Piket Harian',
                'nip' => '199107152015011003',
                'phone' => '081200000003',
                'password' => Hash::make('password'),
                'is_active' => true,
            ]
        );
        $piket->syncRoles([RoleEnum::Piket->value]);

        $wali7A = User::updateOrCreate(
            ['email' => 'wali7a@sekolah.sch.id'],
            [
                'name' => 'Budi Santoso, S.Pd.',
                'nip' => '198604202011011004',
                'phone' => '081200000004',
                'password' => Hash::make('password'),
                'is_active' => true,
            ]
        );
        $wali7A->syncRoles([RoleEnum::WaliKelas->value]);

        $wali8A = User::updateOrCreate(
            ['email' => 'wali8a@sekolah.sch.id'],
            [
                'name' => 'Siti Aminah, S.Pd.',
                'nip' => '198909102014012005',
                'phone' => '081200000005',
                'password' => Hash::make('password'),
                'is_active' => true,
            ]
        );
        $wali8A->syncRoles([RoleEnum::WaliKelas->value]);

        $kepalaSekolah = User::updateOrCreate(
            ['email' => 'kepsek@sekolah.sch.id'],
            [
                'name' => 'Drs. Hendra Wijaya, M.Pd.',
                'nip' => '197502182000031001',
                'phone' => '081200000006',
                'password' => Hash::make('password'),
                'is_active' => true,
            ]
        );
        $kepalaSekolah->syncRoles([RoleEnum::KepalaSekolah->value]);

        $academicYear = AcademicYear::updateOrCreate(
            ['name' => '2026/2027'],
            [
                'start_date' => '2026-07-13',
                'end_date' => '2027-06-19',
                'is_active' => true,
            ]
        );

        $class7A = Classroom::updateOrCreate(
            ['academic_year_id' => $academicYear->id, 'name' => '7A'],
            ['level' => 7, 'homeroom_teacher_id' => $wali7A->id]
        );

        $class8A = Classroom::updateOrCreate(
            ['academic_year_id' => $academicYear->id, 'name' => '8A'],
            ['level' => 8, 'homeroom_teacher_id' => $wali8A->id]
        );

        $class9A = Classroom::updateOrCreate(
            ['academic_year_id' => $academicYear->id, 'name' => '9A'],
            ['level' => 9, 'homeroom_teacher_id' => null]
        );

        if (Student::count() === 0) {
            Student::factory()->count(10)->create(['classroom_id' => $class7A->id]);
            Student::factory()->count(10)->create(['classroom_id' => $class8A->id]);
            Student::factory()->count(10)->create(['classroom_id' => $class9A->id]);
        }

        Holiday::updateOrCreate(
            ['date' => '2026-08-17'],
            [
                'name' => 'Hari Kemerdekaan Republik Indonesia',
                'description' => 'Libur nasional peringatan HUT RI',
            ]
        );

        Device::updateOrCreate(
            ['name' => 'Pos Gerbang Utama'],
            [
                'location' => 'Gerbang Depan Sekolah',
                'token_hash' => Device::hashToken(self::DEFAULT_DEV_DEVICE_TOKEN),
                'is_active' => true,
            ]
        );

        $sampleUids = ['04A1B2C3', 'C3B2A104', '0044DA59', '04A1B2C3D4E5F6'];
        $demoStudents = Student::query()->orderBy('id')->limit(count($sampleUids))->get();

        foreach ($demoStudents as $index => $student) {
            NfcCard::updateOrCreate(
                ['uid' => $sampleUids[$index]],
                [
                    'cardable_type' => Student::class,
                    'cardable_id' => $student->id,
                    'status' => NfcCardStatusEnum::Active,
                    'issued_at' => now(),
                    'issued_by' => $adminTu->id,
                    'notes' => 'Kartu sampel bawaan seeder pengembangan',
                ]
            );
        }
    }
}
