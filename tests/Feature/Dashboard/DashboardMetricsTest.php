<?php

use App\Enums\AttendanceSourceEnum;
use App\Enums\AttendanceStatusEnum;
use App\Enums\NfcCardStatusEnum;
use App\Enums\RoleEnum;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\Device;
use App\Models\NfcCard;
use App\Models\Student;
use App\Models\User;
use App\Services\Attendance\ScanService;
use Carbon\CarbonImmutable;
use Database\Seeders\AttendanceRuleSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SchoolSettingSeeder;

beforeEach(function (): void {
    $this->seed([
        RolePermissionSeeder::class,
        AttendanceRuleSeeder::class,
        SchoolSettingSeeder::class,
    ]);
});

test('dashboard numbers and per-class breakdown equal the underlying attendance records for the same day', function (): void {
    $kepsek = User::factory()->create();
    $kepsek->assignRole(RoleEnum::KepalaSekolah->value);

    $wali7A = User::factory()->create();
    $wali7A->assignRole(RoleEnum::WaliKelas->value);

    $class7A = Classroom::factory()->create([
        'name' => '7A',
        'level' => 7,
        'homeroom_teacher_id' => $wali7A->id,
    ]);
    $class8A = Classroom::factory()->create([
        'name' => '8A',
        'level' => 8,
    ]);

    $stPresent = Student::factory()->create(['classroom_id' => $class7A->id, 'is_active' => true]);
    $stLate = Student::factory()->create(['classroom_id' => $class7A->id, 'is_active' => true]);
    $stSick = Student::factory()->create(['classroom_id' => $class7A->id, 'is_active' => true]);
    $stUnarrived7A = Student::factory()->create(['classroom_id' => $class7A->id, 'is_active' => true]);

    $stAbsent8A = Student::factory()->create(['classroom_id' => $class8A->id, 'is_active' => true]);
    $stPermission8A = Student::factory()->create(['classroom_id' => $class8A->id, 'is_active' => true]);

    $date = '2026-07-20';

    Attendance::factory()->create([
        'attendable_type' => Student::class,
        'attendable_id' => $stPresent->id,
        'date' => $date,
        'status' => AttendanceStatusEnum::Present,
        'source' => AttendanceSourceEnum::Nfc,
    ]);
    Attendance::factory()->create([
        'attendable_type' => Student::class,
        'attendable_id' => $stLate->id,
        'date' => $date,
        'status' => AttendanceStatusEnum::Late,
        'source' => AttendanceSourceEnum::Nfc,
    ]);
    Attendance::factory()->create([
        'attendable_type' => Student::class,
        'attendable_id' => $stSick->id,
        'date' => $date,
        'status' => AttendanceStatusEnum::Sick,
        'source' => AttendanceSourceEnum::Manual,
    ]);
    Attendance::factory()->create([
        'attendable_type' => Student::class,
        'attendable_id' => $stAbsent8A->id,
        'date' => $date,
        'status' => AttendanceStatusEnum::Absent,
        'source' => AttendanceSourceEnum::System,
    ]);
    Attendance::factory()->create([
        'attendable_type' => Student::class,
        'attendable_id' => $stPermission8A->id,
        'date' => $date,
        'status' => AttendanceStatusEnum::Permission,
        'source' => AttendanceSourceEnum::Manual,
    ]);

    // Kepala sekolah sees all 6 active students across 7A and 8A
    $this->actingAs($kepsek)
        ->get(route('dashboard', ['date' => $date]))
        ->assertOk();

    $liveRes = $this->actingAs($kepsek)
        ->getJson(route('dashboard.live-state', ['date' => $date]))
        ->assertOk();

    expect($liveRes->json('counts.total_active_students'))->toBe(6)
        ->and($liveRes->json('counts.present'))->toBe(1)
        ->and($liveRes->json('counts.late'))->toBe(1)
        ->and($liveRes->json('counts.permission'))->toBe(1)
        ->and($liveRes->json('counts.sick'))->toBe(1)
        ->and($liveRes->json('counts.absent'))->toBe(1)
        ->and($liveRes->json('counts.not_yet_arrived'))->toBe(1)
        ->and($liveRes->json('not_yet_arrived_students.0.id'))->toBe($stUnarrived7A->id);

    // Wali kelas 7A sees only the 4 students in 7A
    $waliLiveRes = $this->actingAs($wali7A)
        ->getJson(route('dashboard.live-state', ['date' => $date]))
        ->assertOk();

    expect($waliLiveRes->json('counts.total_active_students'))->toBe(4)
        ->and($waliLiveRes->json('counts.present'))->toBe(1)
        ->and($waliLiveRes->json('counts.late'))->toBe(1)
        ->and($waliLiveRes->json('counts.sick'))->toBe(1)
        ->and($waliLiveRes->json('counts.absent'))->toBe(0)
        ->and($waliLiveRes->json('counts.not_yet_arrived'))->toBe(1);
});

test('a scan on the Scan Station immediately appears in the dashboard live feed endpoint', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole(RoleEnum::AdminTu->value);

    $device = Device::factory()->create([
        'name' => 'Pos Gerbang Barat',
        'is_active' => true,
    ]);
    $classroom = Classroom::factory()->create(['name' => '7B', 'level' => 7]);
    $student = Student::factory()->create([
        'name' => 'Rizky Pratama',
        'classroom_id' => $classroom->id,
        'is_active' => true,
    ]);

    NfcCard::factory()->create([
        'uid' => 'EE112233',
        'cardable_type' => Student::class,
        'cardable_id' => $student->id,
        'status' => NfcCardStatusEnum::Active,
    ]);

    $scanTime = CarbonImmutable::create(2026, 7, 20, 6, 45, 0, 'Asia/Jakarta');
    app(ScanService::class)->processTap('EE112233', $device, $scanTime);

    $liveRes = $this->actingAs($admin)
        ->getJson(route('dashboard.live-state', ['date' => '2026-07-20']))
        ->assertOk();

    expect($liveRes->json('counts.present'))->toBe(1)
        ->and($liveRes->json('counts.not_yet_arrived'))->toBe(0)
        ->and($liveRes->json('recent_taps'))->toHaveCount(1)
        ->and($liveRes->json('recent_taps.0.student.name'))->toBe('Rizky Pratama')
        ->and($liveRes->json('recent_taps.0.device_name'))->toBe('Pos Gerbang Barat');
});

